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

use Berlioz\Config\ConfigFunction\ConstantFunction;
use LogicException;
use PHPUnit\Framework\TestCase;

class ConstantFunctionTest extends TestCase
{
    public function testGetName()
    {
        $function = new ConstantFunction();

        $this->assertEquals('constant', $function->getName());
    }

    public function testExecute()
    {
        defined('FOO') ?: define('FOO', 'bar');
        $function = new ConstantFunction();

        $this->assertEquals('bar', $function->execute('FOO'));
    }

    public function testExecuteFailed()
    {
        $this->expectException(LogicException::class);

        $function = new ConstantFunction();
        $function->execute('BAR');
    }
}
