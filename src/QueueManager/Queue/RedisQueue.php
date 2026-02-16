<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2025 Ronan GIRON
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
use Berlioz\QueueManager\Job\JobDescriptorInterface;
use Berlioz\QueueManager\Job\JobInterface;
use Berlioz\QueueManager\Job\RedisJob;
use Berlioz\QueueManager\RateLimiter\NullRateLimiter;
use Berlioz\QueueManager\RateLimiter\RateLimiterInterface;
use DateInterval;
use DateTimeInterface;
use Exception;
use Redis;

class_exists(Redis::class) || throw QueueManagerException::missingPackage('ext-redis');

readonly class RedisQueue extends AbstractQueue implements QueueInterface
{
    public function __construct(
        private Redis $redis,
        string $name = 'default',
        RateLimiterInterface $limiter = new NullRateLimiter(),
    ) {
        parent::__construct(name: $name, limiter: $limiter);
    }

    /**
     * @inheritDoc
     */
    public function size(): int
    {
        $this->freeDelayedJobs();

        return $this->redis->llen($this->name) ?: 0;
    }

    public function freeDelayedJobs(): void
    {
        $lockKey = $this->getDelayedQueueKey() . ':lock';
        $delayedQueueKey = $this->getDelayedQueueKey();
        $currentTime = time();

        try {
            // Attempt to acquire lock
            if (!$this->redis->set($lockKey, '1', ['nx', 'ex' => 10])) {
                // Lock already held by another process
                return;
            }

            // Get jobs ready to be processed
            $jobs = $this->redis->zrangebyscore($delayedQueueKey, '-inf', (string)$currentTime);

            foreach ($jobs as $job) {
                // Remove job from delayed queue
                $this->redis->zrem($delayedQueueKey, $job);

                // Add job to main queue
                $this->redis->rpush($this->name, $job);
            }
        } catch (Exception $e) {
            throw new QueueException('Failed to process delayed jobs.', 0, $e);
        } finally {
            // Release lock
            $this->redis->del($lockKey);
        }
    }

    /**
     * @inheritDoc
     */
    public function consume(): ?RedisJob
    {
        try {
            // Rate limit reached? Wait...
            $this->getRateLimiter()->wait();

            $this->freeDelayedJobs();

            $payload = $this->redis->lpop($this->name);
            if ($payload === false) {
                return null;
            }

            $jobRaw = json_decode($payload, true);

            if (!isset($jobRaw['jobId'], $jobRaw['payload'])) {
                throw new QueueException('Invalid job structure. Missing required fields: jobId or payload.');
            }

            $this->getRateLimiter()->pop();

            return $this->createJob($jobRaw);
        } catch (Exception $e) {
            throw new QueueException('Failed to consume a job.', 0, $e);
        }
    }

    /**
     * Create job.
     *
     * @param array $raw
     *
     * @return RedisJob
     */
    protected function createJob(array $raw): RedisJob
    {
        $payload = json_decode($raw['payload'], true);
        $name = $payload['jobName'] ?? null;
        unset($payload['jobName']);

        return new RedisJob(
            id: (string)$raw['jobId'],
            name: $name,
            attempts: ($raw['attempts'] ?? 0) + 1,
            payload: $payload,
            queue: $this,
        );
    }

    /**
     * @inheritDoc
     */
    public function push(
        JobDescriptorInterface $jobDescriptor,
        DateTimeInterface|DateInterval|int $delay = 0
    ): string {
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
        $delaySeconds = $this->getDelayInSeconds($this->getAvailableDateTime($delay));
        $jobData = [
            'jobId' => uniqid(more_entropy: true),
            'payload' => json_encode($payload),
            'attempts' => $attempts
        ];

        if ($delaySeconds > 0) {
            $this->redis->zadd(
                $this->getDelayedQueueKey(),
                ['NX'],
                time() + $delaySeconds,
                json_encode($jobData)
            );
        } else {
            $this->redis->rPush($this->name, json_encode($jobData));
        }

        return $jobData['jobId'];
    }

    /**
     * Release job.
     *
     * @param RedisJob $job
     * @param int $delay
     *
     * @return void
     * @throws QueueException
     */
    public function release(RedisJob $job, int $delay = 0): void
    {
        $job->isReleased() && throw JobException::alreadyReleased($job);
        $job->isDeleted() && throw JobException::alreadyDeleted($job);

        $this->push($job, $delay);
    }

    /**
     * Delete job.
     *
     * @param RedisJob $job
     *
     * @return void
     * @throws JobException
     */
    public function delete(RedisJob $job): void
    {
        $job->isReleased() && throw JobException::alreadyReleased($job);
        $job->isDeleted() && throw JobException::alreadyDeleted($job);

        $this->redis->hset($this->getDeletedJobsKey(), $job->getId(), json_encode($job));
    }

    private function getDelayedQueueKey(): string
    {
        return $this->name . ':delayed';
    }

    private function getDeletedJobsKey(): string
    {
        return $this->name . ':deleted';
    }
}
