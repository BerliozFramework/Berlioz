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

use Berlioz\Config\ConfigFunction\EnvFunction;
use LogicException;
use PHPUnit\Framework\TestCase;

class EnvFunctionTest extends TestCase
{
    public function testGetName()
    {
        $function = new EnvFunction();

        $this->assertEquals('env', $function->getName());
    }

    public function testExecute()
    {
        putenv('FOO=bar');
        $function = new EnvFunction();

        $this->assertEquals('bar', $function->execute('FOO'));
    }

    public function testExecuteFailed()
    {
        $this->expectException(LogicException::class);

        $function = new EnvFunction();
        $function->execute('BAR');
    }
}
