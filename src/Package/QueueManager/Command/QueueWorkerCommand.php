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
use Berlioz\QueueManager\Handler\JobHandlerManager;
use Berlioz\QueueManager\QueueManager;
use Berlioz\QueueManager\RateLimiter\MultiRateLimiter;
use Berlioz\QueueManager\RateLimiter\NullRateLimiter;
use Berlioz\QueueManager\Worker;
use Berlioz\QueueManager\WorkerOptions;
use League\CLImate\Logger;
use Psr\Log\LogLevel;

#[Argument('name', prefix: 'n', longPrefix: 'name', description: 'Worker name', castTo: 'string')]
#[Argument('queue', prefix: 'q', longPrefix: 'queue', description: 'Queue name', castTo: 'string')]
#[Argument('limit', longPrefix: 'limit', description: 'Limit', defaultValue: 0, castTo: 'int')]
#[Argument('delay', longPrefix: 'delay', description: 'Delay between 2 consumption (in seconds)', defaultValue: 0, castTo: 'float')]
#[Argument('delayNoJob', longPrefix: 'delay-no-job', description: 'Delay if no job (in seconds)', defaultValue: 1, castTo: 'float')]
#[Argument('memoryLimit', longPrefix: 'memory', description: 'Memory limit (MB)', defaultValue: 0, castTo: 'int')]
#[Argument('timeLimit', longPrefix: 'time', description: 'Time limit (in seconds)', defaultValue: 0, castTo: 'int')]
#[Argument('rateLimit', longPrefix: 'rate', description: 'Rate limit', castTo: 'string')]
#[Argument('backoff', longPrefix: 'backoff', description: 'Backoff time (in seconds)', defaultValue: 0, castTo: 'int')]
#[Argument('backoffMultiplier', longPrefix: 'backoff-multiplier', description: 'Backoff multiplier', defaultValue: 1, castTo: 'int')]
#[Argument('killFilePath', longPrefix: 'kill-file', description: 'Kill file path', castTo: 'string')]
#[Argument('verbose', prefix: 'v', description: 'Verbose', noValue: true, castTo: 'bool')]
class QueueWorkerCommand extends AbstractCommand
{
    public function __construct(
        private readonly JobHandlerManager $jobHandlerManager,
        private readonly QueueManager $queueManager,
    ) {
    }

    /**
     * @inheritDoc
     */
    public static function getDescription(): ?string
    {
        return 'Start work for queues jobs';
    }

    /**
     * @inheritDoc
     */
    public function run(Environment $env): int
    {
        $worker = new Worker($this->jobHandlerManager);
        $worker->setLogger(
            new Logger(
                level: match (true) {
                    $env->getArgument('verbose') => LogLevel::DEBUG,
                    default => LogLevel::INFO,
                },
                climate: $env->console()
            )
        );

        return $worker->run(
            $this->queueManager->filter(...$env->getArgumentMultiple('queue')),
            new WorkerOptions(
                name: $env->getArgument('name') ?: null,
                limit: $env->getArgument('limit') ?: INF,
                memoryLimit: $env->getArgument('memoryLimit') ?: INF,
                timeLimit: $env->getArgument('timeLimit') ?: INF,
                killFilePath: $env->getArgument('killFilePath'),
                sleep: $env->getArgument('delay'),
                sleepNoJob: $env->getArgument('delayNoJob'),
                backoffTime: (int)$env->getArgument('backoff'),
                backoffMultiplier: (int)$env->getArgument('backoffMultiplier'),
                rateLimiter: match ($env->getArgumentMultiple('rateLimit') ?: null) {
                    null => new NullRateLimiter(),
                    default => MultiRateLimiter::createFromString(...$env->getArgumentMultiple('rateLimit')),
                },
            )
        );
    }
}
