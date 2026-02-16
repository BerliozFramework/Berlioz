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

namespace Berlioz\ServiceContainer\Service;

use Berlioz\ServiceContainer\Exception\ContainerException;
use DateInterval;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

class CacheStrategy
{
    const CACHE_PATTERN = 'CONTAINER_%s';

    public function __construct(
        protected CacheInterface $cache,
        protected DateInterval|int|null $ttl = null,
    ) {
    }

    protected function getCacheKey(Service $service): string
    {
        return sprintf(static::CACHE_PATTERN, $service->getAlias());
    }

    /**
     * Has cache for service?
     *
     * @param Service $service
     *
     * @return bool
     * @throws ContainerException
     */
    public function has(Service $service): bool
    {
        try {
            return $this->cache->has($this->getCacheKey($service));
        } catch (InvalidArgumentException $exception) {
            throw new ContainerException(
                message: sprintf('Error during cache read of service "%s"', $service->getAlias()),
                previous: $exception
            );
        }
    }

    /**
     * Get cache.
     *
     * @param Service $service
     *
     * @return object|null
     * @throws ContainerException
     */
    public function get(Service $service): ?object
    {
        try {
            if (!$this->cache->has($this->getCacheKey($service))) {
                return null;
            }

            $object = $this->cache->get($this->getCacheKey($service));

            if (!($service->isNullable() && null === $object)) {
                if (!is_a($object, $service->getClass())) {
                    throw new ContainerException(sprintf('Cache integrity of service "%s"', $service->getAlias()));
                }
            }

            return $object;
        } catch (InvalidArgumentException $exception) {
            throw new ContainerException(
                message: sprintf('Error during cache read of service "%s"', $service->getAlias()),
                previous: $exception
            );
        }
    }

    /**
     * Set cache.
     *
     * @param Service $service
     * @param object|null $object
     *
     * @return bool
     * @throws ContainerException
     */
    public function set(Service $service, ?object $object): bool
    {
        try {
            return $this->cache->set($this->getCacheKey($service), $object, $this->ttl);
        } catch (InvalidArgumentException $exception) {
            throw new ContainerException('Error during cache write', 0, $exception);
        }
    }
}
