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

use Berlioz\QueueManager\RateLimiter\TimeRateLimiter;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class TimeRateLimiterTest extends TestCase
{
    private function getLimitsCount(TimeRateLimiter $rateLimit): int
    {
        $reflection = new ReflectionClass($rateLimit);
        $limits = $reflection->getProperty('limits');

        return count($limits->getValue($rateLimit));
    }

    public function testCreateFromString(): void
    {
        $rateLimit = TimeRateLimiter::createFromString('10/min');

        $reflection = new ReflectionClass($rateLimit);

        $this->assertEquals(10, $reflection->getProperty('limit')->getValue($rateLimit));
        $this->assertEquals(60, $reflection->getProperty('step')->getValue($rateLimit));

    }

    public function testReachedReturnsFalseWhenUnderLimit(): void
    {
        $rateLimit = new TimeRateLimiter(3, 1);
        $rateLimit->pop();
        $this->assertFalse($rateLimit->reached());
    }

    public function testReachedReturnsTrueWhenLimitReached(): void
    {
        $rateLimit = new TimeRateLimiter(3, 1);
        $rateLimit->pop();
        $rateLimit->pop();
        $rateLimit->pop();
        $this->assertTrue($rateLimit->reached());
    }

    public function testCleanRemovesOldEntries(): void
    {
        $rateLimit = new TimeRateLimiter(3, 1);
        $rateLimit->pop();
        sleep(2);
        $rateLimit->pop();
        $this->assertEquals(1, $this->getLimitsCount($rateLimit));
    }

    public function testWaitDelaysCorrectly(): void
    {
        $rateLimit = new TimeRateLimiter(2, $step = 5);
        $rateLimit->pop();
        sleep($sleep = 2);
        $start = microtime(true);
        $rateLimit->pop();
        $waitTime = $rateLimit->getWaitTime();
        $rateLimit->wait();
        $end = microtime(true);

        $this->assertEquals($step - $sleep, round($end - $start));
        $this->assertEquals($step - $sleep, round($waitTime / 1000 / 1000));
    }

    public function testWaitAndPopDelaysCorrectly(): void
    {
        $rateLimit = new TimeRateLimiter(2, $step = 5);
        $rateLimit->pop();
        sleep($sleep = 2);
        $start = microtime(true);
        $rateLimit->pop();
        $waitTime = $rateLimit->getWaitTime();
        $rateLimit->waitAndPop();
        $end = microtime(true);

        $this->assertEquals($step - $sleep, round($end - $start));
        $this->assertEquals($step - $sleep, round($waitTime / 1000 / 1000));
    }
}
