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

namespace Berlioz\Cli\Core\Console\Usage;

use Berlioz\Cli\Core\Command\Argument;
use Berlioz\Cli\Core\Command\CommandDeclaration;
use Berlioz\Cli\Core\Console\Console;

/**
 * Class CommandUsage.
 */
class CommandUsage
{
    public function __construct(protected CommandDeclaration $commandDeclaration)
    {
    }

    /**
     * Output.
     *
     * @param Console $console
     */
    public function output(Console $console): void
    {
        $description = call_user_func([$this->commandDeclaration->getClass(), 'getDescription']) ?: null;
        if (null !== $description) {
            $console->out($description)->br();
        }

        // Usage
        $options = $this->options() ?: null;
        $arguments = $this->arguments() ?: null;
        $console
            ->yellow('Usage:')
            ->inline('  ' . $this->commandDeclaration->getName());
        null !== $options && $console->inline(' [options]');
        null !== $arguments && $console->inline(' [arguments]');
        $console->br(2);

        null !== $options && $console->out(rtrim($options))->br();
        null !== $arguments && $console->out(rtrim($arguments))->br();

        // Help
        $help = call_user_func([$this->commandDeclaration->getClass(), 'getHelp']) ?: null;
        if (null !== $help) {
            $console
                ->yellow('Help:')
                ->tab()
                ->out($help)
                ->br();
        }
    }

    /**
     * Get options description.
     *
     * @return string
     */
    protected function options(): string
    {
        $output = '';

        foreach ([true, false] as $required) {
            $arguments = $this->commandDeclaration->getArguments($required);
            $arguments = array_filter($arguments, fn(Argument $argument) => true === $argument->hasPrefix());

            // No arguments
            if (0 === count($arguments)) {
                continue;
            }

            $output .= '<yellow>' . (true === $required ? 'Required' : 'Optional') . ' options:</yellow>' . PHP_EOL;

            foreach ($arguments as $argument) {
                $prefixes = [];
                null !== $argument->getPrefix() && $prefixes[] = sprintf('-%s', $argument->getPrefix());
                null !== $argument->getLongPrefix() && $prefixes[] = sprintf('--%s', $argument->getLongPrefix());

                $output .= sprintf('  <green>%s</green> %s', implode(', ', $prefixes), $argument->getDescription());

                if (null !== $argument->getDefaultValue()) {
                    $output .= sprintf(' <yellow>(default: %s)</yellow>', $argument->getDefaultValue());
                }

                $output .= PHP_EOL;
            }

            $output .= PHP_EOL;
        }

        return $output;
    }

    /**
     * Get arguments description.
     *
     * @return string
     */
    protected function arguments(): string
    {
        $output = '';

        foreach ([true, false] as $required) {
            $arguments = $this->commandDeclaration->getArguments($required);
            $arguments = array_filter($arguments, fn(Argument $argument) => false === $argument->hasPrefix());

            // No arguments
            if (0 === count($arguments)) {
                continue;
            }

            $output .= '<yellow>' . (true === $required ? 'Required' : 'Optional') . ' arguments:</yellow>' . PHP_EOL;

            foreach ($arguments as $argument) {
                $output .= sprintf('  <green>%s</green> %s', $argument->getName(), $argument->getDescription());

                if (null !== $argument->getDefaultValue()) {
                    $output .= sprintf(' <yellow>(default: %s)</yellow>', $argument->getDefaultValue());
                }

                $output .= PHP_EOL;
            }

            $output .= PHP_EOL;
        }

        return $output;
    }
}