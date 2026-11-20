<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2026 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\Core\Tests\Container\Provider;

use Berlioz\Config\Adapter\ArrayAdapter;
use Berlioz\Core\Container\Provider\AppServiceProvider;
use Berlioz\Core\Core;
use Berlioz\Core\TestProject\ServiceBar;
use Berlioz\Core\TestProject\ServiceFoo;
use Berlioz\Core\TestProject\ServiceQuux;
use Berlioz\Core\TestProject\ServiceQux;
use Berlioz\Core\Tests\Directories\FakeDefaultDirectories;
use Berlioz\Core\Tests\RestoresErrorHandler;
use PHPUnit\Framework\TestCase;

class AppServiceProviderTest extends TestCase
{
    use RestoresErrorHandler;

    private const SERVICES = [
        'legacyFoo' => ServiceFoo::class,
        'legacyBar' => [
            'class' => ServiceBar::class,
            'arguments' => ['quux' => 'value'],
        ],
        'legacyQux' => [
            'class' => ServiceQux::class,
            'calls' => [
                ['method' => 'inc', 'arguments' => ['increment' => 4]],
                ['method' => 'inc', 'arguments' => ['increment' => 2]],
            ],
        ],
        'legacyQuux' => [
            'class' => ServiceQuux::class,
            'factory' => ServiceQuux::class . '::factory',
            'provides' => ['quuxAlias'],
        ],
    ];

    private function getCore(array $services = []): Core
    {
        $core = new Core(new FakeDefaultDirectories(), false);
        $core->getConfig()->addConfig(new ArrayAdapter(['container' => ['services' => $services]], 1000));

        return $core;
    }

    private function createProvider(Core $core, ?array &$deprecations): AppServiceProvider
    {
        $deprecations = [];
        set_error_handler(function (int $errno, string $errstr) use (&$deprecations): bool {
            if ($errno !== E_USER_DEPRECATED) {
                return false;
            }

            $deprecations[] = $errstr;

            return true;
        });

        try {
            return new AppServiceProvider($core);
        } finally {
            restore_error_handler();
        }
    }

    public function testConstruct_withoutServices(): void
    {
        $this->createProvider($this->getCore(), $deprecations);

        $this->assertSame([], $deprecations);
    }

    public function testConstruct_withServices(): void
    {
        $this->createProvider($this->getCore(self::SERVICES), $deprecations);

        $this->assertSame(
            [
                'Declaring services in "container.services" configuration is deprecated since 3.4 '
                . 'and will be removed in 4.0. Register them from a service provider listed in '
                . '"container.providers" instead. '
                . 'See https://getberlioz.com/docs/3.x/getting-started/upgrading',
            ],
            $deprecations,
        );
    }

    public function testRegister_withServices(): void
    {
        $core = $this->getCore(self::SERVICES);
        $provider = $this->createProvider($core, $deprecations);

        foreach (self::SERVICES as $alias => $config) {
            $this->assertTrue($provider->provides($alias));
            $this->assertTrue($provider->provides(is_string($config) ? $config : $config['class']));
        }
        $this->assertTrue($provider->provides('quuxAlias'));
        $this->assertFalse($provider->provides('unknown'));

        $container = $core->getContainer();
        $container->addProvider($provider);

        $this->assertInstanceOf(ServiceFoo::class, $foo = $container->get('legacyFoo'));
        $this->assertSame($core, $foo->getCore());

        $this->assertInstanceOf(ServiceBar::class, $bar = $container->get('legacyBar'));
        $this->assertSame('value', $bar->quux);

        $this->assertInstanceOf(ServiceQux::class, $qux = $container->get('legacyQux'));
        $this->assertSame($bar, $qux->serviceBar);
        $this->assertSame(7, $qux->increment);

        $this->assertInstanceOf(ServiceQuux::class, $quux = $container->get('quuxAlias'));
        $this->assertTrue($quux->factory);
        $this->assertSame($qux, $quux->serviceQux);
    }
}
