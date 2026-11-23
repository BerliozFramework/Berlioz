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

use Berlioz\Core\Exception\ConfigException;
use Berlioz\Package\QueueManager\Factory\RabbitMqQueueFactory;
use Berlioz\QueueManager\Queue\AmqpQueue;
use Berlioz\QueueManager\Queue\RabbitMqManagementApi;
use Berlioz\QueueManager\Queue\RabbitMqQueue;
use Berlioz\QueueManager\RateLimiter\MultiRateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
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
        return (new ReflectionMethod(RabbitMqQueueFactory::class, 'getManagementOptions'))->invoke(null, $config);
    }

    public function testGetQueueClass(): void
    {
        $this->assertSame(self::QUEUE_CLASS, RabbitMqQueueFactory::getQueueClass());
    }

    public function testGetManagementOptions_withoutManagement(): void
    {
        $this->assertSame(
            ['base_url' => null, 'username' => null, 'password' => null, 'context' => null],
            self::getManagementOptions(['amqp' => ['dsn' => 'amqp://user:pass@rabbitmq.invalid/my-vhost']]),
        );
    }

    public function testGetManagementOptions_explicitCredentials(): void
    {
        $options = self::getManagementOptions([
            'amqp' => ['dsn' => 'amqp://user:pass@rabbitmq.invalid'],
            'management' => [
                'base_url' => 'https://rabbitmq.invalid:15671',
                'username' => 'monitoring',
                'password' => 'secret',
                'context' => ['ssl' => ['cafile' => '/path/to/ca.pem']],
            ],
        ]);

        $this->assertSame(
            [
                'base_url' => 'https://rabbitmq.invalid:15671',
                'username' => 'monitoring',
                'password' => 'secret',
                'context' => ['ssl' => ['cafile' => '/path/to/ca.pem']],
            ],
            $options,
        );
    }

    public function testGetManagementOptions_httpAllowed(): void
    {
        $options = self::getManagementOptions([
            'management' => [
                'base_url' => 'http://rabbitmq.namespace.svc:15672',
                'username' => 'monitoring',
                'password' => 'secret',
            ],
        ]);

        $this->assertSame('http://rabbitmq.namespace.svc:15672', $options['base_url']);
    }

    public static function provideMissingCredentials(): array
    {
        return [
            'none' => [[]],
            'username only' => [['username' => 'monitoring']],
            'password only' => [['password' => 'secret']],
            'empty username' => [['username' => '', 'password' => 'secret']],
        ];
    }

    #[DataProvider('provideMissingCredentials')]
    public function testGetManagementOptions_neverInheritsBrokerCredentials(array $credentials): void
    {
        ini_set('amqp.login', 'ini-user');
        ini_set('amqp.password', 'ini-password');

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('broker credentials are never reused');

        self::getManagementOptions([
            'amqp' => ['dsn' => 'amqp://user:pass@rabbitmq.invalid/my-vhost'],
            'management' => ['base_url' => 'https://rabbitmq.invalid:15671'] + $credentials,
        ]);
    }

    public static function provideInvalidBaseUrls(): array
    {
        return [
            'file wrapper' => ['file:///etc/passwd'],
            'php wrapper' => ['php://memory'],
            'ftp' => ['ftp://rabbitmq.invalid'],
            'no scheme' => ['rabbitmq.invalid:15672'],
            'no host' => ['https:///api'],
            'userinfo' => ['https://user:pass@rabbitmq.invalid'],
            'query' => ['https://rabbitmq.invalid/?foo=bar'],
            'fragment' => ['https://rabbitmq.invalid/#foo'],
        ];
    }

    #[DataProvider('provideInvalidBaseUrls')]
    public function testGetManagementOptions_invalidBaseUrl(string $baseUrl): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('RabbitMQ invalid management API configuration');

        self::getManagementOptions([
            'management' => ['base_url' => $baseUrl, 'username' => 'monitoring', 'password' => 'secret'],
        ]);
    }

    public function testGetManagementOptions_invalidContext(): void
    {
        $this->expectException(ConfigException::class);

        self::getManagementOptions([
            'management' => [
                'base_url' => 'https://rabbitmq.invalid',
                'username' => 'monitoring',
                'password' => 'secret',
                'context' => 'invalid',
            ],
        ]);
    }

    #[RequiresPhpExtension('amqp')]
    public function testCreateFromConfig_amqpsWithoutCaCertFailsClosed(): void
    {
        AmqpQueueFactoryTest::assertFailsClosed(RabbitMqQueueFactory::class, fn(int $port) => [
            'amqp' => ['dsn' => sprintf('amqps://guest:guest@127.0.0.1:%d/%%2F', $port)],
            'management' => ['base_url' => 'https://127.0.0.1:15671', 'username' => 'admin', 'password' => 'secret'],
            'name' => 'tls-queue',
        ]);
    }

    #[RequiresPhpExtension('amqp')]
    public function testCreateFromConfig_missingManagementCredentialsFailsClosed(): void
    {
        AmqpQueueFactoryTest::assertFailsClosed(RabbitMqQueueFactory::class, fn(int $port) => [
            'amqp' => ['dsn' => sprintf('amqp://guest:guest@127.0.0.1:%d/%%2F', $port)],
            'management' => ['base_url' => 'http://127.0.0.1:15672'],
            'name' => 'management-queue',
        ]);
    }

    #[RequiresPhpExtension('amqp')]
    public function testCreateFromConfig_invalidManagementBaseUrlFailsClosed(): void
    {
        AmqpQueueFactoryTest::assertFailsClosed(RabbitMqQueueFactory::class, fn(int $port) => [
            'amqp' => ['dsn' => sprintf('amqp://guest:guest@127.0.0.1:%d/%%2F', $port)],
            'management' => ['base_url' => 'file:///etc/passwd', 'username' => 'admin', 'password' => 'secret'],
            'name' => 'management-queue',
        ]);
    }

    #[RequiresPhpExtension('amqp')]
    public function testCreateFromConfig(): void
    {
        AmqpQueueFactoryTest::requireBroker($this);

        $queues = iterator_to_array(RabbitMqQueueFactory::createFromConfig([
            'amqp' => ['dsn' => AmqpQueueFactoryTest::DSN],
            'management' => [
                'base_url' => 'http://127.0.0.1:15672/',
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
        $this->assertInstanceOf(RabbitMqManagementApi::class, self::getProperty($queues[0], 'managementApi'));
        $this->assertSame(
            'http://127.0.0.1:15672',
            self::getProperty($queues[0], 'managementApi')->getBaseUrl(),
        );
        $this->assertSame('/', self::getProperty($queues[0], 'vhost'));

        $this->assertSame('factory-rabbitmq2', $queues[1]->getName());
        $this->assertSame(5, self::getProperty($queues[1], 'maxAttempts'));
    }

    #[RequiresPhpExtension('amqp')]
    public function testCreateFromConfig_withoutManagement(): void
    {
        AmqpQueueFactoryTest::requireBroker($this);

        $queues = iterator_to_array(RabbitMqQueueFactory::createFromConfig([
            'amqp' => ['dsn' => AmqpQueueFactoryTest::DSN],
            'name' => 'factory-rabbitmq-no-management',
        ]));

        $this->assertNull(self::getProperty($queues[0], 'managementApi'));
        $this->assertNull($queues[0]->waitTime());
        $this->assertNull($queues[0]->delayed());
    }
}
