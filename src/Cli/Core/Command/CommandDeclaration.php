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

/**
 * Class CommandDeclaration.
 */
class CommandDeclaration
{
    public function __construct(
        protected string $name,
        protected string $class,
        protected array $arguments = [],
    ) {
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get class.
     *
     * @return string
     */
    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * Get arguments.
     *
     * @return Argument[]
     */
    public function getArguments(?bool $required = null): array
    {
        if (is_bool($required)) {
            return array_values(
                array_filter(
                    $this->arguments,
                    fn(Argument $argument) => $required === $argument->isRequired()
                )
            );
        }

        return $this->arguments;
    }
}