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

use Berlioz\QueueManager\Queue\MonitorableQueueInterface;

/**
 * Class FakeMonitorableQueue.
 *
 * Minimal monitorable queue returning fixed size, wait time and delayed count, for metrics tests.
 */
class FakeMonitorableQueue extends FakeQueue implements MonitorableQueueInterface
{
    public function __construct(
        string $name,
        int $size = 0,
        private readonly ?int $waitTime = null,
        private readonly ?int $delayed = null,
    ) {
        parent::__construct($name, $size);
    }

    public function waitTime(): ?int
    {
        return $this->waitTime;
    }

    public function delayed(): ?int
    {
        return $this->delayed;
    }
}
