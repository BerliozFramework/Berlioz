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

namespace Berlioz\Config;

use Berlioz\Config\Exception\ConfigException;

/**
 * Interface ConfigInterface.
 */
interface ConfigInterface
{
    /**
     * Get value.
     *
     * Key given in parameter must be in format: key.key2.key3
     * If key NULL given, full configuration given.
     *
     * @param string|null $key Key
     * @param mixed $default Default value to return if the key does not exist.
     *
     * @return mixed
     * @throws ConfigException
     */
    public function get(?string $key = null, mixed $default = null): mixed;

    /**
     * Key exists ?
     *
     * Key given in parameter must be in format: key.key2.key3
     * Must return boolean value if key not found.
     *
     * @param string $key Key
     *
     * @return bool
     * @throws ConfigException
     */
    public function has(string $key): bool;

    /**
     * Get an array copy of configuration.
     *
     * @return array
     * @throws ConfigException
     */
    public function getArrayCopy(): array;
}