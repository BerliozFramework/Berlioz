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

namespace Berlioz\Core\Package;

use Berlioz\Config\ConfigInterface;
use Berlioz\Config\Exception\ConfigException;
use Berlioz\Core\Composer\Composer;
use Berlioz\Core\Composer\Package;
use Berlioz\Core\Exception\BerliozException;
use Berlioz\Core\Exception\PackageException;

/**
 * Class PackageBuilder.
 */
class PackageBuilder
{
    private PackageSet $packages;

    public function __construct()
    {
        $this->reset();
    }

    /**
     * Reset builder.
     */
    public function reset(): void
    {
        $this->packages = new PackageSet();
    }

    /**
     * Add composer packages.
     *
     * @param Composer $composer
     *
     * @return static
     * @throws BerliozException
     */
    public function addComposerPackages(Composer $composer): static
    {
        /** @var Package $package */
        foreach ($composer->getBerliozPackages() as $package) {
            $packageClass = $package->getConfig('berlioz.package');

            if (empty($packageClass)) {
                throw PackageException::config($package->getName());
            }

            $packageClass = array_map(
                fn($v) => (string)$v,
                (array)$packageClass,
            );
            $this->packages->addPackage(...$packageClass);
        }

        return $this;
    }

    /**
     * Add configuration packages.
     *
     * @param ConfigInterface $config
     *
     * @return static
     * @throws ConfigException
     * @throws PackageException
     */
    public function addConfigPackages(ConfigInterface $config): static
    {
        $this->packages->addPackage(...(array)$config->get('packages', []));

        return $this;
    }

    /**
     * Get packages.
     *
     * @return PackageSet
     */
    public function getPackages(): PackageSet
    {
        return $this->packages;
    }
}
