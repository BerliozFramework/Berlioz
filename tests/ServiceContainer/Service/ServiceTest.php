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

namespace Berlioz\ServiceContainer\Tests\Service;

use Berlioz\ServiceContainer\Container;
use Berlioz\ServiceContainer\Exception\ContainerException;
use Berlioz\ServiceContainer\Instantiator;
use Berlioz\ServiceContainer\Service\CacheStrategy;
use Berlioz\ServiceContainer\Service\Service;
use Berlioz\ServiceContainer\Tests\Asset\RecursiveService;
use Berlioz\ServiceContainer\Tests\Asset\Service1;
use Berlioz\ServiceContainer\Tests\Asset\Service2;
use Berlioz\ServiceContainer\Tests\Asset\Service4;
use PHPUnit\Framework\TestCase;

class ServiceTest extends TestCase
{
    public function testConstruct()
    {
        $service = new Service(Service1::class, 'foo');

        $this->assertEquals('foo', $service->getAlias());
        $this->assertEquals(Service1::class, $service->getClass());
    }

    public function testConstructWithObject()
    {
        $service = new Service($object = new Service1('foo', 'bar', 1), 'foo');

        $this->assertEquals('foo', $service->getAlias());
        $this->assertEquals(Service1::class, $service->getClass());
        $this->assertSame($object, $service->get(new Instantiator()));
    }

    public function testGetAlias()
    {
        $service = new Service(Service1::class, 'foo');

        $this->assertEquals('foo', $service->getAlias());
    }

    public function testGetAliasDefault()
    {
        $service = new Service(Service1::class);

        $this->assertEquals(Service1::class, $service->getAlias());
    }

    public function testGetClass()
    {
        $service = new Service(Service1::class);

        $this->assertEquals(Service1::class, $service->getClass());
    }

    public function testIsNullable()
    {
        $service = new Service(Service1::class);

        $this->assertFalse($service->isNullable());

        $service->setNullable(true);

        $this->assertTrue($service->isNullable());
    }

    public function testIsShared()
    {
        $service = new Service(Service1::class);

        $this->assertTrue($service->isShared());

        $service->setShared(false);

        $this->assertFalse($service->isShared());
    }

    public function testGetFactory()
    {
        $service = new Service(Service1::class, factory: $factory = fn(...$args) => new Service1(...$args));

        $this->assertSame($factory, $service->getFactory());
    }

    public function testGetFactoryDefault()
    {
        $service = new Service(Service1::class);

        $this->assertNull($service->getFactory());
    }

    public function testSetFactory()
    {
        $service = new Service(Service1::class);

        $this->assertNull($service->getFactory());

        $service->setFactory($factory = fn(...$args) => new Service1(...$args));

        $this->assertSame($factory, $service->getFactory());
    }

    public function testAddArgument()
    {
        $service = new Service(Service1::class);
        $service->addArgument('foo', 'bar');

        $this->assertEquals(['foo' => 'bar'], $service->getArguments());
    }

    public function testAddArguments()
    {
        $service = new Service(Service1::class);
        $service->addArgument('foo', 'bar');
        $service->addArguments(['foo' => 'baz', 'qux' => 'quux']);

        $this->assertEquals(['foo' => 'baz', 'qux' => 'quux'], $service->getArguments());
    }

    public function testAddCall()
    {
        $service = new FakeService(Service1::class);
        $service->addCall('method', ['foo' => 'bar', 'baz' => 'qux']);

        $this->assertEquals(
            [['method', ['foo' => 'bar', 'baz' => 'qux']]],
            $service->getCalls()
        );
    }

    public function testAddCalls()
    {
        $service = new FakeService(Service1::class);
        $service->addCalls(['method' => ['foo' => 'bar', 'baz' => 'qux']]);

        $this->assertEquals(
            [['method', ['foo' => 'bar', 'baz' => 'qux']]],
            $service->getCalls()
        );
    }

    public function testGet_recursive()
    {
        $this->expectException(ContainerException::class);

        $container = new Container();
        $container->addService($service = new Service(RecursiveService::class));
        $service->get(new Instantiator($container));
    }

    public function testGet_withFactory()
    {
        $service = new Service(
            Service1::class,
            factory: fn($param1, $param2, $param3) => new Service1($param1, $param2, $param3)
        );
        $service->addArguments(['param1' => 'foo', 'param2' => 'bar', 'param3' => 1]);

        $this->assertInstanceOf(
            Service1::class,
            $object = $service->get(new Instantiator())
        );
        $this->assertSame($object, $service->get(new Instantiator()));
    }

    public function testGet_withFactoryNullResult()
    {
        $nbRetrieve = 0;
        $service = new Service(
            Service1::class,
            factory: function ($param1, $param2, $param3) use (&$nbRetrieve) {
                $nbRetrieve++;
                return null;
            }
        );
        $service->setNullable(true);
        $service->addArguments(['param1' => 'foo', 'param2' => 'bar', 'param3' => 1]);

        $this->assertNull($service->get(new Instantiator()));
        $this->assertNull($service->get(new Instantiator()));
        $this->assertEquals(1, $nbRetrieve);
    }

    public function testGet_notShared()
    {
        $service = new Service(Service4::class);
        $service->setShared(false);

        $this->assertInstanceOf(
            Service4::class,
            $object = $service->get(new Instantiator())
        );
        $this->assertNotSame($object, $service->get(new Instantiator()));
    }

    public function testGet_withBadResultFactory()
    {
        $this->expectException(ContainerException::class);

        $service = new Service(
            Service2::class,
            factory: fn($param1, $param2, $param3) => new Service1($param1, $param2, $param3)
        );
        $service->addArguments(['param1' => 'foo', 'param2' => 'bar', 'param3' => 1]);
        $service->get(new Instantiator());
    }

    public function testGet_withCalls()
    {
        $service = new Service(Service1::class);
        $service->addArguments(['param1' => 'foo', 'param2' => 'bar', 'param3' => 1]);
        $service->addCall('increaseParam3', ['nb' => 2]);

        $this->assertInstanceOf(Service1::class, $object = $service->get(new Instantiator()));
        $this->assertEquals(3, $object->getParam3());
    }

    public function testGet_withCache()
    {
        $cacheManager = new MemoryCacheDriver();
        $service = new Service(Service1::class, cacheStrategy: new CacheStrategy($cacheManager));
        $service->addArguments(['param1' => 'foo', 'param2' => 'bar', 'param3' => 1]);

        $service2 = new Service(Service1::class, cacheStrategy: new CacheStrategy($cacheManager));
        $service2->addArguments(['param1' => 'foo', 'param2' => 'bar', 'param3' => 1]);

        $this->assertSame($service->get(new Instantiator()), $service2->get(new Instantiator()));
    }

    public function testGet_withFactoryNullResultAndCache()
    {
        $nbRetrieve = 0;
        $cacheManager = new MemoryCacheDriver();

        $service1 = new Service(
            class: Service1::class,
            factory: function () use (&$nbRetrieve) {
                $nbRetrieve++;
                return null;
            },
            cacheStrategy: new CacheStrategy($cacheManager)
        );
        $service1->setNullable(true);

        $service2 = new Service(
            class: Service1::class,
            factory: function () use (&$nbRetrieve) {
                $nbRetrieve++;
                return null;
            },
            cacheStrategy: new CacheStrategy($cacheManager)
        );
        $service2->setNullable(true);

        $this->assertNull($service1->get(new Instantiator()));
        $this->assertNull($service2->get(new Instantiator()));
        $this->assertEquals(1, $nbRetrieve);
    }

    public function testCallsOnNonSharedService()
    {
        $service = new Service(
            class: Service1::class,
            factory: fn() => new Service1('a', 'b', 10),
        );
        $service->setShared(false);
        $service->addCall('increaseParam3', ['nb' => 5]);

        $result = $service->get(new Instantiator());

        $this->assertInstanceOf(Service1::class, $result);
        $this->assertEquals(15, $result->getParam3());
    }
}
