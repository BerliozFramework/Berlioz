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

namespace Berlioz\Package\QueueManager\Command;

use Berlioz\Cli\Core\Command\AbstractCommand;
use Berlioz\Cli\Core\Command\Argument;
use Berlioz\Cli\Core\Console\Environment;
use Berlioz\QueueManager\Queue\PurgeableQueueInterface;
use Berlioz\QueueManager\Queue\QueueInterface;
use Berlioz\QueueManager\QueueManager;
use Throwable;

#[Argument('queue', prefix: 'q', longPrefix: 'queue', description: 'Queue name', castTo: 'string')]
class QueuePurgeCommand extends AbstractCommand
{
    public function __construct(
        private readonly QueueManager $queueManager,
    ) {
    }

    /**
     * @inheritDoc
     */
    public static function getDescription(): ?string
    {
        return 'Purge queues of all jobs';
    }

    /**
     * @inheritDoc
     */
    public function run(Environment $env): int
    {
        $queueManager = $this->queueManager->filter(...$env->getArgumentMultiple('queue'));

        if (false === $env->console()->confirm('Continue to purge queues: ' . $queueManager->getName() . '?')->confirmed()) {
            return 0;
        }

        /** @var QueueInterface $queue */
        foreach ($queueManager->getQueues() as $queue) {
            $env->console()->inline('Purging queue "' . $queue->getName() . '"... ');

            try {
                if (!$queue instanceof PurgeableQueueInterface) {
                    $env->console()->info('not purgeable!');
                    continue;
                }

                $queue->purge();

                $env->console()->green('done!');
            } catch (Throwable) {
                $env->console()->error('error!');
            }
        }

        return 0;
    }
}
