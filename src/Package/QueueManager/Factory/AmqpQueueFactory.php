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
use Berlioz\QueueManager\Exception\QueueManagerException;
use Berlioz\QueueManager\Queue\AmqpQueue;
use Berlioz\QueueManager\Queue\QueueInterface;
use Generator;

class AmqpQueueFactory implements QueueFactory
{
    use QueueFactoryTrait;

    /**
     * @inheritDoc
     */
    public static function getQueueClass(): string
    {
        return AmqpQueue::class;
    }

    /**
     * @inheritDoc
     */
    public static function createFromConfig(array $config): Generator
    {
        extension_loaded('amqp') || throw QueueManagerException::missingPackage('ext-amqp');

        $parameters = static::getConnectionParameters($config);
        $connection = new AMQPConnection($parameters);
        $connection->connect();

        foreach ((array)($config['name'] ?? []) as $queue) {
            !is_array($queue) && $queue = ['name' => (string)$queue];

            yield static::createQueue($connection, $queue, $config, $parameters);
        }
    }

    /**
     * Create queue.
     *
     * Return type is not narrowed to avoid loading the queue class (which requires ext-amqp)
     * when the factory class is loaded.
     *
     * @param AMQPConnection $connection
     * @param array $queue Queue config
     * @param array $config Factory config
     * @param array $parameters Connection parameters
     *
     * @return QueueInterface
     */
    protected static function createQueue(
        AMQPConnection $connection,
        array $queue,
        array $config,
        array $parameters,
    ): QueueInterface {
        return new AmqpQueue(
            connection: $connection,
            name: (string)($queue['name'] ?? 'default'),
            maxAttempts: (int)($queue['max_attempts'] ?? $config['max_attempts'] ?? 5),
            limiter: self::getRateLimiterFromConfig($queue['rate_limit'] ?? null),
        );
    }

    /**
     * Get connection parameters from config.
     *
     * Parameters from the optional DSN are overridden by explicit keys,
     * other keys are passed through to AMQPConnection.
     *
     * @param array $config
     *
     * @return array
     * @throws ConfigException
     */
    protected static function getConnectionParameters(array $config): array
    {
        $amqpConfig = (array)($config['amqp'] ?? []);
        $parameters = [];

        if (null !== ($dsn = $amqpConfig['dsn'] ?? null)) {
            $parameters = self::parseDsn((string)$dsn);
        }
        unset($amqpConfig['dsn']);

        return array_replace($parameters, array_filter($amqpConfig, fn($value) => null !== $value));
    }

    /**
     * Parse DSN.
     *
     * @param string $dsn
     *
     * @return array
     * @throws ConfigException
     */
    private static function parseDsn(string $dsn): array
    {
        $parts = parse_url($dsn);

        if (false === $parts || false === in_array(strtolower($parts['scheme'] ?? ''), ['amqp', 'amqps'], true)) {
            throw new ConfigException('Invalid AMQP DSN, expected an "amqp://" or "amqps://" URI');
        }
        if (isset($parts['query']) || isset($parts['fragment'])) {
            throw new ConfigException('AMQP DSN does not support query parameters, use explicit options instead');
        }

        $vhost = substr($parts['path'] ?? '', 1);

        return array_filter(
            [
                'host' => $parts['host'] ?? null,
                'port' => $parts['port'] ?? ('amqps' === strtolower($parts['scheme']) ? 5671 : 5672),
                'vhost' => '' === $vhost ? '/' : rawurldecode($vhost),
                'login' => isset($parts['user']) ? rawurldecode($parts['user']) : null,
                'password' => isset($parts['pass']) ? rawurldecode($parts['pass']) : null,
            ],
            fn($value) => null !== $value,
        );
    }
}
