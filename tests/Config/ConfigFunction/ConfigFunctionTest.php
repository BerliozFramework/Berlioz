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

use Berlioz\Config\Adapter\JsonAdapter;
use Berlioz\Config\Config;
use Berlioz\Config\ConfigFunction\ConfigFunction;
use LogicException;
use PHPUnit\Framework\TestCase;

class ConfigFunctionTest extends TestCase
{
    public function testGetName()
    {
        $function = new ConfigFunction(new Config());

        $this->assertEquals('config', $function->getName());
    }

    public function testExecute()
    {
        $config = new Config([new JsonAdapter(__DIR__ . '/../Adapter/config.json5', true)]);
        $function = new ConfigFunction($config);

        $this->assertEquals('value', $function->execute('section.foo'));
        $this->assertNull($function->execute('section2.baz'));
    }

    public function testExecuteFailed()
    {
        $this->expectException(LogicException::class);

        $config = new Config([new JsonAdapter(__DIR__ . '/../Adapter/config.json5', true)]);
        $function = new ConfigFunction($config);
        $function->execute('foo.section');
    }
}
