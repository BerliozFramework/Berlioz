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

use Berlioz\Core\Cache\FileCacheDriver;
use Berlioz\Core\Directories\DefaultDirectories;
use Berlioz\Core\Tests\Directories\FakeDefaultDirectories;
use Psr\SimpleCache\CacheInterface;

class FileCacheDriverTest extends AbstractCacheDriverTestCase
{
    protected static ?FileCacheDriver $cacheDriver = null;
    protected static DefaultDirectories $directories;

    protected function getCacheDriver(): CacheInterface
    {
        if (null === self::$cacheDriver) {
            self::$directories = new FakeDefaultDirectories();
            self::$cacheDriver = new FileCacheDriver(self::$directories);
        }

        return self::$cacheDriver;
    }

    public function testClear()
    {
        $this->getCacheDriver()->set('foo', 'bar');
        $cacheDirectory = self::$directories->getCacheDir() . DIRECTORY_SEPARATOR . FileCacheDriver::CACHE_DIRECTORY;

        $this->assertTrue(is_dir($cacheDirectory));
        $this->assertTrue($this->getCacheDriver()->clear());
        $this->assertFalse(is_dir($cacheDirectory));
    }

    public function testSetCreatesNonWorldWritableDirectory()
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('POSIX permissions do not apply on Windows');
        }

        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'berlioz-cache-perm-' . uniqid();
        $driver = new FileCacheDriver($path);

        $this->assertTrue($driver->set('foo', 'bar'));

        $cacheDirectory = $path . DIRECTORY_SEPARATOR . FileCacheDriver::CACHE_DIRECTORY;
        $this->assertTrue(is_dir($cacheDirectory));

        // Directory must not be world-writable (umask may further restrict the requested 0750).
        clearstatcache();
        $this->assertSame(0, (fileperms($cacheDirectory) & 0o002), 'Cache directory must not be world-writable');

        $driver->clear();
    }

    public function testConstructWithStringPath()
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'berlioz-cache-test-' . uniqid();
        $driver = new FileCacheDriver($path);

        $cacheDirectory = $path . DIRECTORY_SEPARATOR . FileCacheDriver::CACHE_DIRECTORY;

        $this->assertTrue($driver->set('foo', 'bar'));
        $this->assertTrue($driver->has('foo'));
        $this->assertEquals('bar', $driver->get('foo'));
        $this->assertTrue(is_dir($cacheDirectory));

        $this->assertTrue($driver->clear());
        $this->assertFalse($driver->has('foo'));
        $this->assertFalse(is_dir($cacheDirectory));
    }
}