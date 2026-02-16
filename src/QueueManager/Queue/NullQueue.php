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
use Berlioz\QueueManager\RateLimiter\NullRateLimiter;
use Berlioz\QueueManager\RateLimiter\RateLimiterInterface;
use DateInterval;
use DateTimeInterface;

readonly class NullQueue implements QueueInterface
{
    public function __construct(
        private string $name = 'NULL',
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritDoc
     */
    public function getRateLimiter(): RateLimiterInterface
    {
        return new NullRateLimiter();
    }

    /**
     * @inheritDoc
     */
    public function size(): int
    {
        return 0;
    }

    /**
     * @inheritDoc
     */
    public function consume(): ?JobInterface
    {
        throw new QueueException('No queue defined');
    }

    /**
     * @inheritDoc
     */
    public function push(JobDescriptorInterface $jobDescriptor, DateTimeInterface|DateInterval|int $delay = 0): string
    {
        throw new QueueException('No queue defined');
    }

    /**
     * @inheritDoc
     */
    public function pushRaw(mixed $payload, DateTimeInterface|DateInterval|int $delay = 0, int $attempts = 0): string
    {
        throw new QueueException('No queue defined');
    }
}
