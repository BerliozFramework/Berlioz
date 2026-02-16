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

use Berlioz\Cli\Core\Console\CLImate\TerminalObject\Card;
use League\CLImate\Util\UtilFactory;
use PHPUnit\Framework\TestCase;

class CardTest extends TestCase
{
    public function testResult()
    {
        $card = new Card(
            <<<EOF
Horum adventum praedocti speculationibus fidis rectores militum tessera data sollemni armatos omnes celeri eduxere procursu et agiliter praeterito Calycadni fluminis ponte, cuius undarum magnitudo murorum adluit turres, in speciem locavere pugnandi. neque tamen exiluit quisquam nec permissus est congredi. formidabatur enim flagrans vesania manus et superior numero et ruitura sine respectu salutis in ferrum.

Homines enim eruditos et sobrios ut infaustos et inutiles vitant, eo quoque accedente quod et nomenclatores adsueti haec et talia venditare, mercede accepta lucris quosdam et prandiis inserunt subditicios ignobiles et obscuros.
EOF
        );
        $card->util(new UtilFactory());

        $result = $card->result();

        $this->assertMatchesRegularExpression('/^\s+$/', $firstLine = array_shift($result));
        $this->assertMatchesRegularExpression('/^\s+$/', $lastLine = array_pop($result));
        $this->assertEquals(strlen($firstLine), strlen($lastLine));

        foreach ($result as $line) {
            $this->assertTrue(str_starts_with($line, '  '));
            $this->assertTrue(str_ends_with($line, '  '));
        }
    }
}
