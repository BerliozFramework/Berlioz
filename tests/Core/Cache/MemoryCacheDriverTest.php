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

use Berlioz\Core\Cache\MemoryCacheDriver;
use Psr\SimpleCache\CacheInterface;

class MemoryCacheDriverTest extends AbstractCacheDriverTestCase
{
    protected static ?MemoryCacheDriver $cacheDriver = null;

    protected function getCacheDriver(): CacheInterface
    {
        if (null === self::$cacheDriver) {
            self::$cacheDriver = new MemoryCacheDriver();
        }

        return self::$cacheDriver;
    }

    public function testClear()
    {
        $this->getCacheDriver()->set('foo', 'bar');
        $this->assertTrue($this->getCacheDriver()->has('foo'));
        $this->assertTrue($this->getCacheDriver()->clear());
        $this->assertFalse($this->getCacheDriver()->has('foo'));
    }
}