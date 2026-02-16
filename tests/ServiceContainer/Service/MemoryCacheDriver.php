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

namespace Berlioz\ServiceContainer\Tests\Service;

use Psr\SimpleCache\CacheInterface;

/**
 * Class MemoryCacheDriver.
 *
 * @package Berlioz\Core\Cache
 */
class MemoryCacheDriver implements CacheInterface
{
    protected array $data = [];

    /**
     * @inheritDoc
     */
    public function has($key): bool
    {
        if (!array_key_exists($key, $this->data)) {
            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function get($key, $default = null): mixed
    {
        if ($this->has($key)) {
            return $this->data[$key]['value'];
        }

        return $default;
    }

    /**
     * @inheritDoc
     */
    public function set($key, $value, $ttl = null): bool
    {
        $this->data[$key] = [
            'ttl' => $ttl,
            'value' => $value,
        ];

        return true;
    }

    /**
     * @inheritDoc
     */
    public function delete($key): bool
    {
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

    /**
     * @inheritDoc
     */
    public function getMultiple($keys, $default = null): iterable
    {
        // TODO: Implement getMultiple() method.
    }

    /**
     * @inheritDoc
     */
    public function setMultiple($values, $ttl = null): bool
    {
        // TODO: Implement setMultiple() method.
    }

    /**
     * @inheritDoc
     */
    public function deleteMultiple($keys): bool
    {
        // TODO: Implement deleteMultiple() method.
    }
}