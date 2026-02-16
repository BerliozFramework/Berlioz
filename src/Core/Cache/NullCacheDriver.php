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

declare(strict_types=1);

namespace Berlioz\Core\Cache;

use Berlioz\Core\Exception\InvalidArgumentCacheException;
use DateInterval;
use Psr\SimpleCache\CacheInterface;

/**
 * Class NullCacheDriver.
 */
class NullCacheDriver implements CacheInterface
{
    use KeyControlTrait;

    /**
     * @inheritDoc
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->controlKey($key);

        return $default;
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $this->controlKey($key);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key): bool
    {
        $this->controlKey($key);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function clear(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        if (!is_iterable($keys)) {
            throw new InvalidArgumentCacheException('First argument must be iterable');
        }

        $this->controlKeys((array)$keys);

        return array_fill_keys((array)$keys, $default);
    }

    /**
     * @inheritDoc
     */
    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        if (!is_iterable($values)) {
            throw new InvalidArgumentCacheException('First argument must be iterable');
        }

        $this->controlKeys(array_keys((array)$values));

        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteMultiple(iterable $keys): bool
    {
        if (!is_iterable($keys)) {
            throw new InvalidArgumentCacheException('First argument must be iterable');
        }

        $this->controlKeys((array)$keys);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        $this->controlKey($key);

        return false;
    }
}