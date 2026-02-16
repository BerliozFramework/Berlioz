<?php
/**
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2020 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\Package\Twig;

use Berlioz\Config\Adapter\ArrayAdapter;
use Berlioz\Config\ConfigInterface;
use Berlioz\Core\Package\AbstractPackage;
use Berlioz\Package\Twig\Container\ServiceProvider;
use Berlioz\Package\Twig\Extension\AssetExtension;
use Berlioz\Package\Twig\Extension\DefaultExtension;
use Berlioz\Package\Twig\Extension\RouterExtension;
use Berlioz\ServiceContainer\Container;

class BerliozPackage extends AbstractPackage
{
    /**
     * @inheritDoc
     */
    public static function config(): ConfigInterface
    {
        return new ArrayAdapter(
            [
                'berlioz' => [
                    'directories' => [
                        'templates' => '{config:berlioz.directories.app}/resources/templates',
                    ],
                ],
                'twig' => [
                    'paths' => [
                        '__main__' => '{config: berlioz.directories.templates}',
                        'Berlioz-TwigPackage' => realpath(__DIR__ . '/resources'),
                    ],
                    'options' => [
                        'debug' => '{config: berlioz.debug.enable}',
                        'cache' => '{config: berlioz.directories.cache}/twig',
                        'optimizations' => -1,
                    ],
                    'extensions' => [
                        AssetExtension::class,
                        DefaultExtension::class,
                        RouterExtension::class,
                    ],
                    'globals' => [],
                ],
            ]
        );
    }

    /**
     * @inheritDoc
     */
    public static function register(Container $container): void
    {
        $container->addProvider(new ServiceProvider());
    }
}
