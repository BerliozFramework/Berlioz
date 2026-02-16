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

namespace Berlioz\Package\QueueManager\Tests\Factory;

use Berlioz\Package\QueueManager\Factory\MemoryQueueFactory;
use Berlioz\QueueManager\Queue\MemoryQueue;
use Berlioz\QueueManager\RateLimiter\MultiRateLimiter;
use Berlioz\QueueManager\RateLimiter\NullRateLimiter;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class MemoryQueueFactoryTest extends TestCase
{
    public function testCreateFromConfig()
    {
        $queues = MemoryQueueFactory::createFromConfig([
            'name' => [
                [
                    'name' => 'queue1',
                    'rate_limit' => '1/second',
                ],
                'queue2',
                [
                    'name' => 'queue3',
                    'rate_limit' => '10/second',
                    'retry_time' => 20,
                    'max_attempts' => 1,
                ],
            ],
            'retry_time' => 10,
            'max_attempts' => 3,
        ]);
        $queues = iterator_to_array($queues);

        $retryTimeProperty = new ReflectionProperty(MemoryQueue::class, 'retryTime');
        $maxAttemptsProperty = new ReflectionProperty(MemoryQueue::class, 'maxAttempts');

        $this->assertEquals('queue1', $queues[0]->getName());
        $this->assertInstanceOf(MultiRateLimiter::class, $queues[0]->getRateLimiter());
        $this->assertEquals(10, $retryTimeProperty->getValue($queues[0]));
        $this->assertEquals(3, $maxAttemptsProperty->getValue($queues[0]));

        $this->assertEquals('queue2', $queues[1]->getName());
        $this->assertInstanceOf(NullRateLimiter::class, $queues[1]->getRateLimiter());
        $this->assertEquals(10, $retryTimeProperty->getValue($queues[1]));
        $this->assertEquals(3, $maxAttemptsProperty->getValue($queues[1]));

        $this->assertEquals('queue3', $queues[2]->getName());
        $this->assertInstanceOf(MultiRateLimiter::class, $queues[2]->getRateLimiter());
        $this->assertEquals(20, $retryTimeProperty->getValue($queues[2]));
        $this->assertEquals(1, $maxAttemptsProperty->getValue($queues[2]));
    }
}
