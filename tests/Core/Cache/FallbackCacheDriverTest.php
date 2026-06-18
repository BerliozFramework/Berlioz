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

namespace Berlioz\Core\Tests\Cache;

use Berlioz\Core\Cache\FallbackCacheDriver;
use Berlioz\Core\Cache\MemoryCacheDriver;
use Berlioz\Core\Exception\CacheException;
use Berlioz\Core\Exception\InvalidArgumentCacheException;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

class FallbackCacheDriverTest extends TestCase
{
    public function testConstructWithoutDriverThrows()
    {
        $this->expectException(InvalidArgumentCacheException::class);

        new FallbackCacheDriver([]);
    }

    public function testConstructWithInvalidDriverThrows()
    {
        $this->expectException(InvalidArgumentCacheException::class);

        new FallbackCacheDriver([new \stdClass()]);
    }

    public function testGetFallsBackOnFailure()
    {
        $failing = $this->createMock(CacheInterface::class);
        $failing->method('get')->willThrowException(new CacheException('down'));

        $fallback = new MemoryCacheDriver();
        $fallback->set('foo', 'bar');

        $driver = new FallbackCacheDriver([$failing, $fallback]);

        $this->assertEquals('bar', $driver->get('foo'));
    }

    public function testGetDoesNotFallBackOnMiss()
    {
        $primary = $this->createMock(CacheInterface::class);
        $primary->expects($this->once())->method('get')->willReturn('default-from-primary');

        $secondary = $this->createMock(CacheInterface::class);
        $secondary->expects($this->never())->method('get');

        $driver = new FallbackCacheDriver([$primary, $secondary]);

        $this->assertEquals('default-from-primary', $driver->get('foo', 'default-from-primary'));
    }

    public function testPrimarySuccessSkipsSecondary()
    {
        $primary = $this->createMock(CacheInterface::class);
        $primary->expects($this->once())->method('set')->willReturn(true);

        $secondary = $this->createMock(CacheInterface::class);
        $secondary->expects($this->never())->method('set');

        $driver = new FallbackCacheDriver([$primary, $secondary]);

        $this->assertTrue($driver->set('foo', 'bar'));
    }

    public function testFailedDriverIsShortCircuited()
    {
        $failing = $this->createMock(CacheInterface::class);
        // Should only be called once, even across multiple operations.
        $failing->expects($this->once())->method('set')->willThrowException(new CacheException('down'));
        $failing->expects($this->never())->method('get');

        $fallback = new MemoryCacheDriver();

        $driver = new FallbackCacheDriver([$failing, $fallback]);

        $this->assertTrue($driver->set('foo', 'bar'));
        $this->assertEquals('bar', $driver->get('foo'));
    }

    public function testAllDriversFailReturnsDefaultOnRead()
    {
        $failing1 = $this->createMock(CacheInterface::class);
        $failing1->method('get')->willThrowException(new CacheException('down'));
        $failing2 = $this->createMock(CacheInterface::class);
        $failing2->method('get')->willThrowException(new CacheException('down'));

        $driver = new FallbackCacheDriver([$failing1, $failing2]);

        $this->assertEquals('default', $driver->get('foo', 'default'));
    }

    public function testAllDriversFailReturnsFalseOnWrite()
    {
        $failing1 = $this->createMock(CacheInterface::class);
        $failing1->method('set')->willThrowException(new CacheException('down'));
        $failing2 = $this->createMock(CacheInterface::class);
        $failing2->method('set')->willThrowException(new CacheException('down'));

        $driver = new FallbackCacheDriver([$failing1, $failing2]);

        $this->assertFalse($driver->set('foo', 'bar'));
    }

    public function testInvalidArgumentIsPropagated()
    {
        $primary = $this->createMock(CacheInterface::class);
        $primary->method('get')->willThrowException(new InvalidArgumentCacheException('bad key'));

        $secondary = $this->createMock(CacheInterface::class);
        $secondary->expects($this->never())->method('get');

        $driver = new FallbackCacheDriver([$primary, $secondary]);

        $this->expectException(InvalidArgumentCacheException::class);
        $driver->get('foo');
    }

    public function testGetMultipleFallsBack()
    {
        $failing = $this->createMock(CacheInterface::class);
        $failing->method('getMultiple')->willThrowException(new CacheException('down'));

        $fallback = new MemoryCacheDriver();
        $fallback->set('a', 1);
        $fallback->set('b', 2);

        $driver = new FallbackCacheDriver([$failing, $fallback]);

        $this->assertEquals(['a' => 1, 'b' => 2], $driver->getMultiple(['a', 'b']));
    }
}
