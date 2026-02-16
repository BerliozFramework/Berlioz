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

use Berlioz\Cli\Core\Console\Console;
use Berlioz\Cli\Core\Exception\CliException;
use Berlioz\Core\Core;
use Berlioz\QueueManager\Handler\JobHandlerInterface;
use Berlioz\QueueManager\Job\JobInterface;

class BerliozSystemJobHandler implements JobHandlerInterface
{
    public function __construct(
        protected readonly Core $core,
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
     * Result of command.
     *
     * @param string|false $output
     * @param int $result
     *
     * @return void
     */
    protected function result(string|false $output, int $result): void
    {
    }

    /**
     * @inheritDoc
     */
    public function handle(JobInterface $job): void
    {
        // Update working directory to app directory
        chdir($this->core->getDirectories()->getAppDir());

        $command = (array)$job->getPayload()->get('command', []);
        $command = implode(' ', $command);

        $result = 0;
        ob_start();
        if (false === passthru($command, $result)) {
            $result === 0 && $result = 1;
        }
        $commandOutput = ob_get_clean() ?: '';
        $this->result($commandOutput, $result);

        if ($result > 0) {
            throw new CliException('Error during execution');
        }
    }
}
