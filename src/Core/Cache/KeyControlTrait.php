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

/**
 * Trait KeyControlTrait.
 */
trait KeyControlTrait
{
    /**
     * Control key.
     *
     * @param $key
     *
     * @throws InvalidArgumentCacheException
     */
    protected function controlKey($key): void
    {
        if (!is_string($key)) {
            throw new InvalidArgumentCacheException('Invalid key name for cache, must be string');
        }

        if ('' === trim($key)) {
            throw new InvalidArgumentCacheException('Invalid key name for cache, must be not empty');
        }
    }

    /**
     * Control keys.
     *
     * @param array $keys
     *
     * @throws InvalidArgumentCacheException
     */
    protected function controlKeys(array $keys)
    {
        foreach ($keys as $key) {
            $this->controlKey($key);
        }
    }
}