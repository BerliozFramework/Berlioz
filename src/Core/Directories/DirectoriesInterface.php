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

namespace Berlioz\Core\Directories;

/**
 * Interface DirectoriesInterface.
 */
interface DirectoriesInterface
{
    /**
     * Get array copy.
     *
     * @return array
     */
    public function getArrayCopy(): array;

    /**
     * Get working directory.
     *
     * @return string
     */
    public function getWorkingDir(): string;

    /**
     * Get app directory.
     *
     * Find last composer.json file.
     *
     * @return string
     */
    public function getAppDir(): string;

    /**
     * Get config directory.
     *
     * @return string
     */
    public function getConfigDir(): string;

    /**
     * Get var directory.
     *
     * @return string
     */
    public function getVarDir(): string;

    /**
     * Get cache directory.
     *
     * @return string
     */
    public function getCacheDir(): string;

    /**
     * Get log directory.
     *
     * @return string
     */
    public function getLogDir(): string;

    /**
     * Get debug directory.
     *
     * @return string
     */
    public function getDebugDir(): string;

    /**
     * Get vendor directory.
     *
     * @return string
     */
    public function getVendorDir(): string;
}