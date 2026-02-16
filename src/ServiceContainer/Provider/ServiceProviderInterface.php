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

namespace Berlioz\ServiceContainer\Provider;

use Berlioz\ServiceContainer\Container;

interface ServiceProviderInterface
{
    /**
     * Is provides?
     *
     * @param string $id
     *
     * @return bool
     */
    public function provides(string $id): bool;

    /**
     * Register.
     *
     * @param Container $container
     */
    public function register(Container $container): void;

    /**
     * Boot.
     *
     * @param Container $container
     */
    public function boot(Container $container): void;
}