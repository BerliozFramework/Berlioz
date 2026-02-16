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

namespace Berlioz\Package\QueueManager\Handler;

use Berlioz\Cli\Core\Command\CommandHandler;
use Berlioz\Cli\Core\Command\CommandManager;
use Berlioz\Cli\Core\Console\Console;
use Berlioz\Cli\Core\Exception\CliException;
use Berlioz\Core\Core;
use Berlioz\QueueManager\Handler\JobHandlerInterface;
use Berlioz\QueueManager\Job\JobInterface;

class BerliozCommandJobHandler implements JobHandlerInterface
{
    public function __construct(
        protected readonly Core $core,
        protected readonly CommandManager $commandManager,
    ) {
    }

    /**
     * New console.
     *
     * @return Console
     */
    protected function newConsole(): Console
    {
        return new Console();
    }

    /**
     * @inheritDoc
     */
    public function handle(JobInterface $job): void
    {
        $commandHandler = new CommandHandler(
            console: $this->newConsole(),
            commandManager: $this->commandManager,
            core: $this->core,
        );

        $command = (array)$job->getPayload()->get('command', []);
        empty($command) && throw new CliException('Empty command');
        array_unshift($command, 'berlioz');

        $result = $commandHandler->handle($command);

        if ($result > 0) {
            throw new CliException('Error during execution');
        }
    }
}
