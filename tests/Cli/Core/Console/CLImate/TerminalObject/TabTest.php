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

namespace Berlioz\Cli\Core\Tests\Console\CLImate\TerminalObject;

use Berlioz\Cli\Core\Console\CLImate\TerminalObject\Tab;
use PHPUnit\Framework\TestCase;

class TabTest extends TestCase
{
    public function testResult()
    {
        $tab = new Tab();
        $this->assertEquals('  ', $tab->result());

        $tab = new Tab(2);
        $this->assertEquals('    ', $tab->result());

        $tab = new Tab(2, '-');
        $this->assertEquals('--', $tab->result());
    }
}
