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
use Berlioz\Package\QueueManager\Tests\Fake\FakeTlsCertificates;
use Berlioz\Package\QueueManager\Tests\Fake\FakeTlsServer;
use Berlioz\QueueManager\Queue\AmqpQueue;
use Berlioz\QueueManager\RateLimiter\MultiRateLimiter;
use Berlioz\QueueManager\RateLimiter\NullRateLimiter;
use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use Throwable;

class AmqpQueueFactoryTest extends TestCase
{
    public const DSN = 'amqp://guest:guest@127.0.0.1:5672/%2F';
    public const INI_SETTINGS = [
        'amqp.cacert',
        'amqp.cert',
        'amqp.key',
        'amqp.verify',
        'amqp.login',
        'amqp.password',
        'amqp.vhost',
    ];

    private static ?FakeTlsCertificates $certificates = null;
    private array $iniBackup = [];

    protected function setUp(): void
    {
        if (false === extension_loaded('amqp')) {
            return;
        }

        foreach (self::INI_SETTINGS as $setting) {
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

    public static function tearDownAfterClass(): void
    {
        self::$certificates = null;
    }

    public static function getCertificates(): FakeTlsCertificates
    {
        return self::$certificates ??= new FakeTlsCertificates();
    }

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

    /**
     * Assert that the factory throws a configuration exception without opening any connection.
     *
     * @param string $factoryClass
     * @param Closure(int $port): array $config Factory config, receiving the port of a plain TCP listener
     *
     * @return ConfigException
     */
    public static function assertFailsClosed(string $factoryClass, Closure $config): ConfigException
    {
        $listener = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int)parse_url('tcp://' . stream_socket_get_name($listener, false), PHP_URL_PORT);
        $config = $config($port);
        $config['amqp'] += ['connect_timeout' => 1, 'read_timeout' => 1];
        $exception = null;

        try {
            iterator_to_array($factoryClass::createFromConfig($config));
        } catch (Throwable $exception) {
        } finally {
            $accepted = @stream_socket_accept($listener, 0.5);
            fclose($listener);
        }

        self::assertFalse($accepted, 'No connection must be opened');
        self::assertInstanceOf(ConfigException::class, $exception);

        return $exception;
    }

    /**
     * Connect with the factory to a fake TLS server.
     *
     * @param string $host
     * @param array $amqpConfig
     *
     * @return array{string, string} Server report and client connection error
     */
    private function connectToFakeTlsServer(string $host, array $amqpConfig): array
    {
        $certificates = self::getCertificates();
        $server = new FakeTlsServer($certificates->serverCert, $certificates->serverKey);

        try {
            iterator_to_array(AmqpQueueFactory::createFromConfig([
                'amqp' => [
                    'dsn' => sprintf('amqps://guest:guest@%s:%d/%%2F', $host, $server->port),
                    'connect_timeout' => 2,
                    'read_timeout' => 2,
                    ...$amqpConfig,
                ],
                'name' => 'tls-queue',
            ]));
            $this->fail('The fake TLS server is not an AMQP broker, the connection must fail');
        } catch (AMQPConnectionException $exception) {
        }

        return [$server->getReport(), $exception->getMessage()];
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

    #[RequiresPhpExtension('amqp')]
    public function testCreateFromConfig_amqpsWithoutCaCertFailsClosed(): void
    {
        $exception = self::assertFailsClosed(AmqpQueueFactory::class, fn(int $port) => [
            'amqp' => ['dsn' => sprintf('amqps://guest:guest@127.0.0.1:%d/%%2F', $port)],
            'name' => 'tls-queue',
        ]);

        $this->assertStringContainsString('CA certificate', $exception->getMessage());
    }

    #[RequiresPhpExtension('amqp')]
    #[RequiresPhpExtension('openssl')]
    public function testCreateFromConfig_amqpsWithVerifyDisabledFailsClosed(): void
    {
        $exception = self::assertFailsClosed(AmqpQueueFactory::class, fn(int $port) => [
            'amqp' => [
                'dsn' => sprintf('amqps://guest:guest@127.0.0.1:%d/%%2F', $port),
                'cacert' => self::getCertificates()->caCert,
                'verify' => false,
            ],
            'name' => 'tls-queue',
        ]);

        $this->assertStringContainsString('verification', $exception->getMessage());
    }

    #[RequiresPhpExtension('amqp')]
    #[RequiresPhpExtension('openssl')]
    public function testCreateFromConfig_amqpsWithVerifyDisabledByIniFailsClosed(): void
    {
        ini_set('amqp.verify', '0');

        self::assertFailsClosed(AmqpQueueFactory::class, fn(int $port) => [
            'amqp' => [
                'dsn' => sprintf('amqps://guest:guest@127.0.0.1:%d/%%2F', $port),
                'cacert' => self::getCertificates()->caCert,
            ],
            'name' => 'tls-queue',
        ]);
    }

    #[RequiresPhpExtension('amqp')]
    #[RequiresPhpExtension('openssl')]
    public function testCreateFromConfig_amqpsWithCertWithoutKeyFailsClosed(): void
    {
        $exception = self::assertFailsClosed(AmqpQueueFactory::class, fn(int $port) => [
            'amqp' => [
                'dsn' => sprintf('amqps://guest:guest@127.0.0.1:%d/%%2F', $port),
                'cacert' => self::getCertificates()->caCert,
                'cert' => self::getCertificates()->serverCert,
            ],
            'name' => 'tls-queue',
        ]);

        $this->assertStringContainsString('"cert" and "key"', $exception->getMessage());
    }

    #[RequiresPhpExtension('amqp')]
    public function testCreateFromConfig_amqpsWithExplicitHostAndPortFailsClosed(): void
    {
        self::assertFailsClosed(AmqpQueueFactory::class, fn(int $port) => [
            'amqp' => [
                'dsn' => 'amqps://guest:guest@rabbitmq.invalid/%2F',
                'host' => '127.0.0.1',
                'port' => $port,
            ],
            'name' => 'tls-queue',
        ]);
    }

    #[RequiresPhpExtension('amqp')]
    #[RequiresPhpExtension('openssl')]
    public function testCreateFromConfig_amqpsWithTrustedCertificate(): void
    {
        [$report] = $this->connectToFakeTlsServer('localhost', ['cacert' => self::getCertificates()->caCert]);

        $this->assertSame(FakeTlsServer::REPORT_AMQP_OVER_TLS, $report);
    }

    #[RequiresPhpExtension('amqp')]
    #[RequiresPhpExtension('openssl')]
    public function testCreateFromConfig_amqpsWithCaCertFromIni(): void
    {
        ini_set('amqp.cacert', self::getCertificates()->caCert);

        [$report] = $this->connectToFakeTlsServer('localhost', []);

        $this->assertSame(FakeTlsServer::REPORT_AMQP_OVER_TLS, $report);
    }

    #[RequiresPhpExtension('amqp')]
    #[RequiresPhpExtension('openssl')]
    public function testCreateFromConfig_amqpsWithUntrustedCertificate(): void
    {
        [$report, $error] = $this->connectToFakeTlsServer(
            'localhost',
            ['cacert' => self::getCertificates()->untrustedCaCert],
        );

        $this->assertStringContainsString('SSL peer cert verification failed', $error);
        $this->assertContains($report, FakeTlsServer::REPORTS_WITHOUT_DATA);
    }

    #[RequiresPhpExtension('amqp')]
    #[RequiresPhpExtension('openssl')]
    public function testCreateFromConfig_amqpsWithHostnameMismatch(): void
    {
        [$report, $error] = $this->connectToFakeTlsServer('127.0.0.1', ['cacert' => self::getCertificates()->caCert]);

        $this->assertStringContainsString('SSL hostname verification failed', $error);
        $this->assertContains($report, FakeTlsServer::REPORTS_WITHOUT_DATA);
    }

    #[RequiresPhpExtension('amqp')]
    public function testCreateFromConfig_amqpsWithBroker(): void
    {
        $dsn = getenv('BERLIOZ_TEST_AMQPS_DSN');
        $caCert = getenv('BERLIOZ_TEST_AMQPS_CACERT');

        if (empty($dsn) || empty($caCert)) {
            $this->markTestSkipped('Set BERLIOZ_TEST_AMQPS_DSN and BERLIOZ_TEST_AMQPS_CACERT to test a TLS broker.');
        }

        $queues = iterator_to_array(AmqpQueueFactory::createFromConfig([
            'amqp' => ['dsn' => $dsn, 'cacert' => $caCert],
            'name' => 'factory-tls-queue',
        ]));

        $this->assertCount(1, $queues);
        $this->assertSame('factory-tls-queue', $queues[0]->getName());
    }
}
