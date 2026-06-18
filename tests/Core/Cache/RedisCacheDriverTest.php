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

use Berlioz\Core\Cache\RedisCacheDriver;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Psr\SimpleCache\CacheInterface;
use Redis;

#[RequiresPhpExtension('redis')]
class RedisCacheDriverTest extends AbstractCacheDriverTestCase
{
    protected static ?RedisCacheDriver $cacheDriver = null;
    protected static ?Redis $redis = null;
    protected const NAMESPACE = 'berlioz-test';

    protected static function getRedis(): ?Redis
    {
        if (null !== self::$redis) {
            return self::$redis;
        }

        $host = getenv('REDIS_HOST') ?: '127.0.0.1';
        $port = (int)(getenv('REDIS_PORT') ?: 6379);

        try {
            $redis = new Redis();
            if (false === @$redis->connect($host, $port, 1.0)) {
                return null;
            }
            if (false === @$redis->ping()) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        return self::$redis = $redis;
    }

    public static function setUpBeforeClass(): void
    {
        // Start from a clean namespace; AbstractCacheDriverTestCase relies on
        // state persisting across its data-provided tests, so we only clean once.
        $redis = self::getRedis();

        if (null !== $redis) {
            (new RedisCacheDriver($redis, self::NAMESPACE))->clear();
        }
    }

    public static function tearDownAfterClass(): void
    {
        $redis = self::getRedis();

        if (null !== $redis) {
            (new RedisCacheDriver($redis, self::NAMESPACE))->clear();
        }

        self::$cacheDriver = null;
        self::$redis = null;
    }

    protected function getCacheDriver(): CacheInterface
    {
        $redis = self::getRedis();

        if (null === $redis) {
            $this->markTestSkipped('Redis server is not available');
        }

        if (null === self::$cacheDriver) {
            self::$cacheDriver = new RedisCacheDriver($redis, self::NAMESPACE);
        }

        return self::$cacheDriver;
    }

    public function testClearScopedToNamespace()
    {
        $redis = self::getRedis();

        if (null === $redis) {
            $this->markTestSkipped('Redis server is not available');
        }

        // Use a dedicated namespace so this test never disturbs the shared state
        // used by the inherited data-provided tests.
        $driver = new RedisCacheDriver($redis, 'berlioz-test-clear');

        // A key outside the namespace must survive clear().
        $redis->set('berlioz-other:keep', 'value');

        $driver->set('foo', 'bar');
        $this->assertTrue($driver->has('foo'));

        $this->assertTrue($driver->clear());
        $this->assertFalse($driver->has('foo'));

        // Out-of-namespace key still there.
        $this->assertEquals('value', $redis->get('berlioz-other:keep'));
        $redis->del('berlioz-other:keep');
    }

    public function testNoExpirationWhenTtlNull()
    {
        $redis = self::getRedis();

        if (null === $redis) {
            $this->markTestSkipped('Redis server is not available');
        }

        // Dedicated namespace to avoid interfering with shared state.
        $driver = new RedisCacheDriver($redis, 'berlioz-test-ttl');
        $driver->set('persistent', 'value', null);

        $this->assertEquals(-1, $redis->ttl('berlioz-test-ttl:persistent'));

        $driver->clear();
    }
}
