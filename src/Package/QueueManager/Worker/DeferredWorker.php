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

namespace Berlioz\Package\QueueManager\Worker;

use Berlioz\Core\App\AbstractApp;
use Berlioz\Core\Debug\DebugHandler;
use Berlioz\QueueManager\Handler\JobHandlerInterface;
use Berlioz\QueueManager\Job\JobInterface;
use Berlioz\QueueManager\Worker;
use Berlioz\QueueManager\WorkerOptions;

/**
 * Class DeferredWorker.
 *
 * Worker draining deferred queues on application terminate, with a debug activity per job.
 */
class DeferredWorker extends Worker
{
    public function __construct(
        JobHandlerInterface $jobHandlerManager,
        private readonly DebugHandler $debug,
    ) {
        parent::__construct($jobHandlerManager);
    }

    /**
     * @inheritDoc
     */
    public function processJob(JobInterface $job, WorkerOptions $options): bool
    {
        $activity = $this->debug
            ->newActivity(sprintf('Deferred job: %s', $job->getName() ?? $job->getId()), AbstractApp::TERMINATE_GROUP)
            ->start()
            ->setDetail([
                'id' => $job->getId(),
                'name' => $job->getName(),
                'queue' => $job->getQueue()->getName(),
                'attempts' => $job->getAttempts(),
            ]);

        try {
            $succeeded = parent::processJob($job, $options);
            $activity->setResult($succeeded ? 'succeeded' : 'failed');

            return $succeeded;
        } finally {
            $activity->end();
        }
    }
}
