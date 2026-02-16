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

use ArrayAccess;
use Berlioz\ServiceContainer\Container\AutoWiringContainer;
use Berlioz\ServiceContainer\Exception\ContainerException;
use Berlioz\ServiceContainer\Exception\NotFoundException;
use Berlioz\ServiceContainer\Tests\Asset\RecursiveService;
use Berlioz\ServiceContainer\Tests\Asset\WithDependency;
use Berlioz\ServiceContainer\Tests\Asset\WithoutConstructor;
use PHPUnit\Framework\TestCase;

class AutoWiringContainerTest extends TestCase
{
    public function testGet()
    {
        $container = new AutoWiringContainer();

        $this->assertInstanceOf(WithDependency::class, $service = $container->get(WithDependency::class));
        $this->assertSame($service, $container->get(WithDependency::class));

        $this->assertInstanceOf(WithoutConstructor::class, $service2 = $container->get(WithoutConstructor::class));
        $this->assertSame($service2, $container->get(WithoutConstructor::class));
        $this->assertSame($service2, $service->param);
    }

    public function testGet_recursive()
    {
        $this->expectException(ContainerException::class);

        $container = new AutoWiringContainer();
        $container->get(RecursiveService::class);
    }

    public function testGetUnknown()
    {
        $this->expectException(NotFoundException::class);

        $container = new AutoWiringContainer();
        $container->get('UnknownClass');
    }

    public function testHas()
    {
        $container = new AutoWiringContainer();
        $container->get(WithoutConstructor::class);

        $this->assertTrue($container->has(WithoutConstructor::class));
        $this->assertTrue($container->has(WithDependency::class));
        $this->assertFalse($container->has('UnknownClass'));
        $this->assertFalse($container->has(ArrayAccess::class)); // An interface
    }
}
