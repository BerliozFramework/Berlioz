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

namespace Berlioz\QueueManager;

enum WorkerExit
{
    case SHOULD_TERMINATE;
    case STOP_NO_JOB;
    case MEMORY_LIMIT;
    case TIME_EXCEEDED;
    case LIMIT_EXCEEDED;

    /**
     * Exit code.
     *
     * @return int
     */
    public function code(): int
    {
        return match ($this) {
            default => 1,
        };
    }

    /**
     * Reason.
     *
     * @return string
     */
    public function reason(): string
    {
        return match ($this) {
            self::SHOULD_TERMINATE => 'Signal to terminate',
            self::STOP_NO_JOB => 'No more job to execute',
            self::MEMORY_LIMIT => 'Memory limit exceeded',
            self::TIME_EXCEEDED => 'Time limit exceeded',
            self::LIMIT_EXCEEDED => 'Number of jobs exceeded',

        };
    }
}
