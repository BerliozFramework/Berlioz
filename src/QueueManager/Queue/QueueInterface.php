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

use Berlioz\QueueManager\Exception\QueueException;
use Berlioz\QueueManager\Job\JobDescriptorInterface;
use Berlioz\QueueManager\Job\JobInterface;
use Berlioz\QueueManager\RateLimiter\RateLimiterInterface;
use DateInterval;
use DateTimeInterface;

interface QueueInterface
{
    /**
     * Get queue name.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get rate limiter.
     *
     * @return RateLimiterInterface
     */
    public function getRateLimiter(): RateLimiterInterface;

    /**
     * Number of jobs into queue.
     *
     * @return int
     * @throws QueueException
     */
    public function size(): int;

    /**
     * Consume the next job onto queue.
     *
     * @return JobInterface|null
     * @throws QueueException
     */
    public function consume(): ?JobInterface;

    /**
     * Push a new job.
     *
     * @param JobDescriptorInterface $jobDescriptor
     * @param DateTimeInterface|DateInterval|int $delay
     *
     * @return string
     * @throws QueueException
     */
    public function push(
        JobDescriptorInterface $jobDescriptor,
        DateTimeInterface|DateInterval|int $delay = 0,
    ): string;

    /**
     * Push raw.
     *
     * @param mixed $payload
     * @param DateTimeInterface|DateInterval|int $delay
     *
     * @return string
     * @throws QueueException
     */
    public function pushRaw(
        mixed $payload,
        DateTimeInterface|DateInterval|int $delay = 0,
    ): string;
}
