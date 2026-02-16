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

namespace Berlioz\QueueManager\RateLimiter;

readonly class MultiRateLimiter extends AbstractRateLimiter implements RateLimiterInterface
{
    private array $limiters;

    /**
     * Create from string.
     *
     * @param string $str
     * @param string ...$_str
     *
     * @return self
     */
    public static function createFromString(string $str, string ...$_str): self
    {
        $str = array_merge(
            ...array_map(
                fn($v) => explode(',', $v),
                [$str, ...$_str]
            )
        );

        return new self(...array_map(fn($v) => TimeRateLimiter::createFromString($v), $str));
    }

    public function __construct(RateLimiterInterface $limiter, RateLimiterInterface ...$_limiter)
    {
        $this->limiters = [$limiter, ...$_limiter];
    }

    /**
     * @inheritDoc
     */
    public function getWaitTime(): int
    {
        $waitTimes = array_map(
            fn(RateLimiterInterface $limit) => $limit->getWaitTime(),
            $this->limiters,
        );

        return max($waitTimes) ?: 0;
    }

    /**
     * @inheritDoc
     */
    public function pop(): void
    {
        array_map(
            fn(RateLimiterInterface $limit) => $limit->pop(),
            $this->limiters,
        );
    }

    /**
     * @inheritDoc
     */
    public function reached(): bool
    {
        $reached = array_filter(
            array_map(
                fn(RateLimiterInterface $limit) => $limit->reached(),
                $this->limiters,
            )
        );

        return count($reached) > 0;
    }
}
