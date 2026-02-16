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

abstract readonly class AbstractRateLimiter implements RateLimiterInterface
{
    /**
     * @inheritDoc
     */
    public function wait(): void
    {
        $waitTime = $this->getWaitTime();
        if ($waitTime > 0) {
            usleep($waitTime);
        }
    }

    /**
     * @inheritDoc
     */
    public function waitAndPop(): void
    {
        if (!$this->reached()) {
            $this->pop();
            return;
        }

        $this->wait();
        $this->pop();
    }
}
