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

use Berlioz\Cli\Core\Command\CommandManager;
use Berlioz\Cli\Core\Console\Console;

/**
 * Class DefaultUsage.
 */
class DefaultUsage
{
    public function __construct(protected CommandManager $commandManager)
    {
    }

    /**
     * Output.
     *
     * @param Console $console
     * @param string|null $commandName
     */
    public function output(Console $console, ?string $commandName = null): void
    {
        $console
            ->out(
                <<<EOF
    ____            ___          
   / __ )___  _____/ (_)___  ____
  / __  / _ \/ ___/ / / __ \/_  /
 / /_/ /  __/ /  / / / /_/ / / /_
/_____/\___/_/  /_/_/\____/ /___/
EOF
            )
            ->br()
            ->out('Berlioz CLI')
            ->br();

        if (null !== $commandName) {
            $console->backgroundRed()->card(sprintf('Command "%s" does not exists.', $commandName))->br();
        }

        // Usage
        $console
            ->yellow('Usage:')
            ->out('  command [options] [arguments]')
            ->br();

        // Commands list
        if (0 === count($this->commandManager)) {
            $console->backgroundLightYellow()->black()->card('No command defined in configuration.')->br();
            return;
        }

        $console->yellow('Available commands:');

        $commandsSummary = [];
        foreach ($this->commandManager->getCommands() as $command) {
            $commandsSummary[] = [
                'name' => $command->getName(),
                'description' => call_user_func([$command->getClass(), 'getDescription']),
            ];
        }

        $maxLength = array_map(fn($commandSummary) => mb_strlen($commandSummary['name']), $commandsSummary);
        $maxLength = max($maxLength);

        foreach ($commandsSummary as $commandSummary) {
            $console
                ->inline('  ')
                ->padding($maxLength)->char(' ')
                ->label(sprintf('<green>%s</green>', $commandSummary['name']))
                ->result($commandSummary['description'] ?? '');
        }
    }
}