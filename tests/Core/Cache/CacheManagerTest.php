<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2021 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Berlioz\Core\Tests\Cache;

use Berlioz\Core\Cache\CacheManager;
use Berlioz\Core\Cache\FileCacheDriver;
use Berlioz\Core\Cache\MemoryCacheDriver;
use Berlioz\Core\Tests\Directories\FakeDefaultDirectories;
use Psr\SimpleCache\CacheInterface;
use ReflectionObject;

class CacheManagerTest extends AbstractCacheDriverTestCase
{
    protected static ?CacheManager $cacheManager = null;

    protected function getCacheDriver(): CacheInterface
    {
        if (null === self::$cacheManager) {
            self::$cacheManager = new CacheManager(new MemoryCacheDriver(), new FakeDefaultDirectories());
        }

        return self::$cacheManager;
    }

    public function testConstruct_withCacheTrue()
    {
        $cacheManager = new CacheManager(true, new FakeDefaultDirectories());

        $this->assertEquals(FileCacheDriver::class, $cacheManager->getClass());
    }

    public function testConstruct_withCacheFalse()
    {
        $cacheManager = new CacheManager(false, new FakeDefaultDirectories());
        $reflectionObj = new ReflectionObject($cacheManager);
        $reflectionProperty = $reflectionObj->getProperty('cache');
        PHP_VERSION_ID < 80100 && $reflectionProperty->setAccessible(true);

        $this->assertEquals(false, $cacheManager->getClass());
        $this->assertInstanceOf(MemoryCacheDriver::class, $reflectionProperty->getValue($cacheManager));
    }

    public function testClear()
    {
        $this->getCacheDriver()->set('foo', 'bar');
        $this->assertTrue($this->getCacheDriver()->has('foo'));
        $this->assertTrue($this->getCacheDriver()->clear());
        $this->assertFalse($this->getCacheDriver()->has('foo'));
    }
}
