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

namespace Berlioz\Cli\Core\Console\CLImate;

use League\CLImate\Argument\Manager;

class ArgumentsManager extends Manager
{
    public function __construct()
    {
        parent::__construct();
        $this->parser = new Parser();
    }

    /**
     * Reset.
     */
    public function reset(): void
    {
        $this->arguments = [];
        $this->description = '';
    }

    /**
     * Get command name.
     *
     * @param array|null $argv
     *
     * @return string|null
     */
    public function getCommandName(?array $argv = null): ?string
    {
        return $this->parser->command($argv);
    }
}