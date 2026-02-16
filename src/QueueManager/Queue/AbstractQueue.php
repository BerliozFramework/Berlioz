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

use Berlioz\QueueManager\RateLimiter\NullRateLimiter;
use Berlioz\QueueManager\RateLimiter\RateLimiterInterface;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Psr\Clock\ClockInterface;

abstract readonly class AbstractQueue implements QueueInterface, ClockInterface
{
    public function __construct(
        protected string $name = 'default',
        protected RateLimiterInterface $limiter = new NullRateLimiter(),
    ) {
    }

    /**
     * @inheritDoc
     */
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
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
        return $this->limiter;
    }

    /**
     * Get available date time.
     *
     * @param DateTimeInterface|DateInterval|int $delay
     *
     * @return DateTimeInterface
     */
    protected function getAvailableDateTime(DateTimeInterface|DateInterval|int $delay): DateTimeInterface
    {
        $now = $this->now();

        if ($delay instanceof DateTimeInterface) {
            if ($now->diff($delay)->invert === 1) {
                return $now;
            }

            return $delay;
        }

        if ($delay instanceof DateInterval) {
            if ($delay->invert === 1) {
                return $now;
            }

            return $now->add($delay);
        }

        if ($delay < 0) {
            return $now;
        }

        return $now->add(new DateInterval('PT' . $delay . 'S'));
    }

    /**
     * Get delay in seconds.
     *
     * @param DateTimeInterface $dateTime
     *
     * @return int
     */
    protected function getDelayInSeconds(DateTimeInterface $dateTime): int
    {
        return $dateTime->getTimestamp() - $this->now()->getTimestamp();
    }
}
