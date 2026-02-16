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

namespace Berlioz\ServiceContainer\Tests\Container;

use Berlioz\ServiceContainer\Container;
use Berlioz\ServiceContainer\Provider\AbstractServiceProvider;
use Berlioz\ServiceContainer\Service\Service;
use Berlioz\ServiceContainer\Tests\Asset\WithDependency;
use Berlioz\ServiceContainer\Tests\Asset\WithoutConstructor;

class FakeServiceProvider extends AbstractServiceProvider
{
    protected array $provides = [WithoutConstructor::class, WithDependency::class, 'foo', 'bar'];

    public function register(Container $container): void
    {
        $container->addService(
            new Service(new WithoutConstructor(), 'foo'),
            new Service(WithDependency::class, 'bar')
        );
    }
}
