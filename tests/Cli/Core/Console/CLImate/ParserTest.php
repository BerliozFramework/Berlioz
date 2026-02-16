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

namespace Berlioz\Cli\Core\Tests\Console\CLImate;

use Berlioz\Cli\Core\Console\CLImate\Parser;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase
{
    public function testCommand()
    {
        $parser = new Parser();
        $this->assertEquals('command', $parser->command(['exec', 'command', '-arg']));
        $this->assertEquals(['-arg'], $parser->arguments(['exec', 'command', '-arg']));

        $parser = new Parser();
        $this->assertNull($parser->command(['exec', '-arg']));
        $this->assertEquals(['-arg'], $parser->arguments(['exec', '-arg']));
    }

    public function testExecutable()
    {
        $parser = new Parser();
        $this->assertEquals('exec', $parser->executable(['exec', 'command', '-arg']));
        $this->assertEquals(['-arg'], $parser->arguments(['exec', 'command', '-arg']));
    }
}
