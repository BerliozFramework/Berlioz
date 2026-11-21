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

use Berlioz\Package\QueueManager\Factory\RabbitMqQueueFactory;
use Berlioz\QueueManager\Queue\AmqpQueue;
use Berlioz\QueueManager\Queue\RabbitMqQueue;
use Berlioz\QueueManager\RateLimiter\MultiRateLimiter;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class RabbitMqQueueFactoryTest extends TestCase
{
    private const QUEUE_CLASS = RabbitMqQueue::class;

    private static function getProperty(object $queue, string $property): mixed
    {
        $class = 'maxAttempts' === $property ? AmqpQueue::class : self::QUEUE_CLASS;

        return (new ReflectionProperty($class, $property))->getValue($queue);
    }

    public function testGetQueueClass(): void
    {
        $this->assertSame(self::QUEUE_CLASS, RabbitMqQueueFactory::getQueueClass());
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
    public function testCreateFromConfig_managementCredentialsFallback(): void
    {
        AmqpQueueFactoryTest::requireBroker($this);

        $queues = iterator_to_array(RabbitMqQueueFactory::createFromConfig([
            'amqp' => ['dsn' => AmqpQueueFactoryTest::DSN],
            'management' => ['base_url' => 'http://127.0.0.1:15672'],
            'name' => 'factory-rabbitmq-fallback',
        ]));

        $this->assertSame('guest', self::getProperty($queues[0], 'managementApiUsername'));
        $this->assertSame('guest', self::getProperty($queues[0], 'managementApiPassword'));
    }
}
