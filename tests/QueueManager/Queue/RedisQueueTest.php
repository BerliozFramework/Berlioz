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

use Berlioz\QueueManager\Queue\QueueInterface;
use Berlioz\QueueManager\Queue\RedisQueue;
use Berlioz\QueueManager\RateLimiter\NullRateLimiter;
use Berlioz\QueueManager\RateLimiter\RateLimiterInterface;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Redis;
use RedisException;

#[RequiresPhpExtension('redis')]
class RedisQueueTest extends QueueTestCase
{
    private static ?Redis $redis = null;

    public static function setUpBeforeClass(): void
    {
        try {
            self::$redis = new Redis();
            self::$redis->connect('127.0.0.1', 6379, 1);
        } catch (RedisException) {
            self::markTestSkipped('Redis is not available on 127.0.0.1:6379.');
        }
    }

    public static function tearDownAfterClass(): void
    {
        self::$redis->flushAll();
    }

    protected function setUp(): void
    {
        self::$redis->flushAll();
    }

    public static function newQueue(RateLimiterInterface $limiter = new NullRateLimiter()): QueueInterface
    {
        return new RedisQueue(
            redis: self::$redis,
            name: 'default',
            limiter: $limiter,
        );
    }

    public function testFreeDelayedJobs(): void
    {
        $redisMock = $this->createMock(Redis::class);
        $queue = new RedisQueue($redisMock, 'testQueue');
        $jobData = json_encode(['jobId' => '123', 'payload' => '{"key":"value"}', 'attempts' => 0]);

        $redisMock
            ->expects($this->once())
            ->method('set')
            ->with('testQueue:delayed:lock', '1', ['nx', 'ex' => 10])
            ->willReturn(true);

        $redisMock
            ->expects($this->once())
            ->method('zrangebyscore')
            ->with('testQueue:delayed', '-inf', (string)time())
            ->willReturn([$jobData]);

        $redisMock
            ->expects($this->once())
            ->method('zrem')
            ->with('testQueue:delayed', $jobData);

        $redisMock
            ->expects($this->once())
            ->method('rpush')
            ->with('testQueue', $jobData);

        $queue->freeDelayedJobs();
    }
}
