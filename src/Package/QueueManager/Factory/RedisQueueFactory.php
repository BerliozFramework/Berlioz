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

use Berlioz\QueueManager\Exception\QueueManagerException;
use Berlioz\QueueManager\Queue\RedisQueue;
use Generator;
use Redis;

class RedisQueueFactory implements QueueFactory
{
    use QueueFactoryTrait;

    /**
     * @inheritDoc
     */
    public static function getQueueClass(): string
    {
        return RedisQueue::class;
    }

    /**
     * @inheritDoc
     */
    public static function createFromConfig(array $config): Generator
    {
        extension_loaded('redis') || throw QueueManagerException::missingPackage('ext-redis');

        $redisConfig = (array)($config['redis'] ?? []);
        $redis = new Redis();
        $redis->connect(
            (string)($redisConfig['host'] ?? '127.0.0.1'),
            (int)($redisConfig['port'] ?? 6379),
            (float)($redisConfig['timeout'] ?? 0),
        );

        if (null !== ($password = $redisConfig['password'] ?? null) && '' !== $password) {
            $redis->auth($password);
        }
        if (null !== ($database = $redisConfig['database'] ?? null)) {
            $redis->select((int)$database);
        }

        foreach ((array)($config['name'] ?? []) as $queue) {
            !is_array($queue) && $queue = ['name' => (string)$queue];

            yield new RedisQueue(
                redis: $redis,
                name: (string)($queue['name'] ?? 'default'),
                limiter: self::getRateLimiterFromConfig($queue['rate_limit'] ?? null),
                deletedJobsTtl: (int)($queue['deleted_jobs_ttl'] ?? $config['deleted_jobs_ttl'] ?? 86400),
                maxAttempts: (int)($queue['max_attempts'] ?? $config['max_attempts'] ?? 5),
                retryTime: (int)($queue['retry_time'] ?? $config['retry_time'] ?? 30),
            );
        }
    }
}
