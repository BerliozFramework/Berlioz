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

use ArrayObject;
use Berlioz\QueueManager\Exception\JobException;
use Berlioz\QueueManager\Exception\QueueException;
use Berlioz\QueueManager\Job\JobDescriptorInterface;
use Berlioz\QueueManager\Job\JobInterface;
use Berlioz\QueueManager\Job\MemoryJob;
use Berlioz\QueueManager\RateLimiter\NullRateLimiter;
use Berlioz\QueueManager\RateLimiter\RateLimiterInterface;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;

readonly class MemoryQueue extends AbstractQueue implements PurgeableQueueInterface
{
    private ArrayObject $stack;

    public function __construct(
        string $name = 'default',
        private int $retryTime = 30,
        private int $maxAttempts = 5,
        RateLimiterInterface $limiter = new NullRateLimiter(),
    ) {
        parent::__construct(name: $name, limiter: $limiter);
        $this->stack = new ArrayObject();
    }

    public function nowDeltaRetry(): DateTimeImmutable
    {
        return $this->now()->sub(new DateInterval('PT' . $this->retryTime . 'S'));
    }

    private function jobRawCanBeConsumed(array $value): bool
    {
        if ($value['attempts'] >= $this->maxAttempts) {
            return false;
        }

        if ($value['available_time'] > $this->now()) {
            return false;
        }

        if (null !== $value['lock_time']) {
            if ($value['lock_time'] > $this->nowDeltaRetry()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function size(): int
    {
        $total = 0;

        foreach ($this->stack as $value) {
            if (false === $this->jobRawCanBeConsumed($value)) {
                continue;
            }

            $total++;
        }

        return $total;
    }

    /**
     * @inheritDoc
     */
    public function consume(): ?MemoryJob
    {
        // Rate limit reached? Wait...
        $this->getRateLimiter()->wait();

        foreach ($this->stack as $id => $value) {
            if (false === $this->jobRawCanBeConsumed($value)) {
                continue;
            }

            $this->stack->offsetSet(
                $id,
                $value = [
                    ...$value,
                    'attempts' => $value['attempts'] + 1,
                    'lock_time' => $this->now()
                ]
            );

            $this->getRateLimiter()->pop();

            return $this->createJob(['id' => $id, ...$value]);
        }

        return null;
    }

    /**
     * Create job.
     *
     * @param array $raw
     *
     * @return MemoryJob
     */
    protected function createJob(array $raw): MemoryJob
    {
        $payload = json_decode($raw['payload'], true);
        $name = $payload['jobName'] ?? null;
        unset($payload['jobName']);

        return new MemoryJob(
            id: $raw['id'],
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
        $this->stack->offsetSet(
            $id = uniqid(),
            [
                'id' => $id,
                'create_time' => $this->now(),
                'delay' => $delay,
                'available_time' => $this->getAvailableDateTime($delay),
                'lock_time' => null,
                'attempts' => $attempts,
                'payload' => json_encode($payload),
            ]
        );
        $this->sort();

        return $id;
    }

    private function sort(): void
    {
        $this->stack->uasort(fn($a, $b) => $a['available_time'] <=> $b['available_time']);
    }

    /**
     * @inheritDoc
     */
    public function purge(): void
    {
        $this->stack->exchangeArray([]);
    }

    /**
     * Release job.
     *
     * @param MemoryJob $job
     * @param int $delay
     *
     * @return void
     * @throws JobException
     * @throws QueueException
     */
    public function release(MemoryJob $job, int $delay = 0): void
    {
        $job->isReleased() && throw JobException::alreadyReleased($job);
        $job->isDeleted() && throw JobException::alreadyDeleted($job);

        $raw = $this->stack->offsetGet($job->getId());
        $this->stack->offsetSet(
            $job->getId(),
            [
                ...$raw,
                'available_time' => $this->getAvailableDateTime($delay),
                'lock_time' => null,
            ]
        );
    }

    /**
     * Delete job.
     *
     * @param MemoryJob $job
     *
     * @return void
     * @throws JobException
     */
    public function delete(MemoryJob $job): void
    {
        $job->isReleased() && throw JobException::alreadyReleased($job);
        $job->isDeleted() && throw JobException::alreadyDeleted($job);

        $this->stack->offsetUnset($job->getId());
    }
}
