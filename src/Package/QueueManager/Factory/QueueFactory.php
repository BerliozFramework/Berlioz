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

namespace Berlioz\Package\QueueManager\Factory;

use Berlioz\QueueManager\Queue\QueueInterface;

interface QueueFactory
{
    /**
     * Get queue full class name of queue.
     *
     * @return string
     */
    public static function getQueueClass(): string;

    /**
     * Create queue from config.
     *
     * @param array $config
     *
     * @return QueueInterface[]
     */
    public static function createFromConfig(array $config): iterable;
}
