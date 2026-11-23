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

namespace Berlioz\QueueManager\Tests\Queue;

use Berlioz\QueueManager\Exception\QueueException;
use Berlioz\QueueManager\Queue\RabbitMqManagementApi;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Process\Process;

class RabbitMqManagementApiTest extends TestCase
{
    private static Process $process;
    private static string $baseUrl;

    public static function setUpBeforeClass(): void
    {
        $listener = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($listener, false);
        fclose($listener);

        self::$baseUrl = 'http://' . $address;
        self::$process = new Process([PHP_BINARY, '-S', $address, __DIR__ . '/Fake/rabbitmq-management-server.php']);
        self::$process->disableOutput();
        self::$process->start();

        for ($i = 0; $i < 50; $i++) {
            if (false !== ($socket = @fsockopen('127.0.0.1', (int)parse_url(self::$baseUrl, PHP_URL_PORT)))) {
                fclose($socket);
                break;
            }
            usleep(100000);
        }
    }

    public static function tearDownAfterClass(): void
    {
        self::$process->stop();
    }

    private static function newApi(
        ?string $baseUrl = null,
        string $password = 'secret',
        mixed $context = null,
    ): RabbitMqManagementApi {
        return new RabbitMqManagementApi(
            baseUrl: $baseUrl ?? self::$baseUrl,
            username: 'monitoring',
            password: $password,
            context: $context,
        );
    }

    public function testGet(): void
    {
        $this->assertSame([['name' => 'default', 'messages' => 3]], self::newApi()->get('/api/queues/%2F'));
    }

    public function testGet_withPathPrefix(): void
    {
        $api = self::newApi(self::$baseUrl . '/prefix/');

        $this->assertSame(self::$baseUrl . '/prefix', $api->getBaseUrl());
        $this->assertSame([['name' => 'prefixed', 'messages' => 1]], $api->get('/api/queues/%2F'));
    }

    public function testGet_redirectNotFollowed(): void
    {
        $this->assertNull(self::newApi()->get('/redirect'));
    }

    public function testGet_redirectNotFollowedWithContextOverride(): void
    {
        $api = self::newApi(context: ['http' => ['follow_location' => 1, 'max_redirects' => 20, 'method' => 'POST']]);

        $this->assertNull($api->get('/redirect'));
        $this->assertSame([['name' => 'default', 'messages' => 3]], $api->get('/api/queues/%2F'));
    }

    public function testGet_unauthorized(): void
    {
        $this->assertNull(self::newApi(password: 'wrong')->get('/api/queues/%2F'));
    }

    public function testGet_serverError(): void
    {
        $this->assertNull(self::newApi()->get('/error'));
    }

    public function testGet_unreachable(): void
    {
        $listener = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($listener, false);
        fclose($listener);

        $this->assertNull(self::newApi('http://' . $address)->get('/api/queues/%2F'));
    }

    public static function provideInvalidBaseUrls(): array
    {
        return [
            'file wrapper' => ['file:///etc/passwd'],
            'php wrapper' => ['php://memory'],
            'data wrapper' => ['data://text/plain;base64,e30='],
            'ftp' => ['ftp://rabbitmq.invalid'],
            'no scheme' => ['rabbitmq.invalid'],
            'relative' => ['/api'],
            'no host' => ['http:///api'],
            'userinfo' => ['https://user:pass@rabbitmq.invalid'],
            'user' => ['https://user@rabbitmq.invalid'],
            'query' => ['https://rabbitmq.invalid/?foo=bar'],
            'fragment' => ['https://rabbitmq.invalid/#foo'],
        ];
    }

    #[DataProvider('provideInvalidBaseUrls')]
    public function testConstruct_invalidBaseUrl(string $baseUrl): void
    {
        $this->expectException(QueueException::class);

        self::newApi($baseUrl);
    }

    public function testConstruct_invalidContext(): void
    {
        $this->expectException(QueueException::class);

        self::newApi('https://rabbitmq.invalid', context: 'invalid');
    }

    public function testConstruct_contextOptions(): void
    {
        $original = stream_context_create(['ssl' => ['cafile' => '/path/to/ca.pem'], 'http' => ['timeout' => 5]]);
        $api = self::newApi('HTTPS://rabbitmq.invalid', context: $original);
        $options = stream_context_get_options((new ReflectionProperty($api, 'context'))->getValue($api));

        $this->assertSame('/path/to/ca.pem', $options['ssl']['cafile']);
        $this->assertTrue($options['ssl']['verify_peer']);
        $this->assertTrue($options['ssl']['verify_peer_name']);
        $this->assertSame(5, $options['http']['timeout']);
        $this->assertSame('GET', $options['http']['method']);
        $this->assertSame(0, $options['http']['follow_location']);
        $this->assertSame(1, $options['http']['max_redirects']);
        $this->assertSame(
            ['ssl' => ['cafile' => '/path/to/ca.pem'], 'http' => ['timeout' => 5]],
            stream_context_get_options($original),
        );
    }

    public function testDebugInfo_hidesPassword(): void
    {
        $dump = print_r(self::newApi('https://rabbitmq.invalid'), true);

        $this->assertStringNotContainsString('secret', $dump);
        $this->assertStringContainsString('*** HIDDEN ***', $dump);
    }
}
