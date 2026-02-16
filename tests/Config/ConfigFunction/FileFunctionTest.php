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

namespace Berlioz\Config\Tests\ConfigFunction;

use Berlioz\Config\ConfigFunction\FileFunction;
use LogicException;
use PHPUnit\Framework\TestCase;

class FileFunctionTest extends TestCase
{
    public function testGetName()
    {
        $function = new FileFunction();

        $this->assertEquals('file', $function->getName());
    }

    public function testExecute()
    {
        $function = new FileFunction();

        $this->assertEquals('FOO', $function->execute(__DIR__ . '/file'));
    }

    public function testExecuteFailed()
    {
        $this->expectException(LogicException::class);

        $function = new FileFunction();
        $function->execute('file_unknown');
    }
}
