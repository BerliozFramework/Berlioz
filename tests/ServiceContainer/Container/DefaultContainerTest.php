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

use Berlioz\ServiceContainer\Container\DefaultContainer;
use Berlioz\ServiceContainer\Exception\NotFoundException;
use Berlioz\ServiceContainer\Service\Service;
use Berlioz\ServiceContainer\Tests\Asset\WithDependency;
use Berlioz\ServiceContainer\Tests\Asset\WithoutConstructor;
use PHPUnit\Framework\TestCase;
use stdClass;

class DefaultContainerTest extends TestCase
{
    public function testAddService()
    {
        $container = new DefaultContainer();

        $this->assertFalse($container->has('foo'));
        $this->assertFalse($container->has('bar'));
        $this->assertFalse($container->has(stdClass::class));

        $container->addService(
            new Service(stdClass::class, 'foo'),
            new Service(stdClass::class, 'bar')
        );

        $this->assertTrue($container->has('foo'));
        $this->assertTrue($container->has('bar'));
        $this->assertTrue($container->has(stdClass::class));
    }

    public function testGet()
    {
        $container = new DefaultContainer();
        $container->addService(
            new Service($service = new WithoutConstructor(), 'foo'),
            new Service(WithDependency::class, 'bar')
        );

        $this->assertInstanceOf(WithoutConstructor::class, $container->get('foo'));
        $this->assertSame($service, $container->get('foo'));
        $this->assertSame($service, $container->get(WithoutConstructor::class));

        $this->assertInstanceOf(WithDependency::class, $service2 = $container->get('bar'));
        $this->assertSame($service2, $container->get(WithDependency::class));
        $this->assertSame($service, $service2->param);
    }

    public function testGetUnknown()
    {
        $this->expectException(NotFoundException::class);

        $container = new DefaultContainer();
        $container->addService(new Service(stdClass::class, 'foo'));
        $container->get('bar');
    }

    public function testHas()
    {
        $container = new DefaultContainer();
        $container->addService(new Service(stdClass::class, 'foo'));

        $this->assertTrue($container->has('foo'));
        $this->assertFalse($container->has('bar'));
    }
}
