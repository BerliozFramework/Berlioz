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

interface RateLimiterInterface
{
    /**
     * Get wait time (in microseconds).
     *
     * @return int
     */
    public function getWaitTime(): int;

    /**
     * Wait.
     *
     * @return void
     */
    public function wait(): void;

    /**
     * Pop.
     *
     * @return void
     */
    public function pop(): void;

    /**
     * Wait and pop.
     *
     * @return void
     */
    public function waitAndPop(): void;

    /**
     * Rate reached?
     *
     * @return bool
     */
    public function reached(): bool;
}
