<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2026 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\Package\QueueManager\Tests\Fake;

use Berlioz\QueueManager\Job\JobDescriptorInterface;
use Berlioz\QueueManager\Job\JobInterface;
use Berlioz\QueueManager\Queue\QueueInterface;
use Berlioz\QueueManager\RateLimiter\NullRateLimiter;
use Berlioz\QueueManager\RateLimiter\RateLimiterInterface;
use DateInterval;
use DateTimeInterface;

/**
 * Class FakeQueue.
 *
 * Minimal non-monitorable queue returning a fixed size, for metrics tests.
 */
class FakeQueue implements QueueInterface
{
    public function __construct(
        private readonly string $name,
        private readonly int $size = 0,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRateLimiter(): RateLimiterInterface
    {
        return new NullRateLimiter();
    }

    public function size(): int
    {
        return $this->size;
    }

    public function consume(): ?JobInterface
    {
        return null;
    }

    public function push(JobDescriptorInterface $jobDescriptor, DateTimeInterface|DateInterval|int $delay = 0): string
    {
        return '';
    }

    public function pushRaw(mixed $payload, DateTimeInterface|DateInterval|int $delay = 0): string
    {
        return '';
    }
}
