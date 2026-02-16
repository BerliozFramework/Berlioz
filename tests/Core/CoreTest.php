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

namespace Berlioz\Core\Tests;

use Berlioz\Config\ConfigInterface;
use Berlioz\Core\Cache\CacheManager;
use Berlioz\Core\Cache\FileCacheDriver;
use Berlioz\Core\Cache\MemoryCacheDriver;
use Berlioz\Core\Composer\Composer;
use Berlioz\Core\Core;
use Berlioz\Core\Debug\DebugHandler;
use Berlioz\Core\Directories\DirectoriesInterface;
use Berlioz\Core\Exception\BerliozException;
use Berlioz\Core\Package\PackageSet;
use Berlioz\Core\Tests\Directories\FakeDefaultDirectories;
use Berlioz\ServiceContainer\Container;
use Locale;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\SimpleCache\CacheInterface;

class CoreTest extends TestCase
{
    use RestoresErrorHandler;

    public function provider()
    {
        $directories = new FakeDefaultDirectories();
        $core = new Core($directories, true);
        $core2 = new Core($directories, false);

        return [[$core, $directories], [$core2, $directories]];
    }

    public function test__construct_withoutCache()
    {
        $directories = new FakeDefaultDirectories();
        $core = new Core($directories, false);

        $this->assertInstanceOf(CacheManager::class, $core->getCache());
        $this->assertFalse($core->getCache()->getClass());
        $this->assertEquals($directories, $core->getDirectories());
    }

    public function test__construct_withCache()
    {
        $directories = new FakeDefaultDirectories();
        $core = new Core($directories, true);

        $this->assertInstanceOf(CacheManager::class, $core->getCache());
        $this->assertEquals(FileCacheDriver::class, $core->getCache()->getClass());
        $this->assertEquals($directories, $core->getDirectories());

        $core = new Core($directories);

        $this->assertInstanceOf(CacheManager::class, $core->getCache());
        $this->assertEquals(FileCacheDriver::class, $core->getCache()->getClass());
        $this->assertEquals($directories, $core->getDirectories());
    }

    public function test__construct_withSpecifiedCache()
    {
        $directories = new FakeDefaultDirectories();
        $cache = new MemoryCacheDriver();
        $cache->set('foo', 'bar');
        $core = new Core($directories, $cache);

        $this->assertInstanceOf(CacheManager::class, $core->getCache());
        $this->assertEquals(MemoryCacheDriver::class, $core->getCache()->getClass());
        $this->assertEquals('bar', $core->getCache()->get('foo'));
        $this->assertEquals($directories, $core->getDirectories());
    }

    public function testSerialize()
    {
        $core = new Core(new FakeDefaultDirectories());

        $this->expectException(BerliozException::class);
        serialize($core);
    }

    public function testGetCache()
    {
        $core = new Core(new FakeDefaultDirectories());

        $this->assertInstanceOf(CacheInterface::class, $core->getCache());
    }

    public function testGetDebug()
    {
        $core = new Core(new FakeDefaultDirectories());

        $this->assertInstanceOf(DebugHandler::class, $core->getDebug());
    }

    public function testGetComposer()
    {
        $core = new Core(new FakeDefaultDirectories());

        $this->assertInstanceOf(Composer::class, $core->getComposer());
    }

    public function testGetConfig()
    {
        $core = new Core(new FakeDefaultDirectories());

        $this->assertInstanceOf(ConfigInterface::class, $core->getConfig());
    }

    public function testGetContainer()
    {
        $core = new Core(new FakeDefaultDirectories());

        $this->assertInstanceOf(ContainerInterface::class, $core->getContainer());
        $this->assertInstanceOf(Container::class, $core->getContainer());
    }

    public function testGetLocale()
    {
        $core = new Core(new FakeDefaultDirectories());

        $this->assertEquals(Locale::getDefault(), $core->getLocale());
    }

    public function testGetPackage()
    {
        $core = new Core(new FakeDefaultDirectories());

        $this->assertInstanceOf(PackageSet::class, $core->getPackages());
    }

    public function testGetDirectories()
    {
        $directories = new FakeDefaultDirectories();
        $core = new Core($directories);

        $this->assertInstanceOf(DirectoriesInterface::class, $core->getDirectories());
        $this->assertSame($directories, $core->getDirectories());
    }
}
