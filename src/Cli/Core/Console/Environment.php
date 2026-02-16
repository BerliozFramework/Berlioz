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

namespace Berlioz\Cli\Core\Console;

use Berlioz\Cli\Core\Command\CommandDeclaration;
use Berlioz\Cli\Core\Exception\InvalidArgumentException;

/**
 * Class Environment.
 */
class Environment
{
    public function __construct(
        protected Console $console,
        protected CommandDeclaration $command,
        protected ?array $argv = null,
    ) {
    }

    /**
     * Get console.
     *
     * @return Console
     */
    public function console(): Console
    {
        return $this->console;
    }

    /**
     * Get all arguments.
     *
     * @return array
     */
    public function getArguments(): array
    {
        return $this->console->arguments->toArray();
    }

    /**
     * Get argument value.
     *
     * @param string $name
     *
     * @return string|int|float|bool|null
     * @throws InvalidArgumentException
     */
    public function getArgument(string $name): string|int|float|bool|null
    {
        if (false === $this->console->arguments->exists($name)) {
            throw InvalidArgumentException::unknown($name, $this->command->getClass());
        }

        return $this->console->arguments->get($name);
    }

    /**
     * Get argument multiple values.
     *
     * @param string $name
     *
     * @return array
     * @throws InvalidArgumentException
     */
    public function getArgumentMultiple(string $name): array
    {
        if (false === $this->console->arguments->exists($name)) {
            throw InvalidArgumentException::unknown($name, $this->command->getClass());
        }

        return $this->console->arguments->getArray($name);
    }

    /**
     * Is argument defined?
     *
     * @param string $name
     * @param array|null $argv
     *
     * @return bool
     * @throws InvalidArgumentException
     */
    public function isArgumentDefined(string $name, ?array $argv = null): bool
    {
        if (false === $this->console->arguments->exists($name)) {
            throw InvalidArgumentException::unknown($name, $this->command->getClass());
        }

        return $this->console->arguments->defined($name, $argv ?? $this->argv);
    }
}