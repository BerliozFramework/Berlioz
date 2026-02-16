<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2024 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\QueueManager\Queue;

use Berlioz\QueueManager\Exception\JobException;
use Berlioz\QueueManager\Exception\QueueException;
use Berlioz\QueueManager\Exception\QueueManagerException;
use Berlioz\QueueManager\Job\DbJob;
use Berlioz\QueueManager\Job\JobDescriptorInterface;
use Berlioz\QueueManager\Job\JobInterface;
use Berlioz\QueueManager\RateLimiter\NullRateLimiter;
use Berlioz\QueueManager\RateLimiter\RateLimiterInterface;
use DateInterval;
use DateTimeInterface;
use Hector\Connection\Connection;
use Hector\Query\Component\Order;
use Hector\Query\QueryBuilder;
use Hector\Query\Statement\Conditions;
use Throwable;

class_exists(QueryBuilder::class) || throw QueueManagerException::missingPackage('hectororm/query');

readonly class DbQueue extends AbstractQueue implements PurgeableQueueInterface
{
    public function __construct(
        private Connection $connection,
        string $name = 'default',
        private string $tableName = 'queue_jobs',
        private int $retryTime = 30,
        private int $maxAttempts = 5,
        RateLimiterInterface $limiter = new NullRateLimiter(),
    ) {
        parent::__construct(name: $name, limiter: $limiter);
    }

    private function getRetryDateTimeLimit(): DateTimeInterface
    {
        return $this->now()->sub(new DateInterval('PT' . $this->retryTime . 'S'));
    }

    private function getQueryBuilder(): QueryBuilder
    {
        $builder = new QueryBuilder($this->connection);
        $builder->from($this->tableName);
        $builder->where('queue', $this->getName());

        return $builder;
    }

    private function addBuilderConditions(QueryBuilder $builder): QueryBuilder
    {
        $retryLimit = $this->getRetryDateTimeLimit();

        return $builder
            ->whereLessThanOrEqual('availability_time', $this->now()->format('Y-m-d H:i:s'))
            ->whereLessThan('attempts', $this->maxAttempts)
            ->where(function (Conditions $where) use ($retryLimit): void {
                $where
                    ->whereNull('lock_time')
                    ->orWhere('lock_time', '<', $retryLimit->format('Y-m-d H:i:s'));
            });
    }

    /**
     * @inheritDoc
     */
    public function size(): int
    {
        return $this->addBuilderConditions($this->getQueryBuilder())->count();
    }

    /**
     * @inheritDoc
     */
    public function consume(): ?DbJob
    {
        if (true === $this->connection->inTransaction()) {
            throw new QueueException('A database transaction is already running');
        }

        // Rate limit reached? Wait...
        $this->getRateLimiter()->wait();

        try {
            $this->connection->beginTransaction();

            $jobRaw = $this->addBuilderConditions($this->getQueryBuilder())
                ->orderBy('job_id', Order::ORDER_ASC)
                ->limit(1)
                ->fetchOne(true);

            if (null === $jobRaw) {
                $this->connection->commit();
                return null;
            }

            // Lock
            $affected = $this->getQueryBuilder()
                ->whereEquals(
                    array_filter(
                        $jobRaw,
                        fn($k) => in_array(
                            $k,
                            ['job_id', 'queue', 'availability_time', 'attempts', 'lock_time']
                        ),
                        ARRAY_FILTER_USE_KEY
                    )
                )
                ->update($updatedData = [
                    'lock_time' => $this->now()->format('Y-m-d H:i:s'),
                    'attempts' => ($jobRaw['attempts'] ?? 0) + 1,
                ]);

            $this->connection->commit();
        } catch (Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }

        if ($affected === 1) {
            $this->getRateLimiter()->pop();

            return $this->createJob(array_replace($jobRaw, $updatedData));
        }

        return null;
    }

    /**
     * Create job.
     *
     * @param array $raw
     *
     * @return DbJob
     */
    protected function createJob(array $raw): DbJob
    {
        $payload = json_decode($raw['payload'], true);
        $name = $payload['jobName'] ?? null;
        unset($payload['jobName']);

        return new DbJob(
            id: (string)$raw['job_id'],
            name: $name,
            attempts: $raw['attempts'] ?? 0,
            payload: $payload,
            queue: $this,
        );
    }

    /**
     * @inheritDoc
     */
    public function push(JobDescriptorInterface $jobDescriptor, DateTimeInterface|DateInterval|int $delay = 0): string
    {
        return $this->pushRaw(
            payload: $jobDescriptor,
            delay: $delay,
            attempts: $jobDescriptor instanceof JobInterface ? $jobDescriptor->getAttempts() : 0,
        );
    }

    /**
     * @inheritDoc
     */
    public function pushRaw(mixed $payload, DateTimeInterface|DateInterval|int $delay = 0, int $attempts = 0): string
    {
        $now = $this->now();

        $this->getQueryBuilder()
            ->insert([
                'create_time' => $now->format('Y-m-d H:i:s'),
                'queue' => $this->name,
                'availability_time' => $this->getAvailableDateTime($delay)->format('Y-m-d H:i:s'),
                'attempts' => $attempts,
                'payload' => json_encode($payload),
            ]);

        return $this->connection->getLastInsertId($this->tableName);
    }

    /**
     * @inheritDoc
     */
    public function purge(): void
    {
        $this->getQueryBuilder()->delete();
    }

    /**
     * Release job.
     *
     * @param DbJob $job
     * @param int $delay
     *
     * @return void
     * @throws QueueException
     */
    public function release(DbJob $job, int $delay = 0): void
    {
        $job->isReleased() && throw JobException::alreadyReleased($job);
        $job->isDeleted() && throw JobException::alreadyDeleted($job);

        $this->getQueryBuilder()
            ->where('job_id', $job->getId())
            ->update([
                'availability_time' => $this->getAvailableDateTime($delay)->format('Y-m-d H:i:s'),
                'lock_time' => null,
            ]);
    }

    /**
     * Delete job.
     *
     * @param DbJob $job
     *
     * @return void
     * @throws JobException
     */
    public function delete(DbJob $job): void
    {
        $job->isReleased() && throw JobException::alreadyReleased($job);
        $job->isDeleted() && throw JobException::alreadyDeleted($job);

        $this->getQueryBuilder()
            ->where('job_id', $job->getId())
            ->delete();
    }
}
