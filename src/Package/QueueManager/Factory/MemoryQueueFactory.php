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

use Berlioz\QueueManager\Queue\MemoryQueue;
use Generator;

class MemoryQueueFactory implements QueueFactory
{
    use QueueFactoryTrait;

    /**
     * @inheritDoc
     */
    public static function getQueueClass(): string
    {
        return MemoryQueue::class;
    }

    /**
     * @inheritDoc
     */
    public static function createFromConfig(array $config): Generator
    {
        foreach ((array)($config['name'] ?? []) as $queue) {
            !is_array($queue) && $queue = ['name' => (string)$queue];

            yield new MemoryQueue(
                name: $queue['name'] ?? 'default',
                retryTime: (int)($queue['retry_time'] ?? $config['retry_time'] ?? 30),
                maxAttempts: (int)($queue['max_attempts'] ?? $config['max_attempts'] ?? 5),
                limiter: self::getRateLimiterFromConfig($queue['rate_limit'] ?? null),
            );
        }
    }
}
