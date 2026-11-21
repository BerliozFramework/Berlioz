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
use Berlioz\Package\QueueManager\Factory\RabbitMqQueueFactory;
use Berlioz\QueueManager\Queue\AmqpQueue;
use Berlioz\QueueManager\Queue\RabbitMqQueue;
use Berlioz\QueueManager\RateLimiter\MultiRateLimiter;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

class RabbitMqQueueFactoryTest extends TestCase
{
    private const QUEUE_CLASS = RabbitMqQueue::class;

    private array $iniBackup = [];

    protected function setUp(): void
    {
        if (false === extension_loaded('amqp')) {
            return;
        }

        foreach (AmqpQueueFactoryTest::INI_SETTINGS as $setting) {
            $this->iniBackup[$setting] = ini_get($setting);
        }
        ini_set('amqp.cacert', '');
    }

    protected function tearDown(): void
    {
        foreach ($this->iniBackup as $setting => $value) {
            ini_set($setting, $value);
        }
    }

    private static function getProperty(object $queue, string $property): mixed
    {
        $class = 'maxAttempts' === $property ? AmqpQueue::class : self::QUEUE_CLASS;

        return (new ReflectionProperty($class, $property))->getValue($queue);
    }

    private static function getManagementOptions(array $config): array
    {
        $connection = new AMQPConnection(AmqpQueueFactoryTest::getConnectionParameters($config));

        return (new ReflectionMethod(RabbitMqQueueFactory::class, 'getManagementOptions'))
            ->invoke(null, $connection, $config);
    }

    public function testGetQueueClass(): void
    {
        $this->assertSame(self::QUEUE_CLASS, RabbitMqQueueFactory::getQueueClass());
    }

    #[RequiresPhpExtension('amqp')]
    public function testGetManagementOptions_fallbackOnIniSettings(): void
    {
        ini_set('amqp.login', 'ini-user');
        ini_set('amqp.password', 'ini-password');
        ini_set('amqp.vhost', 'ini-vhost');

        $options = self::getManagementOptions([
            'amqp' => ['host' => 'rabbitmq.invalid'],
            'management' => ['base_url' => 'http://rabbitmq.invalid:15672'],
        ]);

        $this->assertSame(
            [
                'base_url' => 'http://rabbitmq.invalid:15672',
                'username' => 'ini-user',
                'password' => 'ini-password',
                'vhost' => 'ini-vhost',
            ],
            $options,
        );
    }

    #[RequiresPhpExtension('amqp')]
    public function testGetManagementOptions_fallbackOnConnection(): void
    {
        $options = self::getManagementOptions([
            'amqp' => ['dsn' => 'amqp://user:pass@rabbitmq.invalid/my-vhost'],
        ]);

        $this->assertSame(
            ['base_url' => null, 'username' => 'user', 'password' => 'pass', 'vhost' => 'my-vhost'],
            $options,
        );
    }

    #[RequiresPhpExtension('amqp')]
    public function testGetManagementOptions_explicitCredentials(): void
    {
        ini_set('amqp.vhost', 'ini-vhost');

        $options = self::getManagementOptions([
            'amqp' => ['dsn' => 'amqp://user:pass@rabbitmq.invalid'],
            'management' => [
                'base_url' => 'http://rabbitmq.invalid:15672',
                'username' => 'admin',
                'password' => 'secret',
            ],
        ]);

        $this->assertSame(
            [
                'base_url' => 'http://rabbitmq.invalid:15672',
                'username' => 'admin',
                'password' => 'secret',
                'vhost' => '/',
            ],
            $options,
        );
    }

    #[RequiresPhpExtension('amqp')]
    public function testCreateFromConfig_amqpsWithoutCaCertFailsClosed(): void
    {
        AmqpQueueFactoryTest::assertFailsClosed(RabbitMqQueueFactory::class, fn(int $port) => [
            'amqp' => ['dsn' => sprintf('amqps://guest:guest@127.0.0.1:%d/%%2F', $port)],
            'management' => ['base_url' => 'http://127.0.0.1:15672'],
            'name' => 'tls-queue',
        ]);
    }

    #[RequiresPhpExtension('amqp')]
    public function testCreateFromConfig(): void
    {
        AmqpQueueFactoryTest::requireBroker($this);

        $queues = iterator_to_array(RabbitMqQueueFactory::createFromConfig([
            'amqp' => ['dsn' => AmqpQueueFactoryTest::DSN],
            'management' => [
                'base_url' => 'http://127.0.0.1:15672',
                'username' => 'admin',
                'password' => 'secret',
            ],
            'name' => [
                ['name' => 'factory-rabbitmq1', 'rate_limit' => '1/second', 'max_attempts' => 2],
                'factory-rabbitmq2',
            ],
        ]));

        $this->assertCount(2, $queues);
        $this->assertContainsOnlyInstancesOf(self::QUEUE_CLASS, $queues);

        $this->assertSame('factory-rabbitmq1', $queues[0]->getName());
        $this->assertInstanceOf(MultiRateLimiter::class, $queues[0]->getRateLimiter());
        $this->assertSame(2, self::getProperty($queues[0], 'maxAttempts'));
        $this->assertSame('http://127.0.0.1:15672', self::getProperty($queues[0], 'managementApiBaseUrl'));
        $this->assertSame('admin', self::getProperty($queues[0], 'managementApiUsername'));
        $this->assertSame('secret', self::getProperty($queues[0], 'managementApiPassword'));
        $this->assertSame('/', self::getProperty($queues[0], 'vhost'));

        $this->assertSame('factory-rabbitmq2', $queues[1]->getName());
        $this->assertSame(5, self::getProperty($queues[1], 'maxAttempts'));
    }

    #[RequiresPhpExtension('amqp')]
    public function testCreateFromConfig_omittedCredentials(): void
    {
        AmqpQueueFactoryTest::requireBroker($this);
        ini_set('amqp.login', 'guest');
        ini_set('amqp.password', 'guest');

        $queues = iterator_to_array(RabbitMqQueueFactory::createFromConfig([
            'amqp' => ['host' => '127.0.0.1', 'port' => 5672],
            'management' => ['base_url' => 'http://127.0.0.1:15672'],
            'name' => 'factory-rabbitmq-fallback',
        ]));

        $this->assertSame('guest', self::getProperty($queues[0], 'managementApiUsername'));
        $this->assertSame('guest', self::getProperty($queues[0], 'managementApiPassword'));
        $this->assertSame('/', self::getProperty($queues[0], 'vhost'));
    }
}
