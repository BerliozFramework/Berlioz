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

use Berlioz\Core\Exception\InvalidArgumentCacheException;
use Closure;
use DateInterval;
use Psr\SimpleCache\CacheInterface;
use Throwable;

/**
 * Class FallbackCacheDriver.
 *
 * Resilience decorator: chains several cache drivers ordered from the most
 * preferred to the fallback one. On a driver failure (e.g. Redis unreachable),
 * the operation is retried on the next driver. A cache miss is NOT a failure
 * and does not trigger any fallback. A driver that fails is short-circuited for
 * the lifetime of this instance.
 */
class FallbackCacheDriver implements CacheInterface
{
    /** @var CacheInterface[] */
    protected array $drivers;

    /** @var array<int, bool> */
    protected array $failed = [];

    /**
     * FallbackCacheDriver constructor.
     *
     * @param iterable<CacheInterface> $drivers Ordered from preferred to fallback.
     *
     * @throws InvalidArgumentCacheException
     */
    public function __construct(iterable $drivers)
    {
        $drivers = is_array($drivers) ? $drivers : iterator_to_array($drivers, false);

        if ([] === $drivers) {
            throw new InvalidArgumentCacheException('FallbackCacheDriver requires at least one cache driver');
        }

        foreach ($drivers as $driver) {
            if (!$driver instanceof CacheInterface) {
                throw new InvalidArgumentCacheException(
                    sprintf('All drivers must implement "%s"', CacheInterface::class)
                );
            }
        }

        $this->drivers = array_values($drivers);
    }

    /**
     * @inheritDoc
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attempt(fn(CacheInterface $driver): mixed => $driver->get($key, $default), $default);
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        return (bool)$this->attempt(fn(CacheInterface $driver): bool => $driver->set($key, $value, $ttl), false);
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key): bool
    {
        return (bool)$this->attempt(fn(CacheInterface $driver): bool => $driver->delete($key), false);
    }

    /**
     * @inheritDoc
     */
    public function clear(): bool
    {
        return (bool)$this->attempt(fn(CacheInterface $driver): bool => $driver->clear(), false);
    }

    /**
     * @inheritDoc
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $keys = is_array($keys) ? $keys : iterator_to_array($keys, false);

        return $this->attempt(
            fn(CacheInterface $driver): iterable => $driver->getMultiple($keys, $default),
            array_fill_keys($keys, $default),
        );
    }

    /**
     * @inheritDoc
     */
    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        $values = is_array($values) ? $values : iterator_to_array($values, true);

        return (bool)$this->attempt(
            fn(CacheInterface $driver): bool => $driver->setMultiple($values, $ttl),
            false,
        );
    }

    /**
     * @inheritDoc
     */
    public function deleteMultiple(iterable $keys): bool
    {
        $keys = is_array($keys) ? $keys : iterator_to_array($keys, false);

        return (bool)$this->attempt(fn(CacheInterface $driver): bool => $driver->deleteMultiple($keys), false);
    }

    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        return (bool)$this->attempt(fn(CacheInterface $driver): bool => $driver->has($key), false);
    }

    /**
     * Attempt an operation on the first healthy driver, falling back on failure.
     *
     * @param Closure $operation Receives a CacheInterface, returns the result.
     * @param mixed $onAllFail Value returned when every driver failed.
     *
     * @return mixed
     * @throws InvalidArgumentCacheException
     */
    protected function attempt(Closure $operation, mixed $onAllFail): mixed
    {
        foreach ($this->drivers as $index => $driver) {
            if ($this->failed[$index] ?? false) {
                continue;
            }

            try {
                return $operation($driver);
            } catch (InvalidArgumentCacheException $exception) {
                // Invalid argument is a caller error, not a driver failure: propagate.
                throw $exception;
            } catch (Throwable) {
                $this->failed[$index] = true;
            }
        }

        return $onAllFail;
    }
}
