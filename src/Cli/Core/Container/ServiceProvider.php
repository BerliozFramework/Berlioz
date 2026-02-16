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

namespace Berlioz\Cli\Core\Container;

use Berlioz\Cli\Core\Command\CommandManager;
use Berlioz\Cli\Core\Console\Console;
use Berlioz\Config\Config;
use Berlioz\Core\Core;
use Berlioz\ServiceContainer\Container;
use Berlioz\ServiceContainer\Provider\AbstractServiceProvider;
use Berlioz\ServiceContainer\Service\CacheStrategy;
use Berlioz\ServiceContainer\Service\Service;

/**
 * Class ServiceProvider.
 */
class ServiceProvider extends AbstractServiceProvider
{
    protected array $provides = [
        CommandManager::class,
        Console::class,
    ];

    public function __construct(protected Core $core)
    {
    }

    /**
     * @inheritDoc
     */
    public function register(Container $container): void
    {
        $container->addService(
            new Service(
                class: CommandManager::class,
                factory: function (Config $config) {
                    return new CommandManager($config->get('commands', []));
                },
                cacheStrategy: new CacheStrategy($this->core->getCache())
            )
        );
        $container->add(Console::class);
    }
}