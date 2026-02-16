<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2024 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Berlioz\QueueManager\Tests\Queue;

use AMQPConnection;
use AMQPConnectionException;
use Berlioz\QueueManager\Job\JobDescriptor;
use Berlioz\QueueManager\Queue\AmqpQueue;
use Berlioz\QueueManager\RateLimiter\NullRateLimiter;
use Berlioz\QueueManager\RateLimiter\RateLimiterInterface;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

#[RequiresPhpExtension('amqp')]
class AmqpQueueTest extends QueueTestCase
{
    private static ?AMQPConnection $amqpConnection = null;

    public static function setUpBeforeClass(): void
    {
        try {
            self::$amqpConnection = new AMQPConnection([
                'host' => '127.0.0.1',
                'port' => 5672,
                'login' => 'guest',
                'password' => 'guest',
                'vhost' => '/',
            ]);
            self::$amqpConnection->connect();
        } catch (AMQPConnectionException) {
            self::markTestSkipped('RabbitMQ is not available on 127.0.0.1:5672.');
        }
    }

    public static function tearDownAfterClass(): void
    {
        static::newQueue()->purge();
    }

    protected function setUp(): void
    {
        static::newQueue()->purge();
    }

    public static function newQueue(RateLimiterInterface $limiter = new NullRateLimiter()): AmqpQueue
    {
        return new AmqpQueue(
            connection: self::$amqpConnection,
            limiter: $limiter,
        );
    }

    public function testMaxAttempts(): void
    {
        $queue = static::newQueue();
        $queue->push(new JobDescriptor('test', ['foo' => 'value']));

        for ($i = 1; $i <= 5; $i++) {
            $job = $queue->consume();
            $this->assertEquals($i, $job->getAttempts());
            $job->release();
        }

        $this->assertEquals(0, $queue->size());
    }
}
