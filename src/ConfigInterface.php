<?php
/**
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2017 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Berlioz\Core;


interface ConfigInterface
{
    // Constants for directories
    const DIR_ROOT = 1;
    const DIR_CORE = 2;
    const DIR_VAR = 4;
    const DIR_VAR_CACHE = 16;
    const DIR_VAR_FILES = 32;
    const DIR_VAR_LOGS = 64;
    const DIR_VAR_TMP = 128;
    const DIR_VENDOR = 8;

    /**
     * Get value.
     *
     * Key given in parameter must be in format: key.key2.key3
     * Must return null value if key not found.
     *
     * @param string $key Key
     *
     * @return mixed
     */
    public function get(string $key = null);

    /**
     * Key exists ?
     *
     * Key given in parameter must be in format: key.key2.key3
     * Must return boolean value if key not found.
     *
     * @param string $key Key
     *
     * @return bool
     */
    public function has(string $key = null): bool;

    /**
     * Has debug mode enabled in configuration ?
     *
     * @return bool
     */
    public function hasDebugEnabled(): bool;

    /**
     * Has cache mode enabled in configuration ?
     *
     * @return bool
     */
    public function hasCacheEnabled(): bool;

    /**
     * Get log level defined in configuration.
     *
     * Must return constant value of \Psr\Log\LogLevel class.
     *
     * @return string
     */
    public function getLogLevel(): string;

    /**
     * Get a system directory.
     *
     * Specify a constant of Config class to get directory.
     * Use constants to ensure backward compatibility if constant values are changed.
     *
     * @param int $directory Specific directory (default: Config::DIR_ROOT)
     *
     * @return false|string
     */
    public function getDirectory($directory = Config::DIR_ROOT);
}