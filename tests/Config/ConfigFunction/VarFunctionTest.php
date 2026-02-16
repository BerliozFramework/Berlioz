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

use Berlioz\Config\Config;
use Berlioz\Config\ConfigFunction\VarFunction;
use LogicException;
use PHPUnit\Framework\TestCase;

class VarFunctionTest extends TestCase
{
    public function testGetName()
    {
        $function = new VarFunction(new Config());

        $this->assertEquals('var', $function->getName());
    }

    public function testExecute()
    {
        $config = new Config(variables: ['FOO' => 'bar']);
        $function = new VarFunction($config);

        $this->assertEquals('bar', $function->execute('FOO'));
    }

    public function testExecuteFailed()
    {
        $this->expectException(LogicException::class);

        $function = new VarFunction(new Config());
        $function->execute('FOO');
    }
}
