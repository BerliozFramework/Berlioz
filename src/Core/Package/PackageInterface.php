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
use Berlioz\Core\Core;
use Berlioz\ServiceContainer\Container;

/**
 * Interface PackageInterface.
 */
interface PackageInterface
{
    /**
     * Package configuration.
     *
     * Method called for the configuration of package.
     * Do not use this method to do any actions on framework, only configuration of package.
     *
     * @return ConfigInterface|null
     * @throws ConfigException
     */
    public static function config(): ?ConfigInterface;

    /**
     * Register package.
     *
     * Method called for the registration of services associated to the package.
     * Do not use this method to do any actions on framework, only registration of services.
     *
     * @param Container $container
     *
     * @return void
     */
    public static function register(Container $container): void;

    /**
     * Boot package.
     *
     * Method called after creation of all packages.
     *
     * @param Core $core
     *
     * @return void
     */
    public static function boot(Core $core): void;
}