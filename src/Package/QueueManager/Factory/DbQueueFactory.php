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

use Berlioz\Core\Exception\ConfigException;
use Berlioz\QueueManager\Queue\DbQueue;
use Generator;
use Hector\Connection\Connection;

class DbQueueFactory implements QueueFactory
{
    use QueueFactoryTrait;

    /**
     * @inheritDoc
     */
    public static function getQueueClass(): string
    {
        return DbQueue::class;
    }

    /**
     * @inheritDoc
     */
    public static function createFromConfig(array $config): Generator
    {
        $connection = new Connection(
            dsn: $config['db']['dsn'] ?? throw new ConfigException('Missing "dsn" key on queue config'),
            username: $config['db']['username'] ?? null,
            password: $config['db']['password'] ?? null,
        );

        foreach ((array)($config['name'] ?? []) as $queue) {
            !is_array($queue) && $queue = ['name' => (string)$queue];

            yield new DbQueue(
                connection: $connection,
                name: $queue['name'] ?? 'default',
                tableName: $config['db']['table_name'] ?? 'queue_jobs',
                retryTime: (int)($queue['retry_time'] ?? $config['retry_time'] ?? 30),
                maxAttempts: (int)($queue['max_attempts'] ?? $config['max_attempts'] ?? 5),
                limiter: self::getRateLimiterFromConfig($queue['rate_limit'] ?? null),
            );
        }
    }
}
