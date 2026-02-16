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

namespace Berlioz\ServiceContainer\Tests;

use Berlioz\ServiceContainer\Container;
use Berlioz\ServiceContainer\ContainerAwareInterface;
use Berlioz\ServiceContainer\ContainerAwareTrait;
use Berlioz\ServiceContainer\Inflector\Inflector;
use Berlioz\ServiceContainer\InstantiatorAwareInterface;
use Berlioz\ServiceContainer\Service\Service;
use Berlioz\ServiceContainer\Tests\Asset\Service4;
use Berlioz\ServiceContainer\Tests\Asset\WithDependency;
use Berlioz\ServiceContainer\Tests\Asset\WithoutConstructor;
use Berlioz\ServiceContainer\Tests\Container\FakeServiceProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

class ContainerTest extends TestCase
{
    public function test__construct()
    {
        $container = new FakeContainer(
            [new Container\AutoWiringContainer()],
            [new Inflector(InstantiatorAwareInterface::class, 'setInstantiator')]
        );

        $this->assertCount(3, iterator_to_array($container->getContainers(), false));
        $this->assertCount(3, $container->getInflectors());
    }

    public function testCall()
    {
        $container = new Container();

        $this->assertEquals('foo', $container->call(fn() => 'foo'));
        $this->assertEquals(
            sprintf('It\'s a test "%s"', Service4::class),
            $container->call(sprintf('%s::test', Service4::class))
        );
        $this->assertEquals(
            sprintf('It\'s a test "%s"', Service4::class),
            $container->call(sprintf('%s::testStatic', Service4::class))
        );

        $this->assertInstanceOf(
            ContainerAwareInterface::class,
            $object = $container->call(
                fn() => new class implements ContainerAwareInterface {
                    use ContainerAwareTrait;
                }
            )
        );
        $this->assertSame($container, $object->getContainer());
    }

    public function testAutoWiring()
    {
        $container = new FakeContainer();

        $this->assertCount(2, iterator_to_array($container->getContainers(), false));

        $container->autoWiring(true);
        $this->assertCount(3, iterator_to_array($container->getContainers(), false));

        $container->autoWiring(false);
        $this->assertCount(2, iterator_to_array($container->getContainers(), false));
    }

    public function testAdd()
    {
        $container = new Container();

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

    public function testAddService()
    {
        $container = new Container();
        $container->addService(new Service(stdClass::class, 'foo'));

        $this->assertInstanceOf(stdClass::class, $container->get('foo'));
    }

    public function testGet()
    {
        $container = new Container();
        $container->autoWiring();
        $container->addService(
            new Service($service = new WithoutConstructor(), 'foo'),
            new Service(WithDependency::class, 'bar')
        );

        $this->assertSame($service, $container->get('foo'));
        $this->assertInstanceOf(Service4::class, $container->get(Service4::class));
    }

    public function testGet_inflects()
    {
        $container = new Container();
        $container->autoWiring();
        $container->addService(
            new Service(
                $object = new class implements ContainerAwareInterface {
                    use ContainerAwareTrait;
                },
                'foo'
            ),
        );

        $this->assertSame($object, $container->get('foo'));
        $this->assertSame($container, $container->get('foo')->getContainer());
    }

    public function testHas()
    {
        $container = new Container();

        $container->addService(new Service(stdClass::class, 'foo'));

        $this->assertTrue($container->has('foo'));
        $this->assertFalse($container->has('bar'));
    }

    public function testAddContainer()
    {
        $container = new FakeContainer();
        $container->addContainer(new Container\DefaultContainer(), new Container\DefaultContainer());

        $containers = iterator_to_array($container->getContainers(), false);
        $this->assertCount(4, $containers);
    }

    public function testAddInflector()
    {
        $container = new FakeContainer();
        $container->addInflector(new Inflector(stdClass::class, 'myMethod'));

        $this->assertCount(3, $container->getInflectors());
    }

    public function testAddProvider()
    {
        $container = new FakeContainer();
        $container->addProvider(new FakeServiceProvider());

        $this->assertTrue($container->has('foo'));
        $this->assertTrue($container->has('bar'));
        $this->assertFalse($container->has('baz'));
    }
}
