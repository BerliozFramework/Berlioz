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

namespace Berlioz\QueueManager\Job;

use Berlioz\QueueManager\Exception\JobException;
use Berlioz\QueueManager\Queue\QueueInterface;

interface JobInterface extends JobDescriptorInterface
{
    /**
     * Get job identifier.
     *
     * @return string
     */
    public function getId(): string;

    /**
     * Get number of attempts.
     *
     * @return int
     */
    public function getAttempts(): int;

    /**
     * Release job.
     *
     * @param int $delay
     *
     * @return void
     * @throws JobException
     */
    public function release(int $delay = 0): void;

    /**
     * Is released?
     *
     * @return bool
     */
    public function isReleased(): bool;

    /**
     * Delete job.
     *
     * @return void
     * @throws JobException
     */
    public function delete(): void;

    /**
     * Is deleted?
     *
     * @return bool
     */
    public function isDeleted(): bool;

    /**
     * Get queue.
     *
     * @return QueueInterface
     */
    public function getQueue(): QueueInterface;
}
