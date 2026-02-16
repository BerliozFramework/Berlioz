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
use Berlioz\Core\Core;
use Berlioz\ServiceContainer\Container;

/**
 * Class AbstractPackage.
 */
abstract class AbstractPackage implements PackageInterface
{
    /**
     * @inheritDoc
     */
    public static function config(): ?ConfigInterface
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public static function register(Container $container): void
    {
    }

    /**
     * @inheritDoc
     */
    public static function boot(Core $core): void
    {
    }
}