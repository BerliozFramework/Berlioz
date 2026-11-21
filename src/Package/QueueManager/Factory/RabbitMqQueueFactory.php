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

use AMQPConnection;
use Berlioz\QueueManager\Queue\QueueInterface;
use Berlioz\QueueManager\Queue\RabbitMqQueue;

class RabbitMqQueueFactory extends AmqpQueueFactory
{
    use QueueFactoryTrait;

    /**
     * @inheritDoc
     */
    public static function getQueueClass(): string
    {
        return RabbitMqQueue::class;
    }

    /**
     * @inheritDoc
     */
    protected static function createQueue(AMQPConnection $connection, array $queue, array $config): QueueInterface
    {
        $management = static::getManagementOptions($connection, $config);

        return new RabbitMqQueue(
            connection: $connection,
            name: (string)($queue['name'] ?? 'default'),
            maxAttempts: (int)($queue['max_attempts'] ?? $config['max_attempts'] ?? 5),
            limiter: self::getRateLimiterFromConfig($queue['rate_limit'] ?? null),
            managementApiBaseUrl: $management['base_url'],
            managementApiUsername: $management['username'],
            managementApiPassword: $management['password'],
            vhost: $management['vhost'],
        );
    }

    /**
     * Get management API options.
     *
     * Credentials and vhost fall back to the effective connection ones
     * (explicit options, DSN or "amqp.*" INI settings).
     *
     * @param AMQPConnection $connection
     * @param array $config
     *
     * @return array{base_url: string|null, username: string, password: string, vhost: string}
     */
    protected static function getManagementOptions(AMQPConnection $connection, array $config): array
    {
        $management = (array)($config['management'] ?? []);

        return [
            'base_url' => isset($management['base_url']) ? (string)$management['base_url'] : null,
            'username' => (string)($management['username'] ?? $connection->getLogin()),
            'password' => (string)($management['password'] ?? $connection->getPassword()),
            'vhost' => $connection->getVhost(),
        ];
    }
}
