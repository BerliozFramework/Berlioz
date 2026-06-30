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

namespace Berlioz\Cli\Core\Console\CLImate;

use League\CLImate\Argument\Parser as CLImateParser;

/**
 * Class Parser.
 */
class Parser extends CLImateParser
{
    /**
     * @inheritDoc
     */
    protected function getCommandAndArguments(?array $argv = null): array
    {
        // If no $argv is provided then use the global PHP defined $argv.
        if (is_null($argv)) {
            global $argv;
        }

        $arguments = $argv;
        $executable = array_shift($arguments);
        $command = array_shift($arguments);

        if (null !== $command && str_starts_with((string)$command, '-')) {
            array_unshift($arguments, $command);
            $command = null;
        }

        return compact('arguments', 'command', 'executable');
    }

    /**
     * @inheritDoc
     */
    public function command(?array $argv = null): ?string
    {
        return parent::command($argv);
    }

    /**
     * Get executable.
     *
     * @param array|null $argv
     *
     * @return string|null
     */
    public function executable(?array $argv = null): ?string
    {
        return $this->getCommandAndArguments($argv)['executable'];
    }
}
