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
        // The command is taken from the (untrusted) job payload. It MUST be executed without a
        // shell: passing an array of arguments to proc_open() runs the binary directly, so shell
        // metacharacters (|, ;, &&, $(), >, ...) are treated as literal arguments, never interpreted.
        $command = array_values(
            array_map(strval(...), (array)$job->getPayload()->get('command', []))
        );

        if ([] === $command) {
            throw new CliException('Empty command');
        }

        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $pipes = [];

        $process = proc_open(
            $command,
            $descriptors,
            $pipes,
            $this->core->getDirectories()->getAppDir(),
        );

        if (!is_resource($process)) {
            throw new CliException('Unable to start command');
        }

        $commandOutput = stream_get_contents($pipes[1]) ?: '';
        $commandOutput .= stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);

        $result = proc_close($process);
        $this->result($commandOutput, $result);

        if ($result > 0) {
            throw new CliException('Error during execution');
        }
    }
}
