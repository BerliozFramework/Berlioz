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

use Berlioz\Package\QueueManager\Factory\RedisQueueFactory;
use Berlioz\QueueManager\Queue\RedisQueue;
use Berlioz\QueueManager\RateLimiter\MultiRateLimiter;
use Berlioz\QueueManager\RateLimiter\NullRateLimiter;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Redis;
use RedisException;
use ReflectionProperty;

class RedisQueueFactoryTest extends TestCase
{
    private const QUEUE_CLASS = RedisQueue::class;

    public function testGetQueueClass(): void
    {
        $this->assertSame(self::QUEUE_CLASS, RedisQueueFactory::getQueueClass());
    }

    #[RequiresPhpExtension('redis')]
    public function testCreateFromConfig(): void
    {
        try {
            (new Redis())->connect('127.0.0.1', 6379, 1);
        } catch (RedisException) {
            $this->markTestSkipped('Redis is not available on 127.0.0.1:6379.');
        }

        $queues = iterator_to_array(RedisQueueFactory::createFromConfig([
            'redis' => [
                'host' => '127.0.0.1',
                'port' => 6379,
                'timeout' => 1,
                'database' => 1,
            ],
            'name' => [
                ['name' => 'queue1', 'rate_limit' => '1/second'],
                'queue2',
                ['name' => 'queue3', 'deleted_jobs_ttl' => 60, 'max_attempts' => 1, 'retry_time' => 20],
            ],
            'deleted_jobs_ttl' => 3600,
            'max_attempts' => 3,
            'retry_time' => 10,
        ]));

        $redisProperty = new ReflectionProperty(self::QUEUE_CLASS, 'redis');
        $ttlProperty = new ReflectionProperty(self::QUEUE_CLASS, 'deletedJobsTtl');

        $this->assertCount(3, $queues);
        $this->assertContainsOnlyInstancesOf(self::QUEUE_CLASS, $queues);
        $this->assertSame($redisProperty->getValue($queues[0]), $redisProperty->getValue($queues[2]));
        $this->assertSame(1, $redisProperty->getValue($queues[0])->getDbNum());

        $this->assertSame('queue1', $queues[0]->getName());
        $this->assertInstanceOf(MultiRateLimiter::class, $queues[0]->getRateLimiter());
        $this->assertSame(3600, $ttlProperty->getValue($queues[0]));
        $this->assertSame(3, $queues[0]->getMaxAttempts());
        $this->assertSame(10, $queues[0]->getRetryTime());

        $this->assertSame('queue2', $queues[1]->getName());
        $this->assertInstanceOf(NullRateLimiter::class, $queues[1]->getRateLimiter());
        $this->assertSame(3600, $ttlProperty->getValue($queues[1]));

        $this->assertSame('queue3', $queues[2]->getName());
        $this->assertSame(60, $ttlProperty->getValue($queues[2]));
        $this->assertSame(1, $queues[2]->getMaxAttempts());
        $this->assertSame(20, $queues[2]->getRetryTime());
    }
}
