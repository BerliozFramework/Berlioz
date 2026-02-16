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

namespace Berlioz\Package\Hector;

use Berlioz\Config\Adapter\ArrayAdapter;
use Berlioz\Config\ConfigInterface;
use Berlioz\Core\Core;
use Berlioz\Core\Package\AbstractPackage;
use Berlioz\Package\Hector\Command\CacheClearCommand;
use Berlioz\Package\Hector\Command\GenerateSchemaCommand;
use Berlioz\Package\Hector\Container\ServiceProvider;
use Berlioz\Package\Hector\Http\HectorMiddleware;
use Berlioz\ServiceContainer\Container;
use Hector\Orm\Orm;

/**
 * Class BerliozPackage.
 */
class BerliozPackage extends AbstractPackage
{
    /**
     * @inheritDoc
     */
    public static function config(): ?ConfigInterface
    {
        return new ArrayAdapter(
            [
                'berlioz' => [
                    'http' => [
                        'middlewares' => [
                            98 => [
                                'hector' => HectorMiddleware::class,
                            ],
                        ],
                    ],
                ],
                'hector' => [
                    'dsn' => null,
                    'read_dsn' => null,
                    'schemas' => [],
                    'dynamic_events' => true,
                    'types' => [],
                ],
                'commands' => [
                    'hector:cache-clear' => CacheClearCommand::class,
                    'hector:generate-schema' => GenerateSchemaCommand::class,
                ],
                'twig' => [
                    'paths' => [
                        'Berlioz-HectorPackage' => realpath(__DIR__ . '/resources'),
                    ],
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

    /**
     * @inheritDoc
     */
    public static function boot(Core $core): void
    {
        $core->getContainer()->get(Orm::class);
    }
}
