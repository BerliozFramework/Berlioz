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

namespace Berlioz\Cli\Core\Console\CLImate\TerminalObject;

use League\CLImate\TerminalObject\Basic\Tab as CLImateTab;

/**
 * Class Tab.
 */
class Tab extends CLImateTab
{
    public function __construct($count = 1, protected string $char = "  ")
    {
        parent::__construct($count);
    }

    /**
     * @inheritDoc
     */
    public function result()
    {
        return str_repeat($this->char, $this->count);
    }
}