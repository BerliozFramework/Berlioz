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

namespace Berlioz\Cli\Core;

use Berlioz\Cli\Core\Command\Berlioz\CacheClearCommand;
use Berlioz\Cli\Core\Command\Berlioz\ConfigCommand;
use Berlioz\Cli\Core\Container\ServiceProvider;
use Berlioz\Config\Adapter\ArrayAdapter;
use Berlioz\Config\ConfigInterface;
use Berlioz\Config\Exception\ConfigException;
use Berlioz\Core\Package\AbstractPackage;
use Berlioz\ServiceContainer\Container;

/**
 * Class BerliozPackage.
 */
class BerliozPackage extends AbstractPackage
{
    /**
     * @inheritDoc
     * @throws ConfigException
     */
    public static function config(): ?ConfigInterface
    {
        return new ArrayAdapter(
            [
                'commands' => [
                    'berlioz:cache-clear' => CacheClearCommand::class,
                    'berlioz:config' => ConfigCommand::class,
                ],
            ]
        );
    }

    /**
     * @inheritDoc
     */
    public static function register(Container $container): void
    {
        $container->addProvider($container->call(ServiceProvider::class));
    }
}
