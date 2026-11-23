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
use Berlioz\Core\Exception\ConfigException;
use Berlioz\QueueManager\Exception\QueueException;
use Berlioz\QueueManager\Queue\QueueInterface;
use Berlioz\QueueManager\Queue\RabbitMqManagementApi;
use Berlioz\QueueManager\Queue\RabbitMqQueue;
use Generator;

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
    public static function createFromConfig(array $config): Generator
    {
        // Validated before connecting to the broker
        static::getManagementOptions($config);

        yield from parent::createFromConfig($config);
    }

    /**
     * @inheritDoc
     */
    protected static function createQueue(AMQPConnection $connection, array $queue, array $config): QueueInterface
    {
        $management = static::getManagementOptions($config);

        return new RabbitMqQueue(
            connection: $connection,
            name: (string)($queue['name'] ?? 'default'),
            maxAttempts: (int)($queue['max_attempts'] ?? $config['max_attempts'] ?? 5),
            limiter: self::getRateLimiterFromConfig($queue['rate_limit'] ?? null),
            managementApiBaseUrl: $management['base_url'],
            managementApiUsername: $management['username'],
            managementApiPassword: $management['password'],
            vhost: $connection->getVhost(),
            managementApiContext: $management['context'],
        );
    }

    /**
     * Get management API options.
     *
     * Credentials are never inherited from the broker connection: if a base URL is given,
     * explicit "username" and "password" options are required.
     *
     * @param array $config
     *
     * @return array{base_url: string|null, username: string|null, password: string|null, context: mixed}
     * @throws ConfigException
     */
    protected static function getManagementOptions(array $config): array
    {
        $management = (array)($config['management'] ?? []);
        $options = [
            'base_url' => isset($management['base_url']) ? (string)$management['base_url'] : null,
            'username' => isset($management['username']) ? (string)$management['username'] : null,
            'password' => isset($management['password']) ? (string)$management['password'] : null,
            'context' => $management['context'] ?? null,
        ];

        if (null === $options['base_url']) {
            return $options;
        }

        if (null === $options['username'] || '' === $options['username'] || null === $options['password']) {
            throw new ConfigException(
                'RabbitMQ management API requires explicit "management.username" and "management.password" ' .
                'options, broker credentials are never reused'
            );
        }

        try {
            new RabbitMqManagementApi(
                baseUrl: $options['base_url'],
                username: $options['username'],
                password: $options['password'],
                context: $options['context'],
            );
        } catch (QueueException $exception) {
            throw new ConfigException(sprintf('RabbitMQ %s', lcfirst($exception->getMessage())), previous: $exception);
        }

        return $options;
    }
}
