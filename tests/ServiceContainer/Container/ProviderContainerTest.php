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
use Berlioz\ServiceContainer\Exception\NotFoundException;
use Berlioz\ServiceContainer\Provider\AbstractServiceProvider;
use Berlioz\ServiceContainer\Service\Service;
use Berlioz\ServiceContainer\Tests\Asset\WithDependency;
use Berlioz\ServiceContainer\Tests\Asset\WithoutConstructor;
use Berlioz\ServiceContainer\Tests\FakeContainer;
use PHPUnit\Framework\TestCase;
use stdClass;

class ProviderContainerTest extends TestCase
{
    public function testAddProvider()
    {
        $container = (new FakeContainer())->getProviderContainer();

        $this->assertFalse($container->has('foo'));
        $this->assertFalse($container->has('bar'));
        $this->assertFalse($container->has(stdClass::class));

        $container->addProvider(
            $provider =
                new class extends AbstractServiceProvider {
                    protected bool $booted = false;
                    protected array $provides = [stdClass::class, 'foo', 'bar'];

                    public function isBooted(): bool
                    {
                        return $this->booted;
                    }

                    public function boot(Container $container): void
                    {
                        $this->booted = true;
                    }

                    public function register(Container $container): void
                    {
                        $container->addService(
                            new Service(stdClass::class, 'foo'),
                            new Service(stdClass::class, 'bar')
                        );
                    }
                }
        );

        $this->assertTrue($provider->isBooted());
        $this->assertTrue($container->has('foo'));
        $this->assertTrue($container->has('bar'));
        $this->assertTrue($container->has(stdClass::class));
    }

    public function testGet()
    {
        $container = new FakeContainer();
        $container->addProvider(new FakeServiceProvider());

        $this->assertInstanceOf(WithoutConstructor::class, $service = $container->get('foo'));
        $this->assertSame($service, $container->get('foo'));
        $this->assertSame($service, $container->get(WithoutConstructor::class));

        $this->assertInstanceOf(WithDependency::class, $service2 = $container->get('bar'));
        $this->assertSame($service2, $container->get(WithDependency::class));
        $this->assertSame($service, $service2->param);
    }

    public function testGetUnknown()
    {
        $this->expectException(NotFoundException::class);

        $container = (new FakeContainer())->getProviderContainer();
        $container->addProvider(new FakeServiceProvider());
        $container->get('baz');
    }

    public function testHas()
    {
        $container = (new FakeContainer())->getProviderContainer();
        $container->addProvider(new FakeServiceProvider());

        $this->assertTrue($container->has('foo'));
        $this->assertTrue($container->has('bar'));
        $this->assertFalse($container->has('baz'));
    }
}
