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

use Berlioz\QueueManager\Job\JobDescriptor;
use Berlioz\QueueManager\Queue\MemoryQueue;
use Berlioz\QueueManager\Queue\MonitorableQueueInterface;
use Berlioz\QueueManager\Queue\QueueInterface;
use Berlioz\QueueManager\RateLimiter\NullRateLimiter;
use Berlioz\QueueManager\RateLimiter\RateLimiterInterface;

class MemoryQueueTest extends QueueTestCase
{
    public static function newQueue(RateLimiterInterface $limiter = new NullRateLimiter()): QueueInterface
    {
        return new MemoryQueue(
            name: 'default',
            limiter: $limiter,
        );
    }

    public function testMonitorableStats(): void
    {
        $queue = static::newQueue();
        $this->assertInstanceOf(MonitorableQueueInterface::class, $queue);

        $queue->push(new JobDescriptor('test', ['foo' => 'value']));
        $queue->push(new JobDescriptor('test', ['foo' => 'value']), 3);

        sleep(1);

        $this->assertSame(1, $queue->size());
        $this->assertNotNull($queue->waitTime());
        $this->assertGreaterThanOrEqual(1, $queue->waitTime());
        $this->assertSame(1, $queue->delayed());
    }

    public function testWaitTimeUsesAvailableTime(): void
    {
        /** @var MemoryQueue $queue */
        $queue = static::newQueue();

        $queue->push(new JobDescriptor('delayed', ['foo' => 'value']), 5);

        sleep(1);

        $queue->push(new JobDescriptor('ready', ['foo' => 'value']));

        sleep(1);

        $this->assertLessThanOrEqual(2, $queue->waitTime());
    }
}
