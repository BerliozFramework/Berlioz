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

use AMQPConnection;
use AMQPConnectionException;
use Berlioz\QueueManager\Exception\QueueException;
use Berlioz\QueueManager\Queue\RabbitMqQueue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Throwable;

#[RequiresPhpExtension('amqp')]
class RabbitMqQueueTest extends TestCase
{
    private const MANAGEMENT_URL = 'http://127.0.0.1:15672';

    public static function provideInvalidManagement(): array
    {
        return [
            'no credentials' => [self::MANAGEMENT_URL, null, null],
            'no password' => [self::MANAGEMENT_URL, 'guest', null],
            'no username' => [self::MANAGEMENT_URL, null, 'guest'],
            'file wrapper' => ['file:///etc/passwd', 'guest', 'guest'],
            'userinfo' => ['http://guest:guest@127.0.0.1:15672', 'guest', 'guest'],
        ];
    }

    #[DataProvider('provideInvalidManagement')]
    public function testConstruct_invalidManagementFailsClosed(
        string $baseUrl,
        ?string $username,
        ?string $password,
    ): void {
        $listener = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int)parse_url('tcp://' . stream_socket_get_name($listener, false), PHP_URL_PORT);
        $exception = null;

        try {
            new RabbitMqQueue(
                connection: new AMQPConnection(['host' => '127.0.0.1', 'port' => $port, 'connect_timeout' => 1]),
                name: 'rabbitmq-invalid-management',
                managementApiBaseUrl: $baseUrl,
                managementApiUsername: $username,
                managementApiPassword: $password,
            );
        } catch (Throwable $exception) {
        } finally {
            $accepted = @stream_socket_accept($listener, 0.5);
            fclose($listener);
        }

        $this->assertFalse($accepted, 'No connection must be opened');
        $this->assertInstanceOf(QueueException::class, $exception);
    }

    public function testMetrics_withManagementApi(): void
    {
        try {
            $connection = new AMQPConnection(['host' => '127.0.0.1', 'port' => 5672]);
            $connection->connect();
        } catch (AMQPConnectionException) {
            $this->markTestSkipped('RabbitMQ is not available on 127.0.0.1:5672.');
        }

        if (false === ($socket = @fsockopen('127.0.0.1', 15672, timeout: 1))) {
            $this->markTestSkipped('RabbitMQ management API is not available on 127.0.0.1:15672.');
        }
        fclose($socket);

        $queue = new RabbitMqQueue(
            connection: $connection,
            name: 'rabbitmq-management',
            managementApiBaseUrl: self::MANAGEMENT_URL,
            managementApiUsername: 'guest',
            managementApiPassword: 'guest',
        );
        $this->assertIsInt($queue->delayed());

        $queue = new RabbitMqQueue(
            connection: $connection,
            name: 'rabbitmq-management',
            managementApiBaseUrl: self::MANAGEMENT_URL,
            managementApiUsername: 'guest',
            managementApiPassword: 'wrong',
        );
        $this->assertNull($queue->delayed());
    }

    public function testMetrics_withoutManagementApi(): void
    {
        try {
            $connection = new AMQPConnection(['host' => '127.0.0.1', 'port' => 5672]);
            $connection->connect();
        } catch (AMQPConnectionException) {
            $this->markTestSkipped('RabbitMQ is not available on 127.0.0.1:5672.');
        }

        $queue = new RabbitMqQueue(connection: $connection, name: 'rabbitmq-no-management');

        $this->assertNull($queue->waitTime());
        $this->assertNull($queue->delayed());
    }
}
