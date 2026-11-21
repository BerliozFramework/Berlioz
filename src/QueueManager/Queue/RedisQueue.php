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

readonly class RedisQueue extends AbstractQueue implements QueueInterface, MonitorableQueueInterface
{
    public function __construct(
        private Redis $redis,
        string $name = 'default',
        RateLimiterInterface $limiter = new NullRateLimiter(),
        private int $deletedJobsTtl = 86400,
        private int $maxAttempts = 5,
        private int $retryTime = 30,
    ) {
        parent::__construct(name: $name, limiter: $limiter);
    }

    /**
     * @inheritDoc
     */
    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    /**
     * @inheritDoc
     */
    public function getRetryTime(): int
    {
        return $this->retryTime;
    }

    /**
     * @inheritDoc
     */
    public function size(): int
    {
        $this->freeDelayedJobs();

        return $this->redis->llen($this->name) ?: 0;
    }

    /**
     * @inheritDoc
     */
    public function waitTime(): ?int
    {
        $this->freeDelayedJobs();

        $firstPayload = $this->redis->lindex($this->name, 0);
        if ($firstPayload === false) {
            return null;
        }

        $jobRaw = $this->decodeJobRaw($firstPayload);
        if (null === $jobRaw) {
            return null;
        }

        if (isset($jobRaw['availableAt']) && is_int($jobRaw['availableAt'])) {
            return max(0, time() - $jobRaw['availableAt']);
        }

        if (!isset($jobRaw['createdAt']) || !is_int($jobRaw['createdAt'])) {
            return 0;
        }

        return max(0, time() - $jobRaw['createdAt']);
    }

    /**
     * @inheritDoc
     */
    public function delayed(): ?int
    {
        $this->freeDelayedJobs();

        return $this->redis->zcard($this->getDelayedQueueKey()) ?: 0;
    }

    public function freeDelayedJobs(): void
    {
        $lockKey = $this->getDelayedQueueKey() . ':lock';
        $lockValue = bin2hex(random_bytes(16));
        $delayedQueueKey = $this->getDelayedQueueKey();
        $currentTime = time();

        try {
            // Attempt to acquire lock with unique value
            if (!$this->redis->set($lockKey, $lockValue, ['nx', 'ex' => 10])) {
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
            // Release lock only if we still own it
            if ($this->redis->get($lockKey) === $lockValue) {
                $this->redis->del($lockKey);
            }
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
            $this->recoverProcessingJobs();

            // Move the job to the processing set atomically, with the time after which it is recovered
            $payload = $this->redis->eval(
                <<<'LUA'
                local message = redis.call('LPOP', KEYS[1])
                if message then
                    redis.call('ZADD', KEYS[2], ARGV[1], message)
                end
                return message
                LUA,
                [$this->name, $this->getProcessingQueueKey(), (string)(time() + $this->retryTime)],
                2,
            );
            if (!is_string($payload)) {
                return null;
            }

            $jobRaw = $this->decodeJobRaw($payload);

            if (null === $jobRaw) {
                throw new QueueException('Invalid job structure. Missing required fields: jobId or payload.');
            }

            $this->getRateLimiter()->pop();

            return $this->createJob($jobRaw, $payload);
        } catch (Exception $e) {
            throw new QueueException('Failed to consume a job.', 0, $e);
        }
    }

    /**
     * Create job.
     *
     * @param array $raw
     * @param string|null $message
     *
     * @return RedisJob
     */
    protected function createJob(array $raw, ?string $message = null): RedisJob
    {
        $payload = json_decode((string)$raw['payload'], true);

        if (!is_array($payload)) {
            throw new QueueException('Failed to decode Redis job payload as JSON');
        }

        $name = $payload['jobName'] ?? null;
        unset($payload['jobName']);

        return new RedisJob(
            id: (string)$raw['jobId'],
            name: $name,
            attempts: ($raw['attempts'] ?? 0) + 1,
            payload: $payload,
            queue: $this,
            redisMessage: $message,
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
        $availableAt = $this->getAvailableDateTime($delay);
        $delaySeconds = $this->getDelayInSeconds($availableAt);
        $createdAt = time();
        $jobData = [
            'jobId' => uniqid(more_entropy: true),
            'payload' => json_encode($payload),
            'attempts' => $attempts,
            'createdAt' => $createdAt,
            'availableAt' => $availableAt->getTimestamp(),
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

        // Already recovered (retry time exceeded) and pushed back to the queue
        if (false === $this->removeFromProcessing($job)) {
            return;
        }

        // Max attempts reached, move job to the dead list
        if ($job->getAttempts() >= $this->maxAttempts) {
            $this->redis->rPush(
                $this->getDeadQueueKey(),
                json_encode([
                    'jobId' => $job->getId(),
                    'payload' => json_encode($job),
                    'attempts' => $job->getAttempts(),
                    'createdAt' => time(),
                ]),
            );
            return;
        }

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

        $this->removeFromProcessing($job);

        $deletedJobsKey = $this->getDeletedJobsKey();
        $this->redis->hset($deletedJobsKey, $job->getId(), json_encode($job));
        if ($this->deletedJobsTtl > 0) {
            $this->redis->expire($deletedJobsKey, $this->deletedJobsTtl);
        }
    }

    private function getDelayedQueueKey(): string
    {
        return $this->name . ':delayed';
    }

    /**
     * Recover processing jobs whose retry time is exceeded.
     *
     * The consumer died (or is too long) without deleting or releasing the job: it is counted as a failed attempt,
     * and the job is pushed back to the queue, or to the dead list once max attempts is reached.
     *
     * @return void
     * @throws QueueException
     */
    public function recoverProcessingJobs(): void
    {
        try {
            $messages = $this->redis->zrangebyscore($this->getProcessingQueueKey(), '-inf', (string)time());

            foreach ($messages as $message) {
                $destination = $this->getDeadQueueKey();
                $recovered = $message;

                if (null !== ($jobRaw = $this->decodeJobRaw($message))) {
                    $jobRaw['attempts'] = ($jobRaw['attempts'] ?? 0) + 1;
                    $jobRaw['availableAt'] = time();
                    $destination = $jobRaw['attempts'] >= $this->maxAttempts ? $destination : $this->name;
                    $recovered = json_encode($jobRaw, JSON_THROW_ON_ERROR);
                }

                // Atomically publish and remove, so a recovering worker crash cannot lose the job.
                // Publish first: Redis scripts do not roll back writes if RPUSH fails (e.g. wrong key type).
                $result = $this->redis->eval(
                    <<<'LUA'
                    if redis.call('ZSCORE', KEYS[1], ARGV[1]) then
                        redis.call('RPUSH', KEYS[2], ARGV[2])
                        redis.call('ZREM', KEYS[1], ARGV[1])
                        return 1
                    end
                    return 0
                    LUA,
                    [$this->getProcessingQueueKey(), $destination, $message, $recovered],
                    2,
                );

                if (false === $result) {
                    throw new QueueException('Failed to publish a recovered job.');
                }
            }
        } catch (Exception $e) {
            throw new QueueException('Failed to recover processing jobs.', 0, $e);
        }
    }

    /**
     * Remove job from processing set.
     *
     * @param RedisJob $job
     *
     * @return bool FALSE if the job was no longer in processing set
     */
    private function removeFromProcessing(RedisJob $job): bool
    {
        if (null === ($message = $job->getRedisMessage())) {
            return true;
        }

        return 1 === $this->redis->zrem($this->getProcessingQueueKey(), $message);
    }

    private function getProcessingQueueKey(): string
    {
        return $this->name . ':processing';
    }

    private function getDeadQueueKey(): string
    {
        return $this->name . ':dead';
    }

    private function getDeletedJobsKey(): string
    {
        return $this->name . ':deleted';
    }

    private function decodeJobRaw(string $payload): ?array
    {
        $jobRaw = json_decode($payload, true);

        if (!is_array($jobRaw) || !isset($jobRaw['jobId'], $jobRaw['payload'])) {
            return null;
        }

        return $jobRaw;
    }
}
