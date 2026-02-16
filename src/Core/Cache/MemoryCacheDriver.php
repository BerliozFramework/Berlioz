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
use DateInterval;
use Psr\SimpleCache\CacheInterface;

/**
 * Class MemoryCacheDriver.
 */
class MemoryCacheDriver extends AbstractCacheDriver implements CacheInterface
{
    protected array $data = [];

    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        $this->controlKey($key);

        if (!array_key_exists($key, $this->data)) {
            return false;
        }

        return $this->isValidTtl($this->data[$key]['ttl']);
    }

    /**
     * @inheritDoc
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->controlKey($key);

        if ($this->has($key)) {
            return $this->data[$key]['value'];
        }

        return $default;
    }

    /**
     * @inheritDoc
     * @throws CacheException
     */
    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $this->controlKey($key);

        $this->data[$key] = [
            'ttl' => $this->makeTtl($ttl),
            'value' => $value,
        ];

        return true;
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key): bool
    {
        $this->controlKey($key);

        if (array_key_exists($key, $this->data)) {
            unset($this->data[$key]);

            return true;
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function clear(): bool
    {
        $this->data = [];

        return true;
    }
}