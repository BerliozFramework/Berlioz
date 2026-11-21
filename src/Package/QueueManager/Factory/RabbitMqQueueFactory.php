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
    protected static function createQueue(
        AMQPConnection $connection,
        array $queue,
        array $config,
        array $parameters,
    ): QueueInterface {
        $management = (array)($config['management'] ?? []);

        return new RabbitMqQueue(
            connection: $connection,
            name: (string)($queue['name'] ?? 'default'),
            maxAttempts: (int)($queue['max_attempts'] ?? $config['max_attempts'] ?? 5),
            limiter: self::getRateLimiterFromConfig($queue['rate_limit'] ?? null),
            managementApiBaseUrl: $management['base_url'] ?? null,
            managementApiUsername: $management['username'] ?? $parameters['login'] ?? null,
            managementApiPassword: $management['password'] ?? $parameters['password'] ?? null,
            vhost: (string)($parameters['vhost'] ?? '/'),
        );
    }
}
