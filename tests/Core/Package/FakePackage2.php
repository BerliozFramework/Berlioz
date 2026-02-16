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

namespace Berlioz\Core\Tests\Package;

use Berlioz\Config\Adapter\JsonAdapter;
use Berlioz\Config\ConfigInterface;
use Berlioz\Core\Package\AbstractPackage;
use Berlioz\ServiceContainer\Container;
use Berlioz\ServiceContainer\Service\Service;
use stdClass;

class FakePackage2 extends AbstractPackage
{
    public static function config(): ?ConfigInterface
    {
        return new JsonAdapter('{"package2": "qux"}');
    }

    public static function register(Container $container): void
    {
        $service = new Service(stdClass::class, 'foo');
        $service->setFactory(static::class . '::factory');

        $container->addService($service);
    }

    public static function factory()
    {
        $obj = new stdClass();
        $obj->foo = 'bar';

        return $obj;
    }
}