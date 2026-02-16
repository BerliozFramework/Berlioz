<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2025 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Berlioz\QueueManager\Tests\RateLimiter;

use Berlioz\QueueManager\RateLimiter\MultiRateLimiter;
use Berlioz\QueueManager\RateLimiter\TimeRateLimiter;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class MultiRateLimiterTest extends TestCase
{
    public function testCreateFromString(): void
    {
        $rateLimit = MultiRateLimiter::createFromString('10/min, 123/d', '20/h', '02 / secs');

        $reflectionMulti = new ReflectionClass(MultiRateLimiter::class);
        $reflectionTime = new ReflectionClass(TimeRateLimiter::class);
        $limiters = $reflectionMulti->getProperty('limiters')->getValue($rateLimit);

        $this->assertEquals(10, $reflectionTime->getProperty('limit')->getValue($limiters[0]));
        $this->assertEquals(60, $reflectionTime->getProperty('step')->getValue($limiters[0]));

        $this->assertEquals(123, $reflectionTime->getProperty('limit')->getValue($limiters[1]));
        $this->assertEquals(86400, $reflectionTime->getProperty('step')->getValue($limiters[1]));

        $this->assertEquals(20, $reflectionTime->getProperty('limit')->getValue($limiters[2]));
        $this->assertEquals(3600, $reflectionTime->getProperty('step')->getValue($limiters[2]));

        $this->assertEquals(2, $reflectionTime->getProperty('limit')->getValue($limiters[3]));
        $this->assertEquals(1, $reflectionTime->getProperty('step')->getValue($limiters[3]));
    }

    public function testReachedReturnsFalseWhenUnderLimit(): void
    {
        $rateLimit = new MultiRateLimiter(
            new TimeRateLimiter(3, 1),
            new TimeRateLimiter(3, 1),
        );
        $rateLimit->pop();
        $this->assertFalse($rateLimit->reached());
    }

    public function testReachedReturnsTrueWhenLimitReached(): void
    {
        $rateLimit = new MultiRateLimiter(
            new TimeRateLimiter(3, 1),
            new TimeRateLimiter(3, 1),
        );
        $rateLimit->pop();
        $rateLimit->pop();
        $rateLimit->pop();
        $this->assertTrue($rateLimit->reached());
    }

    public function testWaitDelaysCorrectly(): void
    {
        $rateLimit = new MultiRateLimiter(
            new TimeRateLimiter(3, 4),
            new TimeRateLimiter(2, 2),
        );

        $rateLimit->pop();
        $rateLimit->pop();
        $waitTime1 = $rateLimit->getWaitTime();
        $rateLimit->wait();
        $rateLimit->pop();
        $waitTime2 = $rateLimit->getWaitTime();

        $this->assertEquals(2, round($waitTime1 / 1000 / 1000));
        $this->assertEquals(2, round($waitTime2 / 1000 / 1000));
    }

    public function testWaitAndPopDelaysCorrectly(): void
    {
        $rateLimit = new MultiRateLimiter(
            new TimeRateLimiter(3, 4),
            new TimeRateLimiter(2, 2),
        );

        $rateLimit->pop();
        $rateLimit->pop();
        $waitTime1 = $rateLimit->getWaitTime();
        $rateLimit->waitAndPop();
        $waitTime2 = $rateLimit->getWaitTime();

        $this->assertEquals(2, round($waitTime1 / 1000 / 1000));
        $this->assertEquals(2, round($waitTime2 / 1000 / 1000));
    }
}
