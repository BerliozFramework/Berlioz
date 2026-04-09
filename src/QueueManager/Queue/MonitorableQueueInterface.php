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

namespace Berlioz\QueueManager\Queue;

use Berlioz\QueueManager\Exception\QueueException;

/**
 * Interface MonitorableQueueInterface.
 */
interface MonitorableQueueInterface
{
    /**
     * Get age in seconds of the oldest consumable job.
     *
     * @return int|null
     * @throws QueueException
     */
    public function waitTime(): ?int;

    /**
     * Get number of delayed jobs.
     *
     * @return int|null
     * @throws QueueException
     */
    public function delayed(): ?int;
}
