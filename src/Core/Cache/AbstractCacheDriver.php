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

use Berlioz\Core\Exception\CacheException;
use Berlioz\Core\Exception\InvalidArgumentCacheException;
use DateInterval;
use DateTime;
use DateTimeInterface;
use Exception;
use Psr\SimpleCache\CacheInterface;
use Traversable;

/**
 * Class AbstractCacheDriver.
 */
abstract class AbstractCacheDriver implements CacheInterface
{
    use KeyControlTrait;

    /**
     * @inheritDoc
     * @throws CacheException
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        if (!is_iterable($keys)) {
            throw new InvalidArgumentCacheException('First argument must be iterable');
        }

        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    /**
     * @inheritDoc
     * @throws CacheException
     */
    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        if (!is_iterable($values)) {
            throw new InvalidArgumentCacheException('First argument must be iterable');
        }

        if (!is_array($values)) {
            /** @var Traversable $values */
            $values = iterator_to_array($values, true);
        }

        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    /**
     * @inheritDoc
     * @throws CacheException
     */
    public function deleteMultiple(iterable $keys): bool
    {
        if (!is_iterable($keys)) {
            throw new InvalidArgumentCacheException('First argument must be iterable');
        }

        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    /**
     * Is valid TTL?
     *
     * @param mixed $ttl
     *
     * @return bool
     */
    protected function isValidTtl(mixed $ttl): bool
    {
        try {
            if (null === $ttl) {
                return true;
            }

            if ($ttl instanceof DateTime && $ttl > new DateTime('now')) {
                return true;
            }
        } catch (Exception) {
        }

        return false;
    }

    /**
     * Make ttl.
     *
     * @param int|null|DateInterval $ttl
     *
     * @return DateTimeInterface|null
     * @throws CacheException
     */
    protected function makeTtl(int|null|DateInterval $ttl): ?DateTimeInterface
    {
        try {
            if (null !== $ttl) {
                if (is_int($ttl)) {
                    $ttl = new DateInterval(sprintf('PT%dS', $ttl));
                }

                return (new DateTime('now'))->add($ttl);
            }
        } catch (Exception $e) {
            throw new CacheException('TTL cache exception', 0, $e);
        }

        return null;
    }
}