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

namespace Berlioz\Config\Tests\Adapter;

use Berlioz\Config\Adapter\IniAdapter;
use Berlioz\Config\Exception\ConfigException;
use PHPUnit\Framework\TestCase;

class IniAdapterTest extends TestCase
{
    public function testLoadString()
    {
        $ini = <<<EOF
qux= value1

[section]
foo= value
qux = value2

[section2]
bar = value3
EOF;

        $adapter = new IniAdapter($ini);

        $this->assertEquals('value1', $adapter->get('qux'));
        $this->assertEquals('value', $adapter->get('section.foo'));
        $this->assertEquals('value2', $adapter->get('section.qux'));
        $this->assertEquals(['bar' => 'value3'], $adapter->get('section2'));
        $this->assertEquals('bar', $adapter->get('foo', 'bar'));
    }

    public function testLoadStringFailed()
    {
        $this->expectException(ConfigException::class);

        $ini = <<<EOF
&
qux= value1
EOF;
        new IniAdapter($ini);
    }

    public function testLoadFile()
    {
        $adapter = new IniAdapter(__DIR__ . '/config.ini', true);

        $this->assertEquals('value1', $adapter->get('qux'));
        $this->assertEquals('value', $adapter->get('section.foo'));
        $this->assertEquals('value2', $adapter->get('section.qux'));
        $this->assertEquals(['bar' => 'value3'], $adapter->get('section2'));
    }

    public function testLoadFileFailed()
    {
        $this->expectException(ConfigException::class);

        new IniAdapter(__DIR__ . '/config-failed.ini', true);
    }

    public function testGetArrayCopy()
    {
        $adapter = new IniAdapter(
            <<<EOF
qux= value1

[section]
foo= value
qux = value2

[section2]
bar = value3
EOF
        );
        $array = [
            "qux" => "value1",
            "section" => [
                "foo" => "value",
                "qux" => "value2"
            ],
            "section2" => [
                "bar" => "value3"
            ],
        ];

        $this->assertEquals($array, $adapter->getArrayCopy());
    }
}
