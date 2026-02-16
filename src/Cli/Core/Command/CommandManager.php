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

use Berlioz\Cli\Core\Exception\CommandException;
use Countable;
use ReflectionAttribute as RAttribute;
use ReflectionClass as RClass;
use ReflectionException;

/**
 * Class CommandManager.
 */
class CommandManager implements Countable
{
    protected array $commands = [];

    /**
     * CommandManager constructor.
     *
     * @param array $commands
     *
     * @throws CommandException
     */
    public function __construct(array $commands = [])
    {
        $this->addCommands($commands);
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->commands);
    }

    /**
     * Add commands.
     *
     * @param array $commands
     *
     * @throws CommandException
     */
    public function addCommands(array $commands): void
    {
        array_walk($commands, fn($class, $name) => $this->newCommand($name, $class));
    }

    /**
     * New command.
     *
     * @param string $name
     * @param string $class
     *
     * @return CommandDeclaration
     * @throws CommandException
     */
    public function newCommand(string $name, string $class): CommandDeclaration
    {
        if (!is_a($class, CommandInterface::class, true)) {
            throw CommandException::invalidCommandClass($class);
        }

        return $this->addCommand($this->createDeclaration($name, $class));
    }

    /**
     * Add command.
     *
     * @param CommandDeclaration $command
     *
     * @return CommandDeclaration
     */
    public function addCommand(CommandDeclaration $command): CommandDeclaration
    {
        $this->commands[$command->getName()] = $command;

        return $command;
    }

    /**
     * Create declaration.
     *
     * @param string $name
     * @param string $class
     *
     * @return CommandDeclaration
     * @throws CommandException
     */
    protected function createDeclaration(string $name, string $class): CommandDeclaration
    {
        try {
            $reflectionClass = new RClass($class);
            $attributes =
                array_map(
                    function (RAttribute $reflectionAttribute) {
                        return $reflectionAttribute->newInstance();
                    },
                    $reflectionClass->getAttributes(Argument::class, RAttribute::IS_INSTANCEOF)
                );

            return new CommandDeclaration(
                name: $name,
                class: $class,
                arguments: $attributes,
            );
        } catch (ReflectionException $exception) {
            throw CommandException::parsingArguments($class, $exception);
        }
    }

    /**
     * Get commands.
     *
     * @return CommandDeclaration[]
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * Get command.
     *
     * @param string $name
     *
     * @return CommandDeclaration|null
     */
    public function getCommand(string $name): ?CommandDeclaration
    {
        return $this->commands[$name] ?? null;
    }
}