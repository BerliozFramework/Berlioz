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

use Berlioz\Config\Config;
use Berlioz\Config\ConfigInterface;
use Berlioz\Core\Core;
use Berlioz\Core\Exception\PackageException;
use Berlioz\ServiceContainer\Container;
use Throwable;

/**
 * Class PackageSet.
 */
class PackageSet
{
    private array $packages = [];

    /**
     * PackageSet constructor.
     *
     * @param array $packages
     *
     * @throws PackageException
     */
    public function __construct(array $packages = [])
    {
        $this->addPackage(...$packages);
    }

    ////////////////////
    /// SERIALIZABLE ///
    ////////////////////

    /**
     * PHP serialize function.
     *
     * @return array
     */
    public function __serialize(): array
    {
        return [
            'packages' => $this->packages,
        ];
    }

    /**
     * PHP unserialize function.
     *
     * @param array $data
     */
    public function __unserialize(array $data): void
    {
        $this->packages = $data['packages'] ?? [];
    }

    //////////////////////////////
    /// PACKAGES MANIPULATIONS ///
    //////////////////////////////

    /**
     * Get packages classes.
     *
     * @return array
     */
    public function getPackages(): array
    {
        return $this->packages;
    }

    /**
     * Add a package.
     *
     * @param string ...$package
     *
     * @throws PackageException
     */
    public function addPackage(string ...$package): void
    {
        array_walk(
            $package,
            function ($class) {
                if (!is_a($class, PackageInterface::class, true)) {
                    throw PackageException::invalidPackage($class);
                }
            }
        );

        array_push($this->packages, ...$package);
        $this->packages = array_unique($this->packages);
    }

    //////////////////////
    /// PACKAGES STEPS ///
    //////////////////////

    /**
     * Get packages config.
     *
     * @return ConfigInterface
     */
    public function config(): ConfigInterface
    {
        $config = new Config();

        foreach ($this->packages as $package) {
            $packageConfig = call_user_func([$package, 'config']);

            if (null === $packageConfig) {
                continue;
            }

            $config->addConfig($packageConfig);
        }

        return $config;
    }

    /**
     * Register packages.
     *
     * @param Container $container
     *
     * @return static
     */
    public function register(Container $container): PackageSet
    {
        foreach ($this->packages as $package) {
            call_user_func([$package, 'register'], $container);
        }

        return $this;
    }

    /**
     * Boot packages.
     *
     * @param Core $core
     *
     * @return static
     * @throws PackageException
     */
    public function boot(Core $core): PackageSet
    {
        foreach ($this->packages as $package) {
            try {
                call_user_func([$package, 'boot'], $core);
            } catch (Throwable $exception) {
                throw PackageException::boot($package, $exception);
            }
        }

        return $this;
    }
}