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

namespace Berlioz\QueueManager;

use Berlioz\QueueManager\Exception\QueueException;
use Berlioz\QueueManager\Exception\QueueManagerException;
use Berlioz\QueueManager\Handler\JobHandlerInterface;
use Berlioz\QueueManager\Job\JobInterface;
use Berlioz\QueueManager\Queue\QueueInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Throwable;

class Worker implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private bool $shouldTerminate = false;

    public function __construct(
        private readonly JobHandlerInterface $jobHandlerManager,
    ) {
        $this->initSignalHandler();
    }

    /**
     * Init signal handler.
     *
     * @return void
     */
    public function initSignalHandler(): void
    {
        if (!extension_loaded('pcntl')) {
            return;
        }

        pcntl_async_signals(true);
        pcntl_signal(SIGQUIT, fn() => $this->shouldTerminate = true);
        pcntl_signal(SIGTERM, fn() => $this->shouldTerminate = true);
    }

    /**
     * Get memory usage (in MB).
     *
     * @return int|float
     */
    protected function getMemoryUsage(): int|float
    {
        return memory_get_usage(true) / 1024 / 1024;
    }

    /**
     * Memory limit exceeded?
     *
     * @param WorkerOptions $options
     *
     * @return bool
     */
    public function memoryLimitExceeded(WorkerOptions $options): bool
    {
        return $this->getMemoryUsage() >= $options->memoryLimit;
    }

    /**
     * hrtime().
     *
     * @return int
     */
    protected function hrtime(): int
    {
        return (int)hrtime(true);
    }

    /**
     * Time limit exceeded?
     *
     * @param int|float $startTime
     * @param WorkerOptions $options
     *
     * @return bool
     */
    public function timeLimitExceeded(int|float $startTime, WorkerOptions $options): bool
    {
        return ($this->hrtime() - $startTime) >= ($options->timeLimit * 1000000000);
    }

    /**
     * Kill file exists?
     *
     * @param WorkerOptions $options
     *
     * @return bool
     */
    public function killFileExists(WorkerOptions $options): bool
    {
        if (null === $options->killFilePath) {
            return false;
        }

        return file_exists($options->killFilePath);
    }

    /**
     * Continue?
     *
     * @param WorkerOptions $options
     * @param int|float $startTime
     * @param JobInterface|null $currentJob
     * @param int $nbJobsExecuted
     *
     * @return WorkerExit|null
     */
    public function continue(
        WorkerOptions $options,
        int|float $startTime,
        ?JobInterface $currentJob,
        int $nbJobsExecuted,
    ): ?WorkerExit {
        return match (true) {
            // Should terminate?
            $this->shouldTerminate => WorkerExit::SHOULD_TERMINATE,
            // Kill file exists?
            $this->killFileExists($options) => WorkerExit::SHOULD_TERMINATE,
            // Memory limit exceeded?
            $this->memoryLimitExceeded($options) => WorkerExit::MEMORY_LIMIT,
            // Maximum time reached?
            $this->timeLimitExceeded($startTime, $options) => WorkerExit::TIME_EXCEEDED,
            // No job?
            $options->stopNoJob && null === $currentJob => WorkerExit::STOP_NO_JOB,
            // Limit exceeded?
            $nbJobsExecuted >= $options->limit => WorkerExit::LIMIT_EXCEEDED,
            // Default, continue
            default => null,
        };
    }

    /**
     * Run jobs in queues.
     *
     * @param QueueInterface $queue
     * @param WorkerOptions $options
     *
     * @return int
     * @throws QueueException
     */
    public function run(QueueInterface $queue, WorkerOptions $options = new WorkerOptions()): int
    {
        $startTime = $this->hrtime();
        $nbJobsExecuted = 0;

        $this->logger?->info(sprintf('Start worker on queue(s): %s', $queue->getName() ?? 'none'));
        $options->logOptions($this->logger);

        do {
            // Sleep between get new job
            $nbJobsExecuted > 0 && usleep((int)($options->sleep * 1000 * 1000));

            // Wait for rate limit
            if ($options->rateLimiter->reached()) {
                $this->logger?->debug(
                    'Rate limit reached, wait {time} seconds...',
                    [
                        'time' => round($options->rateLimiter->getWaitTime() / 1000 / 1000, 3),
                    ]
                );
                $options->rateLimiter->wait();
            }

            // Get a job to consume
            $job = $queue->consume();

            if (null === $job) {
                $this->logger?->debug('No job to consume');
                usleep((int)($options->sleepNoJob * 1000 * 1000));
                continue;
            }

            // Increment nb job to execute
            $options->rateLimiter->pop();
            $nbJobsExecuted++;

            $this->logger?->info(
                sprintf(
                    'Job %s consumed from queue %s',
                    $job->getId(),
                    $job->getQueue()->getName() ?? '--'
                ),
            );

            $execStartTime = $this->hrtime();
            try {
                // Exec
                $this->executeJob($job);

                // Delete job
                $job->delete();

                $this->logger?->info(
                    sprintf(
                        'Job %s executed from queue %s (%f ms)',
                        $job->getId(),
                        $job->getQueue()->getName() ?? '--',
                        ($this->hrtime() - $execStartTime) / 1000000,
                    ),
                );
            } catch (Throwable $exception) {
                // Release job
                $job->release($this->nextDelayAfterFailure($job, $options));

                $this->logger?->error(
                    sprintf(
                        'Job %s failed from queue %s (%f ms)',
                        $job->getId(),
                        $job->getQueue()->getName() ?? '--',
                        ($this->hrtime() - $execStartTime) / 1000000,
                    ),
                    ['exception' => $exception],
                );
                continue;
            }
        } while (null === ($exit = $this->continue($options, $startTime, $job, $nbJobsExecuted)));

        $this->logger?->info(sprintf('Exit(%d): %s', $exit->code(), $exit->reason()));

        return $exit->code();
    }

    /**
     * Execute job.
     *
     * @param JobInterface $job
     *
     * @return void
     * @throws QueueManagerException
     */
    public function executeJob(JobInterface $job): void
    {
        $this->jobHandlerManager->handle($job);
    }

    /**
     * Next delay after job failure.
     *
     * @param JobInterface $job
     * @param WorkerOptions $options
     *
     * @return int
     */
    public function nextDelayAfterFailure(JobInterface $job, WorkerOptions $options): int
    {
        if ($job->getAttempts() <= 1) {
            return $options->backoffTime;
        }

        return $options->backoffTime * pow(max($options->backoffMultiplier, 1), $job->getAttempts() - 1);
    }
}
