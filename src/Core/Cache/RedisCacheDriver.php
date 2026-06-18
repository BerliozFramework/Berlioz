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

declare(strict_types=1);

namespace Berlioz\Core\Cache;

use Berlioz\Core\Exception\CacheException;
use DateInterval;
use DateTime;
use Exception;
use Psr\SimpleCache\CacheInterface;
use Redis;
use RedisException;

class_exists(Redis::class) || throw new CacheException('Missing ext-redis package');

/**
 * Class RedisCacheDriver.
 */
class RedisCacheDriver extends AbstractCacheDriver implements CacheInterface
{
    /**
     * RedisCacheDriver constructor.
     *
     * @param Redis $redis
     * @param string $namespace
     */
    public function __construct(
        protected Redis $redis,
        protected string $namespace = 'berlioz',
    ) {
    }

    /**
     * @inheritDoc
     * @throws CacheException
     */
    public function has(string $key): bool
    {
        $this->controlKey($key);

        try {
            return (bool)$this->redis->exists($this->prefix($key));
        } catch (RedisException $exception) {
            throw new CacheException(sprintf('Unable to read key "%s" from Redis cache', $key), 0, $exception);
        }
    }

    /**
     * @inheritDoc
     * @throws CacheException
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->controlKey($key);

        try {
            $raw = $this->redis->get($this->prefix($key));
        } catch (RedisException $exception) {
            throw new CacheException(sprintf('Unable to read key "%s" from Redis cache', $key), 0, $exception);
        }

        if (false === $raw) {
            return $default;
        }

        $unserialized = @unserialize($raw);

        if (false === $unserialized && 'b:0;' !== $raw) {
            throw new CacheException(sprintf('Corrupted data for key "%s" from Redis cache', $key));
        }

        return $unserialized;
    }

    /**
     * @inheritDoc
     * @throws CacheException
     */
    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $this->controlKey($key);

        $milliseconds = $this->ttlToMilliseconds($ttl);

        // Negative or zero TTL means the value is already expired: delete it.
        if (null !== $milliseconds && $milliseconds <= 0) {
            return $this->delete($key);
        }

        try {
            $serialized = serialize($value);
        } catch (Exception $exception) {
            throw new CacheException(sprintf('Unable to serialize data to cache save "%s"', $key), 0, $exception);
        }

        try {
            if (null === $milliseconds) {
                return (bool)$this->redis->set($this->prefix($key), $serialized);
            }

            return (bool)$this->redis->pSetEx($this->prefix($key), $milliseconds, $serialized);
        } catch (RedisException $exception) {
            throw new CacheException(sprintf('Unable to save key "%s" to Redis cache', $key), 0, $exception);
        }
    }

    /**
     * @inheritDoc
     * @throws CacheException
     */
    public function delete(string $key): bool
    {
        $this->controlKey($key);

        try {
            $this->redis->del($this->prefix($key));
        } catch (RedisException $exception) {
            throw new CacheException(sprintf('Unable to delete key "%s" from Redis cache', $key), 0, $exception);
        }

        return true;
    }

    /**
     * @inheritDoc
     * @throws CacheException
     */
    public function clear(): bool
    {
        $pattern = $this->prefix('*');

        try {
            $iterator = null;

            do {
                $keys = $this->redis->scan($iterator, $pattern, 1000);

                if (false === $keys) {
                    continue;
                }

                if ([] !== $keys) {
                    $this->redis->del($keys);
                }
            } while ($iterator > 0);
        } catch (RedisException $exception) {
            throw new CacheException('Unable to clear Redis cache', 0, $exception);
        }

        return true;
    }

    /**
     * @inheritDoc
     * @throws CacheException
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $keys = is_array($keys) ? $keys : iterator_to_array($keys, false);
        $this->controlKeys($keys);

        if ([] === $keys) {
            return [];
        }

        $prefixedKeys = array_map($this->prefix(...), $keys);

        try {
            $values = $this->redis->mget($prefixedKeys);
        } catch (RedisException $exception) {
            throw new CacheException('Unable to read keys from Redis cache', 0, $exception);
        }

        $result = [];

        foreach ($keys as $index => $key) {
            $raw = $values[$index] ?? false;

            if (false === $raw) {
                $result[$key] = $default;
                continue;
            }

            $unserialized = @unserialize($raw);

            if (false === $unserialized && 'b:0;' !== $raw) {
                throw new CacheException(sprintf('Corrupted data for key "%s" from Redis cache', $key));
            }

            $result[$key] = $unserialized;
        }

        return $result;
    }

    /**
     * Prefix key with namespace.
     *
     * @param string $key
     *
     * @return string
     */
    protected function prefix(string $key): string
    {
        return $this->namespace . ':' . $key;
    }

    /**
     * Convert PSR-16 TTL to milliseconds.
     *
     * Milliseconds are used (via PSETEX) so the expiration matches the requested
     * TTL as closely as possible, instead of being rounded to the second.
     *
     * @param int|DateInterval|null $ttl
     *
     * @return int|null Null means no expiration.
     * @throws CacheException
     */
    protected function ttlToMilliseconds(int|DateInterval|null $ttl): ?int
    {
        if (null === $ttl) {
            return null;
        }

        if (is_int($ttl)) {
            return $ttl * 1000;
        }

        try {
            $now = new DateTime('now');
            $expiration = (new DateTime('now'))->add($ttl);

            return (int)round(($expiration->format('U.u') - $now->format('U.u')) * 1000);
        } catch (Exception $exception) {
            throw new CacheException('TTL cache exception', 0, $exception);
        }
    }
}
