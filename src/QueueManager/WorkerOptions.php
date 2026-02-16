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

namespace Berlioz\QueueManager;

use Berlioz\QueueManager\RateLimiter\NullRateLimiter;
use Berlioz\QueueManager\RateLimiter\RateLimiterInterface;
use Psr\Log\LoggerInterface;

readonly class WorkerOptions
{
    public function __construct(
        public ?string $name = null,
        public int|float $limit = INF,
        public int|float $memoryLimit = INF,
        public int|float $timeLimit = INF,
        public ?string $killFilePath = null,
        public bool $stopNoJob = false,
        public int|float $sleep = 0,
        public int|float $sleepNoJob = 1,
        public int $backoffTime = 0,
        public int $backoffMultiplier = 1,
        public RateLimiterInterface $rateLimiter = new NullRateLimiter(),
    ) {
    }

    /**
     * Log options.
     *
     * @param LoggerInterface|null $logger
     *
     * @return void
     */
    public function logOptions(?LoggerInterface $logger): void
    {
        $logger?->debug(sprintf('Worker name: %s', $this->name ?? '--'));
        $logger?->debug(sprintf('Limit of jobs to execute: %s', $this->unit($this->limit)));
        $logger?->debug(sprintf('Memory limit: %s', $this->unit($this->memoryLimit, 'MB')));
        $logger?->debug(sprintf('Time limit: %s', $this->unit($this->timeLimit, 'second(s)')));
        $logger?->debug(sprintf('Kill file path: %s', $this->killFilePath ?? '--'));
        $logger?->debug(sprintf('Stop on no jobs: %s', $this->stopNoJob ? 'yes' : 'no'));
        $logger?->debug(sprintf('Sleep between job consumption: %s', $this->unit($this->sleep, 'second(s)')));
        $logger?->debug(sprintf('Sleep if no job: %s', $this->unit($this->sleepNoJob, 'second(s)')));
        $logger?->debug(sprintf('Backoff time: %s', $this->unit($this->backoffTime, 'second(s)')));
        $logger?->debug(sprintf('Backoff multiplier: %s', $this->unit($this->backoffMultiplier)));
    }

    private function unit(int|float $value, ?string $unit = null): string
    {
        if (null === $unit || $value === INF) {
            return sprintf('%s', $value);
        }

        return sprintf('%d %s', $value, $unit);
    }
}
