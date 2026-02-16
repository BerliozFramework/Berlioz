<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2024 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\Package\QueueManager\Container;

use Berlioz\Config\Config;
use Berlioz\Package\QueueManager\Factory\QueueFactories;
use Berlioz\QueueManager\Handler\JobHandlerManager;
use Berlioz\QueueManager\QueueManager;
use Berlioz\QueueManager\Worker;
use Berlioz\ServiceContainer\Container;
use Berlioz\ServiceContainer\Provider\AbstractServiceProvider;
use Berlioz\ServiceContainer\Service\Service;

class QueueManagerProvider extends AbstractServiceProvider
{
    protected array $provides = [
        QueueFactories::class,
        QueueManager::class,
        JobHandlerManager::class,
        Worker::class,
    ];

    /**
     * @inheritDoc
     */
    public function register(Container $container): void
    {
        $container->addService(new Service(class: QueueFactories::class));
        $container->addService(
            new Service(
                class: JobHandlerManager::class,
                factory: function (Config $config) use ($container) {
                    $jobManager = new JobHandlerManager(container: $container);

                    $handlers = $config->get('berlioz.queues.handlers', []);
                    array_walk(
                        $handlers,
                        fn($handlerClass, $jobName) => $jobManager->addHandler($jobName, $handlerClass),
                    );

                    return $jobManager;
                }
            )
        );
        $container->addService(
            new Service(
                class: QueueManager::class,
                factory: function (Config $config, QueueFactories $factories) {
                    $queues = array_map(
                        fn($v) => iterator_to_array($factories->createFromConfig($v), false),
                        $config->get('berlioz.queues.queues', [])
                    );
                    $queues = array_merge(...$queues);

                    return new QueueManager(...$queues);
                }
            )
        );
    }
}
