<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2026 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\Package\QueueManager\Listener;

use Berlioz\Config\Config;
use Berlioz\Core\App\AbstractApp;
use Berlioz\Core\Debug\DebugHandler;
use Berlioz\Core\Event\TerminateEvent;
use Berlioz\Package\QueueManager\Worker\DeferredWorker;
use Berlioz\QueueManager\Handler\JobHandlerManager;
use Berlioz\QueueManager\Queue\MemoryQueue;
use Berlioz\QueueManager\QueueManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Class DeferredQueueListener.
 *
 * Consumes the deferred queues ("berlioz.queues.deferred.queues") on application terminate,
 * i.e. after the HTTP response has been sent, or at the end of a CLI command.
 */
class DeferredQueueListener
{
    public function __construct(
        private readonly Config $config,
        private readonly ContainerInterface $container,
        private readonly DebugHandler $debug,
    ) {
    }

    /**
     * On terminate.
     *
     * Never throws: errors are added to the debug report and raised as a warning.
     *
     * @param TerminateEvent $event
     */
    public function onTerminate(TerminateEvent $event): void
    {
        $queues = array_filter((array)$this->config->get('berlioz.queues.deferred.queues', []));

        if (empty($queues)) {
            return;
        }

        $activity = $this->debug->newActivity('Deferred queues', AbstractApp::TERMINATE_GROUP)
            ->start()
            ->setDetail($queues);
        $queueManager = null;

        try {
            /** @var QueueManager $queueManager */
            $queueManager = $this->container->get(QueueManager::class)->filter(...$queues);

            $worker = new DeferredWorker($this->container->get(JobHandlerManager::class), $this->debug);
            if ($this->container->has(LoggerInterface::class)) {
                $worker->setLogger($this->container->get(LoggerInterface::class));
            }

            $timeLimit = $this->config->get('berlioz.queues.deferred.time_limit');
            $activity->setResult($worker->drain($queueManager, null !== $timeLimit ? (float)$timeLimit : INF));
        } catch (Throwable $exception) {
            $this->debug->addException($exception);
            trigger_error(
                sprintf('Unable to consume deferred queues: %s', $exception->getMessage()),
                E_USER_WARNING,
            );
        } finally {
            // Memory queues live in the container: never keep jobs for a next request
            // of a long-running process (worker mode). Persistent queues are left untouched.
            foreach ($queueManager?->getQueues() ?? [] as $queue) {
                if ($queue instanceof MemoryQueue) {
                    $queue->purge();
                }
            }

            $activity->end();
        }
    }
}
