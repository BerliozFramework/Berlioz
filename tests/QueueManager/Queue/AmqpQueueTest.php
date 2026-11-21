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

use AMQPChannel;
use AMQPConnection;
use AMQPConnectionException;
use AMQPQueue as AmqpExtensionQueue;
use Berlioz\QueueManager\Job\JobDescriptor;
use Berlioz\QueueManager\Job\JobInterface;
use Berlioz\QueueManager\Queue\AmqpQueue;
use Berlioz\QueueManager\Queue\PurgeableQueueInterface;
use Berlioz\QueueManager\Queue\QueueInterface;
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

    // Not typed as AmqpQueue: a covariant return type would load the class (which throws without ext-amqp)
    // during inheritance checks, before #[RequiresPhpExtension] can skip the test.
    public static function newQueue(
        RateLimiterInterface $limiter = new NullRateLimiter(),
    ): QueueInterface&PurgeableQueueInterface {
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
            $this->assertSame(5, $job->getMaxAttempts());
            $this->assertSame(5 === $i, $job->isLastAttempt());
            $job->release();
        }

        $this->assertEquals(0, $queue->size());
    }

    public function testGetRetryTime(): void
    {
        $this->assertNull(static::newQueue()->getRetryTime());
    }

    private static function newConnection(): AMQPConnection
    {
        $connection = new AMQPConnection([
            'host' => '127.0.0.1',
            'port' => 5672,
            'login' => 'guest',
            'password' => 'guest',
            'vhost' => '/',
        ]);
        $connection->connect();

        return $connection;
    }

    private static function deadQueueSize(): int
    {
        $queue = new AmqpExtensionQueue(new AMQPChannel(self::$amqpConnection));
        $queue->setName('default:dead');
        $queue->setFlags(AMQP_PASSIVE);

        return $queue->declareQueue();
    }

    /**
     * Consume a job with another connection, and close it without delete nor release (simulates a worker crash).
     */
    private static function consumeAndCrash(): JobInterface
    {
        $connection = self::newConnection();
        $job = (new AmqpQueue(connection: $connection))->consume();
        $connection->disconnect();

        return $job;
    }

    public function testConsume_redeliveryCountsAsAttempt(): void
    {
        $queue = static::newQueue();
        $jobId = $queue->push(new JobDescriptor('test', ['foo' => 'value']));

        $this->assertSame(1, self::consumeAndCrash()->getAttempts());

        $job = $queue->consume();

        $this->assertSame($jobId, $job->getId());
        $this->assertSame(2, $job->getAttempts());
        $this->assertSame('value', $job->getPayload()->get('foo'));

        $job->delete();
        $this->assertNull($queue->consume());
    }

    public function testConsume_redeliveryReachesMaxAttempts(): void
    {
        $queue = static::newQueue();
        $queue->push(new JobDescriptor('test', ['foo' => 'value']));
        $deadQueueSize = self::deadQueueSize();

        for ($i = 1; $i <= 5; $i++) {
            $this->assertSame($i, self::consumeAndCrash()->getAttempts());
        }

        $this->assertNull($queue->consume());
        $this->assertSame(0, $queue->size());
        $this->assertSame($deadQueueSize + 1, self::deadQueueSize());
    }
}
