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

namespace Berlioz\Core\Factory;

use Berlioz\Config\ConfigInterface;
use Berlioz\Core\Composer\Composer;
use Berlioz\Core\Exception\BerliozException;
use Berlioz\Core\Package\PackageSet;

/**
 * Interface CoreFactoryInterface.
 */
interface CoreFactoryInterface
{
    /**
     * Reset.
     */
    public function reset(): void;

    /**
     * Build.
     *
     * @throws BerliozException
     */
    public function build(): void;

    /**
     * Get composer.
     *
     * @return Composer
     */
    public function getComposer(): Composer;

    /**
     * Get config.
     *
     * @return ConfigInterface
     */
    public function getConfig(): ConfigInterface;

    /**
     * Get packages.
     *
     * @return PackageSet
     */
    public function getPackages(): PackageSet;
}