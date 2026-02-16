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
use Berlioz\Core\Core;
use Berlioz\Core\Package\AbstractPackage;
use Berlioz\ServiceContainer\Container;
use Berlioz\ServiceContainer\Service\Service;
use DateTime;

class FakePackage1 extends AbstractPackage
{
    public static bool $foo = false;

    public static function config(): ?ConfigInterface
    {
        return new JsonAdapter(__DIR__ . '/fake.package1.config.json', true);
    }

    public static function register(Container $container): void
    {
        $container->addService(new Service(DateTime::class, 'date'));
    }

    public static function boot(Core $core): void
    {
        self::$foo = true;
    }
}