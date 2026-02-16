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

namespace Berlioz\Core\Container\Provider;

use Berlioz\Config\Config;
use Berlioz\Config\ConfigAwareInterface;
use Berlioz\Core\Asset\Assets;
use Berlioz\Core\Composer\Composer;
use Berlioz\Core\Core;
use Berlioz\Core\CoreAwareInterface;
use Berlioz\Core\Debug\DebugHandler;
use Berlioz\Core\Directories\DirectoriesInterface;
use Berlioz\Core\Event\EventDispatcher;
use Berlioz\Core\Event\EventDispatcherBuilder;
use Berlioz\Core\Filesystem\Filesystem;
use Berlioz\Core\Filesystem\FilesystemInterface;
use Berlioz\EventManager\EventDispatcher as BerliozEventDispatcher;
use Berlioz\ServiceContainer\Container;
use Berlioz\ServiceContainer\Inflector\Inflector;
use Berlioz\ServiceContainer\Provider\AbstractServiceProvider;
use Berlioz\ServiceContainer\Service\Service;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Class CoreServiceProvider.
 */
class CoreServiceProvider extends AbstractServiceProvider
{
    protected array $provides = [
        Assets::class,
        Core::class,
        Config::class,
        Composer::class,
        DebugHandler::class,
        DirectoriesInterface::class,
        EventDispatcher::class,
        BerliozEventDispatcher::class,
        EventDispatcherInterface::class,
        Filesystem::class,
        FilesystemInterface::class,
        'assets',
        'berlioz',
        'config',
        'composer',
        'debug',
        'directories',
        'events',
    ];

    public function __construct(protected Core $core)
    {
    }

    /**
     * @inheritDoc
     */
    public function register(Container $container): void
    {
        $container->add($this->core, 'berlioz');
        $container->add($this->core->getConfig(), 'config');
        $container->add($this->core->getComposer(), 'composer');
        $container->add($this->core->getDebug(), 'debug');

        $service = $container->add($this->core->getDirectories(), 'directories');
        $service->addProvide(DirectoriesInterface::class);

        $container->addService(
            new Service(
                class: Assets::class,
                alias: 'assets',
                factory: function (Config $config): Assets {
                    return new Assets(
                        $config->get('berlioz.assets.manifest'),
                        $config->get('berlioz.assets.entrypoints'),
                        $config->get('berlioz.assets.entrypoints_key'),
                    );
                }
            )
        );
        $container->addService(
            $service = new Service(
                class: EventDispatcher::class,
                alias: 'events',
                factory: function (Core $core): EventDispatcher {
                    $builder = new EventDispatcherBuilder($core);
                    $builder->addDefaultSubscribers();
                    $builder->addSubscribersFromConfig();

                    return $builder->getEventDispatcher();
                }
            )
        );
        $service->addProvide(
            BerliozEventDispatcher::class,
            EventDispatcherInterface::class
        );

        $service = $container->add($this->core->getFilesystem(), Filesystem::class);
        $service->addProvide(FilesystemInterface::class);
    }

    /**
     * @inheritDoc
     */
    public function boot(Container $container): void
    {
        $container->addInflector(
            new Inflector(
                CoreAwareInterface::class,
                'setCore',
                ['core' => '@berlioz']
            )
        );
        $container->addInflector(
            new Inflector(
                ConfigAwareInterface::class,
                'setConfig',
                ['config' => '@config']
            )
        );
    }
}