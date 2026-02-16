<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2021 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\Cli\Core\Command;

use Berlioz\Cli\Core\Console\Console;
use Berlioz\Cli\Core\Console\Environment;
use Berlioz\Cli\Core\Console\Usage\CommandUsage;
use Berlioz\Cli\Core\Console\Usage\DefaultUsage;
use Berlioz\Cli\Core\Exception\CliException;
use Berlioz\Core\Core;
use League\CLImate\Argument\Argument;
use Throwable;

/**
 * Class CommandHandler.
 */
class CommandHandler
{
    public function __construct(
        protected Console $console,
        protected CommandManager $commandManager,
        protected Core $core
    ) {
    }

    /**
     * Handle.
     *
     * @param array|null $argv
     *
     * @return int
     * @throws CliException
     */
    public function handle(?array $argv = null): int
    {
        $commandName = $this->console->getArgumentsManager()->getCommandName($argv);

        // No command
        if (null === $commandName) {
            $usage = new DefaultUsage($this->commandManager);
            $usage->output($this->console);
            return 0;
        }

        // Get command
        if (null === ($command = $this->commandManager->getCommand($commandName))) {
            $usage = new DefaultUsage($this->commandManager);
            $usage->output($this->console, $commandName);
            return 1;
        }

        // Add help argument
        $this->console->getArgumentsManager()->add(
            Argument::createFromArray(
                'help',
                [
                    'longPrefix' => 'help',
                    'description' => 'Show this help',
                    'noValue' => true
                ]
            )
        );

        /** @var CommandInterface $commandObj */
        $commandObj = $this->core->getContainer()->get($command->getClass());

        // Ask help
        if (true === $this->console->getArgumentsManager()->defined('help', $argv)) {
            $usage = new CommandUsage($command);
            $usage->output($this->console);
            return 0;
        }

        try {
            $this->addArguments($command);
            $this->console->getArgumentsManager()->parse($argv);

            return $commandObj->run(new Environment($this->console, $command, $argv));
        } catch (Throwable $exception) {
            $this->printException($exception);
            return 1;
        }
    }

    /**
     * Add arguments.
     *
     * @param CommandDeclaration $declaration
     *
     * @throws CliException
     */
    protected function addArguments(CommandDeclaration $declaration): void
    {
        $this->console->getArgumentsManager()->reset();

        foreach ($declaration->getArguments() as $argument) {
            $this->console->getArgumentsManager()->add(
                Argument::createFromArray($argument->getName(), $argument->getArrayCopy())
            );
        }
    }

    /**
     * Print exception.
     *
     * @param Throwable $exception
     */
    protected function printException(Throwable $exception): void
    {
        $iException = 0;

        do {
            $text = ($iException > 0 ? 'Next ' : '') . sprintf('[%s] ', get_class($exception)) . PHP_EOL;
            $text .= $exception->getMessage();
            if (true === $this->core->getDebug()->isEnabled()) {
                $text .= sprintf(
                    ' in %s:%d' . PHP_EOL .
                    'Stack trace:' . PHP_EOL .
                    '%s',
                    $exception->getFile(),
                    $exception->getLine(),
                    $exception->getTraceAsString()
                );
            }

            $this->console->br()->to('error')->backgroundRed()->card($text);

            if (false === $this->core->getDebug()->isEnabled()) {
                return;
            }

            $iException++;
        } while (null !== ($exception = $exception->getPrevious()));
    }
}