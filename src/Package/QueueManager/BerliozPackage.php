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

namespace Berlioz\Package\QueueManager;

use Berlioz\Config\Adapter\ArrayAdapter;
use Berlioz\Config\ConfigInterface;
use Berlioz\Core\Package\AbstractPackage;
use Berlioz\Package\QueueManager\Command\QueuePurgeCommand;
use Berlioz\Package\QueueManager\Command\QueueSizeCommand;
use Berlioz\Package\QueueManager\Command\QueueWorkerCommand;
use Berlioz\Package\QueueManager\Container\QueueManagerProvider;
use Berlioz\Package\QueueManager\Factory\AwsSqsQueueFactory;
use Berlioz\Package\QueueManager\Factory\DbQueueFactory;
use Berlioz\Package\QueueManager\Factory\MemoryQueueFactory;
use Berlioz\Package\QueueManager\Handler\BerliozCommandJobHandler;
use Berlioz\ServiceContainer\Container;

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
                    'queues' => [
                        'queues' => [],
                        'handlers' => [
                            'berlioz:command' => BerliozCommandJobHandler::class,
                            // The `berlioz:system` handler executes system commands from the (untrusted)
                            // job payload. It is intentionally NOT registered by default to avoid RCE.
                            // Opt-in explicitly if you fully trust every queue producer:
                            //   'berlioz:system' => \Berlioz\Package\QueueManager\Handler\BerliozSystemJobHandler::class,
                        ],
                        'factories' => [
                            MemoryQueueFactory::class,
                            AwsSqsQueueFactory::class,
                            DbQueueFactory::class,
                        ],
                    ],
                ],
                'commands' => [
                    'queue:size' => QueueSizeCommand::class,
                    'queue:purge' => QueuePurgeCommand::class,
                    'queue:worker' => QueueWorkerCommand::class,
                ],
            ]
        );
    }

    /**
     * @inheritDoc
     */
    public static function register(Container $container): void
    {
        $container->addProvider(new QueueManagerProvider());
    }
}
