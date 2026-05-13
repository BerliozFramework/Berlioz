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
use Berlioz\ServiceContainer\Service\Service;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

abstract class ProviderTestCase extends TestCase
{
    /**
     * Get container to test integration.
     *
     * @return Container
     */
    public function getContainer(): Container
    {
        return new Container();
    }

    /**
     * Get providers.
     *
     * @return array
     */
    abstract public static function providers(): array;

    #[DataProvider('providers')]
    public function testProvides(ServiceProviderInterface $provider)
    {
        $container = $this->getContainer();
        $defaultContainerProperty = new ReflectionProperty(Container::class, 'container');
        PHP_VERSION_ID < 80100 && $defaultContainerProperty->setAccessible(true);
        $defaultContainer = $defaultContainerProperty->getValue($container);
        $defaultServices = iterator_to_array($defaultContainer->getServices(), false);
        $provider->register($container);

        /** @var Service $service */
        foreach ($defaultContainer->getServices() as $service) {
            if (in_array($service, $defaultServices, true)) {
                continue;
            }
            if ($service->getAlias() != $service->getClass()) {
                $this->assertTrue(
                    $provider->provides($service->getAlias()),
                    sprintf(
                        'Alias of service "%s" not declared in provider "%s"',
                        $service->getClass(),
                        $provider::class,
                    )
                );
            }

            $this->assertTrue(
                $provider->provides($service->getAlias()),
                sprintf(
                    'Service "%s" not declared in provider "%s"',
                    $service->getClass(),
                    $provider::class,
                )
            );

            foreach ($service->getProvides() as $provide) {
                $this->assertTrue(
                    $provider->provides($provide),
                    sprintf(
                        'Service "%s" provide "%s", but not declared in provider "%s"',
                        $service->getClass(),
                        $provide,
                        $provider::class,
                    )
                );
            }
        }
    }
}