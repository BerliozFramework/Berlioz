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

namespace Berlioz\Package\QueueManager\Tests\Factory;

use AMQPConnection;
use AMQPConnectionException;
use Berlioz\Core\Exception\ConfigException;
use Berlioz\Package\QueueManager\Factory\AmqpQueueFactory;
use Berlioz\QueueManager\Queue\AmqpQueue;
use Berlioz\QueueManager\RateLimiter\MultiRateLimiter;
use Berlioz\QueueManager\RateLimiter\NullRateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

class AmqpQueueFactoryTest extends TestCase
{
    public const DSN = 'amqp://guest:guest@127.0.0.1:5672/%2F';

    public static function getConnectionParameters(array $config): array
    {
        return (new ReflectionMethod(AmqpQueueFactory::class, 'getConnectionParameters'))->invoke(null, $config);
    }

    public static function requireBroker(TestCase $test): void
    {
        try {
            (new AMQPConnection(self::getConnectionParameters(['amqp' => ['dsn' => self::DSN]])))->connect();
        } catch (AMQPConnectionException) {
            $test->markTestSkipped('RabbitMQ is not available on 127.0.0.1:5672.');
        }
    }

    public function testGetQueueClass(): void
    {
        $this->assertSame(AmqpQueue::class, AmqpQueueFactory::getQueueClass());
    }

    public static function provideConnectionParameters(): array
    {
        return [
            'empty' => [[], []],
            'dsn' => [
                ['dsn' => 'amqp://user:pass@rabbitmq:5673/my-vhost'],
                ['host' => 'rabbitmq', 'port' => 5673, 'vhost' => 'my-vhost', 'login' => 'user', 'password' => 'pass'],
            ],
            'dsn default port and vhost' => [
                ['dsn' => 'amqp://rabbitmq'],
                ['host' => 'rabbitmq', 'port' => 5672, 'vhost' => '/'],
            ],
            'dsn empty vhost' => [
                ['dsn' => 'amqp://rabbitmq/'],
                ['host' => 'rabbitmq', 'port' => 5672, 'vhost' => '/'],
            ],
            'dsn encoded' => [
                ['dsn' => 'amqp://us%40er:p%3Ass@rabbitmq/%2F'],
                ['host' => 'rabbitmq', 'port' => 5672, 'vhost' => '/', 'login' => 'us@er', 'password' => 'p:ss'],
            ],
            'dsn amqps' => [
                ['dsn' => 'AMQPS://rabbitmq/prod'],
                ['host' => 'rabbitmq', 'port' => 5671, 'vhost' => 'prod'],
            ],
            'explicit keys' => [
                ['host' => 'rabbitmq', 'port' => 5673, 'vhost' => 'foo', 'login' => 'user', 'password' => null],
                ['host' => 'rabbitmq', 'port' => 5673, 'vhost' => 'foo', 'login' => 'user'],
            ],
            'explicit keys override dsn' => [
                [
                    'dsn' => 'amqp://user:pass@rabbitmq:5673/my-vhost',
                    'vhost' => 'other',
                    'password' => 'secret',
                    'heartbeat' => 30,
                ],
                [
                    'host' => 'rabbitmq',
                    'port' => 5673,
                    'vhost' => 'other',
                    'login' => 'user',
                    'password' => 'secret',
                    'heartbeat' => 30,
                ],
            ],
        ];
    }

    #[DataProvider('provideConnectionParameters')]
    public function testGetConnectionParameters(array $amqpConfig, array $expected): void
    {
        $parameters = self::getConnectionParameters(['amqp' => $amqpConfig]);
        ksort($parameters);
        ksort($expected);

        $this->assertSame($expected, $parameters);
    }

    public static function provideInvalidDsn(): array
    {
        return [
            'invalid scheme' => ['redis://127.0.0.1'],
            'no scheme' => ['127.0.0.1'],
            'malformed' => ['amqp://:80'],
            'query' => ['amqp://127.0.0.1/?heartbeat=10'],
        ];
    }

    #[DataProvider('provideInvalidDsn')]
    public function testGetConnectionParameters_invalidDsn(string $dsn): void
    {
        $this->expectException(ConfigException::class);

        self::getConnectionParameters(['amqp' => ['dsn' => $dsn]]);
    }

    #[RequiresPhpExtension('amqp')]
    public function testCreateFromConfig(): void
    {
        self::requireBroker($this);

        $queues = iterator_to_array(AmqpQueueFactory::createFromConfig([
            'amqp' => ['dsn' => self::DSN],
            'name' => [
                ['name' => 'factory-queue1', 'rate_limit' => '1/second'],
                'factory-queue2',
                ['name' => 'factory-queue3', 'max_attempts' => 1],
            ],
            'max_attempts' => 3,
        ]));

        $maxAttemptsProperty = new ReflectionProperty(AmqpQueue::class, 'maxAttempts');

        $this->assertCount(3, $queues);
        $this->assertContainsOnlyInstancesOf(AmqpQueue::class, $queues);

        $this->assertSame('factory-queue1', $queues[0]->getName());
        $this->assertInstanceOf(MultiRateLimiter::class, $queues[0]->getRateLimiter());
        $this->assertSame(3, $maxAttemptsProperty->getValue($queues[0]));

        $this->assertSame('factory-queue2', $queues[1]->getName());
        $this->assertInstanceOf(NullRateLimiter::class, $queues[1]->getRateLimiter());
        $this->assertSame(3, $maxAttemptsProperty->getValue($queues[1]));

        $this->assertSame('factory-queue3', $queues[2]->getName());
        $this->assertSame(1, $maxAttemptsProperty->getValue($queues[2]));
    }
}
