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

namespace Berlioz\Package\QueueManager\Factory;

use Berlioz\QueueManager\Queue\NullQueue;
use Generator;

class NullQueueFactory implements QueueFactory
{
    /**
     * @inheritDoc
     */
    public static function getQueueClass(): string
    {
        return NullQueue::class;
    }

    /**
     * @inheritDoc
     */
    public static function createFromConfig(array $config): Generator
    {
        foreach ((array)($config['name'] ?? []) as $queue) {
            !is_array($queue) && $queue = ['name' => (string)$queue];

            yield new NullQueue(name: (string)($queue['name'] ?? 'NULL'));
        }
    }
}
