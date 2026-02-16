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

use OutOfBoundsException;
use SplMinHeap;

readonly class TimeRateLimiter extends AbstractRateLimiter implements RateLimiterInterface
{
    private SplMinHeap $limits;

    /**
     * Create from string.
     *
     * @param string $str
     *
     * @return self
     */
    public static function createFromString(string $str): self
    {
        list($limit, $step) = RateLimitParser::parse($str);

        return new self($limit, $step);
    }

    public function __construct(
        private int $limit,
        private int $step = 1,
    ) {
        $this->limits = new SplMinHeap();
    }

    private function now(): float
    {
        return microtime(true);
    }

    private function clean(): void
    {
        $now = $this->now();

        while (!$this->limits->isEmpty() && ($now - $this->limits->top()) > $this->step) {
            $this->limits->extract();
        }
    }

    /**
     * @inheritDoc
     */
    public function getWaitTime(): int
    {
        if (false === $this->reached()) {
            return 0;
        }

        $now = $this->now();
        $waitTime = $this->step - ($now - $this->limits->top());

        return (int)($waitTime * 1000 * 1000);
    }

    /**
     * @inheritDoc
     */
    public function pop(): void
    {
        $this->clean();

        if ($this->limits->count() > $this->limit) {
            throw new OutOfBoundsException('Rate limit reached');
        }

        $this->limits->insert($this->now());
    }

    /**
     * @inheritDoc
     */
    public function reached(): bool
    {
        $this->clean();
        return $this->limits->count() >= $this->limit;
    }
}
