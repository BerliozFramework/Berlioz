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
use JsonException;
use Psr\SimpleCache\CacheInterface;
use Redis;
use RedisException;

/**
 * Class CacheDriverFactory.
 *
 * Builds a PSR-16 cache driver from an array of options, a JSON file or
 * environment variables.
 */
final class CacheDriverFactory
{
    public const ENV_PREFIX = 'BERLIOZ_CACHE_';

    /**
     * Create a cache driver from an array of options.
     *
     * Supported drivers:
     *  - redis  (options: host, port, auth, database, timeout, namespace)
     *  - file   (options: path)
     *  - memory
     *  - null   (default)
     *
     * @param array $options
     *
     * @return CacheInterface
     * @throws CacheException
     */
    public static function create(array $options): CacheInterface
    {
        $driver = $options['driver'] ?? 'null';

        return match ($driver) {
            'redis' => self::createRedis($options),
            'file' => self::createFile($options),
            'memory' => new MemoryCacheDriver(),
            'null' => new NullCacheDriver(),
            default => throw new CacheException(sprintf('Unknown cache driver "%s"', (string)$driver)),
        };
    }

    /**
     * Create a cache driver from environment variables.
     *
     * Reads {prefix}DRIVER, {prefix}REDIS_HOST, {prefix}REDIS_PORT,
     * {prefix}REDIS_AUTH, {prefix}REDIS_DB, {prefix}REDIS_TIMEOUT,
     * {prefix}NAMESPACE and {prefix}FILE_PATH.
     *
     * @param string $prefix
     *
     * @return CacheInterface
     * @throws CacheException
     */
    public static function fromEnv(string $prefix = self::ENV_PREFIX): CacheInterface
    {
        return self::create(self::readEnv($prefix));
    }

    /**
     * Create a cache driver from a JSON file.
     *
     * The file must contain a JSON object matching the options of create().
     *
     * @param string $path
     *
     * @return CacheInterface
     * @throws CacheException
     */
    public static function fromFile(string $path): CacheInterface
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new CacheException(sprintf('Cache configuration file "%s" not found or not readable', $path));
        }

        if (false === ($contents = @file_get_contents($path))) {
            throw new CacheException(sprintf('Unable to read cache configuration file "%s"', $path));
        }

        try {
            $options = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new CacheException(
                sprintf('Invalid JSON in cache configuration file "%s"', $path),
                0,
                $exception
            );
        }

        if (!is_array($options)) {
            throw new CacheException(
                sprintf('Cache configuration file "%s" must contain a JSON object', $path)
            );
        }

        return self::create($options);
    }

    /**
     * Create a resilient cache driver with automatic detection.
     *
     * Uses environment variables when {prefix}* are defined. When a file path is
     * provided (without an explicit driver), the file cache is used. Otherwise it
     * defaults to the memory cache. Any persistent driver is wrapped in a
     * FallbackCacheDriver with a MemoryCacheDriver fallback, so the cache keeps
     * working if the main driver becomes unavailable.
     *
     * @param string $prefix
     *
     * @return CacheInterface
     * @throws CacheException
     */
    public static function auto(string $prefix = self::ENV_PREFIX): CacheInterface
    {
        $env = self::readEnv($prefix);

        if (!isset($env['driver'])) {
            $env['driver'] = isset($env['path']) ? 'file' : 'memory';
        }

        $primary = self::create($env);

        if ($primary instanceof MemoryCacheDriver) {
            return $primary;
        }

        return new FallbackCacheDriver([$primary, new MemoryCacheDriver()]);
    }

    /**
     * Create Redis driver.
     *
     * @param array $options
     *
     * @return RedisCacheDriver
     * @throws CacheException
     */
    private static function createRedis(array $options): RedisCacheDriver
    {
        if (!class_exists(Redis::class)) {
            throw new CacheException('Missing ext-redis package to use Redis cache driver');
        }

        $host = (string)($options['host'] ?? '127.0.0.1');
        $port = (int)($options['port'] ?? 6379);
        $timeout = (float)($options['timeout'] ?? 0.0);
        $namespace = (string)($options['namespace'] ?? 'berlioz');

        try {
            $redis = new Redis();

            if (false === @$redis->connect($host, $port, $timeout)) {
                throw new CacheException(sprintf('Unable to connect to Redis server "%s:%d"', $host, $port));
            }

            if (isset($options['auth']) && '' !== (string)$options['auth']) {
                $redis->auth((string)$options['auth']);
            }

            if (isset($options['database']) && null !== $options['database']) {
                $redis->select((int)$options['database']);
            }
        } catch (RedisException $exception) {
            throw new CacheException('Unable to initialize Redis connection', 0, $exception);
        }

        return new RedisCacheDriver($redis, $namespace);
    }

    /**
     * Create file driver.
     *
     * @param array $options
     *
     * @return FileCacheDriver
     * @throws CacheException
     */
    private static function createFile(array $options): FileCacheDriver
    {
        $path = $options['path'] ?? null;

        if (!is_string($path) || '' === $path) {
            throw new CacheException('Option "path" is required for the file cache driver');
        }

        return new FileCacheDriver($path);
    }

    /**
     * Read options from environment variables.
     *
     * @param string $prefix
     *
     * @return array
     */
    private static function readEnv(string $prefix): array
    {
        $options = [];

        if (false !== ($driver = getenv($prefix . 'DRIVER'))) {
            $options['driver'] = $driver;
        }
        if (false !== ($host = getenv($prefix . 'REDIS_HOST'))) {
            $options['host'] = $host;
        }
        if (false !== ($port = getenv($prefix . 'REDIS_PORT'))) {
            $options['port'] = (int)$port;
        }
        if (false !== ($auth = getenv($prefix . 'REDIS_AUTH'))) {
            $options['auth'] = $auth;
        }
        if (false !== ($db = getenv($prefix . 'REDIS_DB'))) {
            $options['database'] = (int)$db;
        }
        if (false !== ($timeout = getenv($prefix . 'REDIS_TIMEOUT'))) {
            $options['timeout'] = (float)$timeout;
        }
        if (false !== ($namespace = getenv($prefix . 'NAMESPACE'))) {
            $options['namespace'] = $namespace;
        }
        if (false !== ($filePath = getenv($prefix . 'FILE_PATH'))) {
            $options['path'] = $filePath;
        }

        return $options;
    }
}
