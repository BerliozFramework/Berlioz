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

use Berlioz\Core\Cache\CacheDriverFactory;
use Berlioz\Core\Cache\FallbackCacheDriver;
use Berlioz\Core\Cache\FileCacheDriver;
use Berlioz\Core\Cache\MemoryCacheDriver;
use Berlioz\Core\Cache\NullCacheDriver;
use Berlioz\Core\Exception\CacheException;
use PHPUnit\Framework\TestCase;

class CacheDriverFactoryTest extends TestCase
{
    public function testCreateMemory()
    {
        $this->assertInstanceOf(MemoryCacheDriver::class, CacheDriverFactory::create(['driver' => 'memory']));
    }

    public function testCreateNull()
    {
        $this->assertInstanceOf(NullCacheDriver::class, CacheDriverFactory::create(['driver' => 'null']));
    }

    public function testCreateDefaultsToNull()
    {
        $this->assertInstanceOf(NullCacheDriver::class, CacheDriverFactory::create([]));
    }

    public function testCreateFile()
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'berlioz-factory-test-' . uniqid();
        $driver = CacheDriverFactory::create(['driver' => 'file', 'path' => $path]);

        $this->assertInstanceOf(FileCacheDriver::class, $driver);
        $this->assertTrue($driver->set('foo', 'bar'));
        $this->assertEquals('bar', $driver->get('foo'));
        $driver->clear();
    }

    public function testCreateFileWithoutPathThrows()
    {
        $this->expectException(CacheException::class);

        CacheDriverFactory::create(['driver' => 'file']);
    }

    public function testCreateUnknownDriverThrows()
    {
        $this->expectException(CacheException::class);

        CacheDriverFactory::create(['driver' => 'unknown']);
    }

    public function testFromFile()
    {
        $cachePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'berlioz-factory-cachedir-' . uniqid();
        $configFile = tempnam(sys_get_temp_dir(), 'berlioz-cfg') . '.json';
        file_put_contents($configFile, json_encode(['driver' => 'file', 'path' => $cachePath]));

        $driver = CacheDriverFactory::fromFile($configFile);
        $this->assertInstanceOf(FileCacheDriver::class, $driver);

        unlink($configFile);
    }

    public function testFromFileMissingThrows()
    {
        $this->expectException(CacheException::class);

        CacheDriverFactory::fromFile(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'does-not-exist-' . uniqid() . '.json');
    }

    public function testFromFileInvalidJsonThrows()
    {
        $configFile = tempnam(sys_get_temp_dir(), 'berlioz-cfg') . '.json';
        file_put_contents($configFile, '{invalid json');

        try {
            $this->expectException(CacheException::class);
            CacheDriverFactory::fromFile($configFile);
        } finally {
            unlink($configFile);
        }
    }

    public function testFromEnv()
    {
        putenv('BERLIOZ_CACHE_DRIVER=memory');

        try {
            $driver = CacheDriverFactory::fromEnv();
            $this->assertInstanceOf(MemoryCacheDriver::class, $driver);
        } finally {
            putenv('BERLIOZ_CACHE_DRIVER');
        }
    }

    public function testAutoDefaultsToFileWrappedInFallback()
    {
        // No env defined: defaults to file driver, wrapped in a fallback.
        putenv('BERLIOZ_CACHE_FILE_PATH=' . sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'berlioz-auto-' . uniqid());

        try {
            $driver = CacheDriverFactory::auto();
            $this->assertInstanceOf(FallbackCacheDriver::class, $driver);
        } finally {
            putenv('BERLIOZ_CACHE_FILE_PATH');
        }
    }

    public function testAutoMemoryNotWrapped()
    {
        putenv('BERLIOZ_CACHE_DRIVER=memory');

        try {
            $driver = CacheDriverFactory::auto();
            $this->assertInstanceOf(MemoryCacheDriver::class, $driver);
        } finally {
            putenv('BERLIOZ_CACHE_DRIVER');
        }
    }
}
