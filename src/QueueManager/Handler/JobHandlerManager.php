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

namespace Berlioz\QueueManager\Handler;

use Berlioz\QueueManager\Exception\QueueManagerException;
use Berlioz\QueueManager\Job\JobInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

class JobHandlerManager implements JobHandlerInterface
{
    private array $handlers = [];

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ?JobHandlerInterface $defaultJobHandler = null,
    ) {
    }

    public function __debugInfo(): ?array
    {
        return [
            'handlers' => $this->handlers,
            'container' => '**CONTAINER**',
        ];
    }

    /**
     * Add job handler.
     *
     * @param string $jobName
     * @param class-string<JobHandlerInterface> $jobHandlerClass
     *
     * @return self
     * @throws QueueManagerException
     */
    public function addHandler(string $jobName, string $jobHandlerClass): self
    {
        if (array_key_exists($jobName, $this->handlers)) {
            throw new QueueManagerException('A job handler already exists for job "' . $jobName . '".');
        }

        $this->handlers[$jobName] = $jobHandlerClass;

        return $this;
    }

    /**
     * @inheritDoc
     * @throws ContainerExceptionInterface
     */
    public function handle(JobInterface $job): void
    {
        // No handler for job
        if (null === ($handlerClass = $this->findHandler($job->getName()))) {
            if ($this->defaultJobHandler) {
                $this->defaultJobHandler->handle($job);
                return;
            }

            throw QueueManagerException::invalidJobHandler($job->getName());
        }

        // Get handler from service container
        $handler = $this->container->get($handlerClass);

        if (!$handler instanceof JobHandlerInterface) {
            throw QueueManagerException::invalidJobHandler($job->getName());
        }

        $handler->handle($job);
    }

    /**
     * Find handler.
     *
     * @param $jobName
     *
     * @return class-string<JobHandlerInterface>|null
     */
    public function findHandler($jobName): ?string
    {
        foreach ($this->handlers as $jobHandle => $handler) {
            // Exact job name
            if ($jobHandle === $jobName) {
                return $handler;
            }

            // No wildcard
            if (false === str_ends_with($jobHandle, '*')) {
                continue;
            }

            // Wildcard
            $regex = '/^' . str_replace('\*', '.*', preg_quote($jobHandle, '/')) . '$/';
            if (1 === preg_match($regex, $jobName)) {
                return $handler;
            }
        }

        return null;
    }
}
