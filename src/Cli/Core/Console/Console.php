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

namespace Berlioz\Cli\Core\Console;

use Berlioz\Cli\Core\Console\CLImate\ArgumentsManager;
use Berlioz\Cli\Core\Console\CLImate\TerminalObject;
use Berlioz\Cli\Core\Exception\CliException;
use League\CLImate\CLImate;

/**
 * Class Console.
 *
 * @method static card(string $str)
 * @method static tab($count = 1, string $char = '  ')
 */
class Console extends CLImate
{
    public function __construct()
    {
        parent::__construct();
        $this->setArgumentManager(new ArgumentsManager());

        // Extensions
        $this->extend(TerminalObject\Card::class, 'card');
        $this->extend(TerminalObject\Tab::class, 'tab');
    }

    /**
     * Get arguments manager.
     *
     * @return ArgumentsManager
     * @throws CliException
     */
    public function getArgumentsManager(): ArgumentsManager
    {
        if (!($this->arguments instanceof ArgumentsManager)) {
            throw new CliException('Bad arguments manager');
        }

        return $this->arguments;
    }
}