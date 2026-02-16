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

use Berlioz\Config\ConfigFunction\ConfigFunctionInterface;
use Berlioz\Config\ConfigFunction\ConfigFunctionSet;
use Berlioz\Config\ConfigFunction\ConstantFunction;
use Berlioz\Config\ConfigFunction\EnvFunction;
use Berlioz\Config\Exception\ConfigException;
use LogicException;
use PHPUnit\Framework\TestCase;

class ConfigFunctionSetTest extends TestCase
{
    public function testAll()
    {
        $set = new ConfigFunctionSet($functions = [new ConstantFunction(), new EnvFunction()]);

        $this->assertCount(2, $set->all());
        $this->assertArrayHasKey('constant', $set->all());
        $this->assertArrayHasKey('env', $set->all());
        $this->assertContainsOnlyInstancesOf(ConfigFunctionInterface::class, $set->all());
    }

    public function testAdd()
    {
        $set = new ConfigFunctionSet();

        $this->assertCount(0, $set->all());

        $set->add(new ConstantFunction(), new EnvFunction(), new EnvFunction());

        $this->assertCount(2, $set->all());
    }

    public function testHas()
    {
        $set = new ConfigFunctionSet([new ConstantFunction()]);

        $this->assertFalse($set->has('env'));
        $this->assertTrue($set->has('constant'));
    }

    public function testExecute()
    {
        defined('FOO') ?: define('FOO', 'bar');

        $set = new ConfigFunctionSet([new ConstantFunction()]);
        $this->assertEquals('bar', $set->execute('constant', 'FOO'));
    }

    public function testExecuteFailed()
    {
        $this->expectException(LogicException::class);

        $set = new ConfigFunctionSet([new ConstantFunction()]);
        $set->execute('constant', 'BAR');
    }

    public function testExecuteNonexistentFunction()
    {
        $this->expectException(ConfigException::class);

        $set = new ConfigFunctionSet();
        $set->execute('constant', 'FOO');
    }
}
