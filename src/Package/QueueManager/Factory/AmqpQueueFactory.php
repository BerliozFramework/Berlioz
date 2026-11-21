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

        $connection = static::createConnection($config);

        foreach ((array)($config['name'] ?? []) as $queue) {
            !is_array($queue) && $queue = ['name' => (string)$queue];

            yield static::createQueue($connection, $queue, $config);
        }
    }

    /**
     * Create queue.
     *
     * Return type is not narrowed to avoid loading the queue class (which requires ext-amqp)
     * when the factory class is loaded.
     *
     * @param AMQPConnection $connection Connected connection
     * @param array $queue Queue config
     * @param array $config Factory config
     *
     * @return QueueInterface
     */
    protected static function createQueue(AMQPConnection $connection, array $queue, array $config): QueueInterface
    {
        return new AmqpQueue(
            connection: $connection,
            name: (string)($queue['name'] ?? 'default'),
            maxAttempts: (int)($queue['max_attempts'] ?? $config['max_attempts'] ?? 5),
            limiter: self::getRateLimiterFromConfig($queue['rate_limit'] ?? null),
        );
    }

    /**
     * Create connected connection.
     *
     * @param array $config
     *
     * @return AMQPConnection
     * @throws ConfigException
     */
    protected static function createConnection(array $config): AMQPConnection
    {
        $connection = new AMQPConnection(static::getConnectionParameters($config));

        if (true === static::requiresTls($config)) {
            static::assertTls($connection);
        }

        $connection->connect();

        return $connection;
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
     * Connection requires TLS?
     *
     * A secure DSN scheme ("amqps") requires TLS, whatever the explicit keys.
     *
     * @param array $config
     *
     * @return bool
     * @throws ConfigException
     */
    protected static function requiresTls(array $config): bool
    {
        if (null === ($dsn = $config['amqp']['dsn'] ?? null)) {
            return false;
        }

        return 'amqps' === self::getDsnScheme((string)$dsn);
    }

    /**
     * Assert that the connection will use verified TLS.
     *
     * Checks the effective connection settings (explicit options or "amqp.*" INI settings), before connecting:
     * ext-amqp only uses TLS when a CA certificate is given, and verifies peer and hostname with "verify".
     *
     * @param AMQPConnection $connection
     *
     * @throws ConfigException
     */
    protected static function assertTls(AMQPConnection $connection): void
    {
        if ('' === (string)$connection->getCACert()) {
            throw new ConfigException(
                'AMQPS connection requires a CA certificate to enable TLS, ' .
                'set the "cacert" option or the "amqp.cacert" INI setting'
            );
        }
        if (true !== $connection->getVerify()) {
            throw new ConfigException(
                'AMQPS connection requires the verification of the broker certificate and hostname, ' .
                'the "verify" option or the "amqp.verify" INI setting must not be disabled'
            );
        }
        if (('' === (string)$connection->getCert()) !== ('' === (string)$connection->getKey())) {
            throw new ConfigException('AMQPS client certificate requires both "cert" and "key" options');
        }
    }

    /**
     * Get DSN scheme.
     *
     * @param string $dsn
     *
     * @return string
     * @throws ConfigException
     */
    private static function getDsnScheme(string $dsn): string
    {
        $scheme = strtolower((string)parse_url($dsn, PHP_URL_SCHEME));

        if (false === in_array($scheme, ['amqp', 'amqps'], true)) {
            throw new ConfigException('Invalid AMQP DSN, expected an "amqp://" or "amqps://" URI');
        }

        return $scheme;
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
        $scheme = self::getDsnScheme($dsn);
        $parts = parse_url($dsn);

        if (false === $parts) {
            throw new ConfigException('Invalid AMQP DSN, expected an "amqp://" or "amqps://" URI');
        }
        if (isset($parts['query']) || isset($parts['fragment'])) {
            throw new ConfigException('AMQP DSN does not support query parameters, use explicit options instead');
        }

        $vhost = substr($parts['path'] ?? '', 1);

        return array_filter(
            [
                'host' => $parts['host'] ?? null,
                'port' => $parts['port'] ?? ('amqps' === $scheme ? 5671 : 5672),
                'vhost' => '' === $vhost ? '/' : rawurldecode($vhost),
                'login' => isset($parts['user']) ? rawurldecode($parts['user']) : null,
                'password' => isset($parts['pass']) ? rawurldecode($parts['pass']) : null,
            ],
            fn($value) => null !== $value,
        );
    }
}
