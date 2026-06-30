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

use Berlioz\QueueManager\Job\RedisJob;
use Berlioz\QueueManager\Queue\MonitorableQueueInterface;
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
        $jobData = json_encode([
            'jobId' => '123',
            'payload' => '{"key":"value"}',
            'attempts' => 0,
            'createdAt' => time()
        ]);

        $lockValue = null;

        $redisMock
            ->expects($this->once())
            ->method('set')
            ->with(
                'testQueue:delayed:lock',
                $this->callback(function ($value) use (&$lockValue) {
                    $lockValue = $value;
                    // Must be a non-empty hex string (32 chars from 16 random bytes)
                    return is_string($value) && strlen($value) === 32 && ctype_xdigit($value);
                }),
                ['nx', 'ex' => 10],
            )
            ->willReturn(true);

        // The queue evaluates time() internally; allow a small window to avoid a
        // 1-second race between this assertion and the call under test.
        $before = time();

        $redisMock
            ->expects($this->once())
            ->method('zrangebyscore')
            ->with(
                'testQueue:delayed',
                '-inf',
                $this->callback(
                    fn($score): bool => is_string($score)
                        && ctype_digit($score)
                        && (int)$score >= $before
                        && (int)$score <= $before + 2,
                ),
            )
            ->willReturn([$jobData]);

        $redisMock
            ->expects($this->once())
            ->method('zrem')
            ->with('testQueue:delayed', $jobData);

        $redisMock
            ->expects($this->once())
            ->method('rpush')
            ->with('testQueue', $jobData);

        $redisMock
            ->method('get')
            ->with('testQueue:delayed:lock')
            ->willReturnCallback(function () use (&$lockValue) {
                return $lockValue;
            });

        $redisMock
            ->expects($this->once())
            ->method('del')
            ->with('testQueue:delayed:lock');

        $queue->freeDelayedJobs();
    }

    public function testFreeDelayedJobsDoesNotDeleteOtherProcessLock(): void
    {
        $redisMock = $this->createMock(Redis::class);
        $queue = new RedisQueue($redisMock, 'testQueue');

        $redisMock
            ->expects($this->once())
            ->method('set')
            ->willReturn(true);

        $redisMock
            ->method('zrangebyscore')
            ->willReturn([]);

        // Simulate lock owned by another process
        $redisMock
            ->method('get')
            ->with('testQueue:delayed:lock')
            ->willReturn('other-process-lock-value');

        // del should NOT be called since the lock value doesn't match
        $redisMock
            ->expects($this->never())
            ->method('del');

        $queue->freeDelayedJobs();
    }

    public function testDeleteSetsTtlOnDeletedJobsKey(): void
    {
        $redisMock = $this->createMock(Redis::class);
        $queue = new RedisQueue($redisMock, 'testQueue');

        $jobMock = $this->createMock(RedisJob::class);
        $jobMock->method('isReleased')->willReturn(false);
        $jobMock->method('isDeleted')->willReturn(false);
        $jobMock->method('getId')->willReturn('job-id-123');

        $redisMock
            ->expects($this->once())
            ->method('hset')
            ->with(
                'testQueue:deleted',
                'job-id-123',
                $this->callback(fn(string $payload) => $payload !== ''),
            );

        $redisMock
            ->expects($this->once())
            ->method('expire')
            ->with('testQueue:deleted', 86400);

        $queue->delete($jobMock);
    }

    public function testDeleteUsesCustomTtlOnDeletedJobsKey(): void
    {
        $redisMock = $this->createMock(Redis::class);
        $queue = new RedisQueue($redisMock, 'testQueue', new NullRateLimiter(), 3600);

        $jobMock = $this->createMock(RedisJob::class);
        $jobMock->method('isReleased')->willReturn(false);
        $jobMock->method('isDeleted')->willReturn(false);
        $jobMock->method('getId')->willReturn('job-id-456');

        $redisMock
            ->expects($this->once())
            ->method('hset')
            ->with(
                'testQueue:deleted',
                'job-id-456',
                $this->callback(fn(string $payload) => $payload !== ''),
            );

        $redisMock
            ->expects($this->once())
            ->method('expire')
            ->with('testQueue:deleted', 3600);

        $queue->delete($jobMock);
    }

    public function testWaitTimeReturnsAgeWhenAvailableAtIsPresent(): void
    {
        $redisMock = $this->createMock(Redis::class);
        $queue = new RedisQueue($redisMock, 'testQueue');
        $this->assertInstanceOf(MonitorableQueueInterface::class, $queue);
        $availableAt = time() - 5;

        $redisMock
            ->expects($this->once())
            ->method('set')
            ->willReturn(true);

        $redisMock
            ->expects($this->once())
            ->method('zrangebyscore')
            ->willReturn([]);

        $redisMock
            ->method('get')
            ->with('testQueue:delayed:lock')
            ->willReturnCallback(fn() => null);

        $redisMock
            ->expects($this->once())
            ->method('lindex')
            ->with('testQueue', 0)
            ->willReturn(json_encode([
                'jobId' => '123',
                'payload' => '{"key":"value"}',
                'attempts' => 0,
                'createdAt' => $availableAt - 10,
                'availableAt' => $availableAt,
            ]));

        $waitTime = $queue->waitTime();

        $this->assertNotNull($waitTime);
        $this->assertGreaterThanOrEqual(5, $waitTime);
    }

    public function testWaitTimeUsesCreatedAtFallbackWhenAvailableAtMissing(): void
    {
        $redisMock = $this->createMock(Redis::class);
        $queue = new RedisQueue($redisMock, 'testQueue');
        $createdAt = time() - 5;

        $redisMock
            ->expects($this->once())
            ->method('set')
            ->willReturn(true);

        $redisMock
            ->expects($this->once())
            ->method('zrangebyscore')
            ->willReturn([]);

        $redisMock
            ->method('get')
            ->with('testQueue:delayed:lock')
            ->willReturnCallback(fn() => null);

        $redisMock
            ->expects($this->once())
            ->method('lindex')
            ->with('testQueue', 0)
            ->willReturn(json_encode([
                'jobId' => '123',
                'payload' => '{"key":"value"}',
                'attempts' => 0,
                'createdAt' => $createdAt,
            ]));

        $this->assertGreaterThanOrEqual(5, $queue->waitTime());
    }

    public function testWaitTimeReturnsZeroForLegacyPayload(): void
    {
        $redisMock = $this->createMock(Redis::class);
        $queue = new RedisQueue($redisMock, 'testQueue');

        $redisMock
            ->expects($this->once())
            ->method('set')
            ->willReturn(true);

        $redisMock
            ->expects($this->once())
            ->method('zrangebyscore')
            ->willReturn([]);

        $redisMock
            ->method('get')
            ->with('testQueue:delayed:lock')
            ->willReturnCallback(fn() => null);

        $redisMock
            ->expects($this->once())
            ->method('lindex')
            ->with('testQueue', 0)
            ->willReturn(json_encode([
                'jobId' => '123',
                'payload' => '{"key":"value"}',
                'attempts' => 0,
            ]));

        $this->assertSame(0, $queue->waitTime());
    }

    public function testDelayedReturnsNumberOfDelayedJobs(): void
    {
        $redisMock = $this->createMock(Redis::class);
        $queue = new RedisQueue($redisMock, 'testQueue');

        $redisMock
            ->expects($this->once())
            ->method('set')
            ->willReturn(true);

        $redisMock
            ->expects($this->once())
            ->method('zrangebyscore')
            ->willReturn([]);

        $redisMock
            ->method('get')
            ->with('testQueue:delayed:lock')
            ->willReturnCallback(fn() => null);

        $redisMock
            ->expects($this->once())
            ->method('zcard')
            ->with('testQueue:delayed')
            ->willReturn(4);

        $this->assertSame(4, $queue->delayed());
    }
}
