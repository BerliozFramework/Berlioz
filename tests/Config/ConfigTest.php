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

namespace Berlioz\Config\Tests;

use ArrayObject;
use Berlioz\Config\Adapter\JsonAdapter;
use Berlioz\Config\Config;
use Berlioz\Config\ConfigFunction\EnvFunction;
use Berlioz\Config\Exception\ConfigException;
use Berlioz\Config\Tests\ConfigFunction\FakeFunction;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public function test__construct()
    {
        $config = new FakeConfig();

        $this->assertEmpty($config->all());
        $this->assertEmpty($config->getVariables());

        $config = new FakeConfig(configs: $configs = [new JsonAdapter('{}')]);

        $this->assertSame($configs, $config->all());
        $this->assertEmpty($config->getVariables());

        $config = new FakeConfig(variables: ['foo' => 'bar']);

        $this->assertEmpty($config->all());
        $this->assertNotEmpty($config->getVariables());
        $this->assertTrue($config->getVariables()->offsetExists('foo'));
    }

    public function testGetVariables()
    {
        $config = new Config();

        $this->assertInstanceOf(ArrayObject::class, $config->getVariables());
        $this->assertSame($config->getVariables(), $config->getVariables());
    }

    public function testAddFunction()
    {
        $config = new FakeConfig();

        $this->assertCount(5, $config->getFunctions()->all());

        $config->addFunction(new FakeFunction(), new EnvFunction());

        $this->assertCount(6, $config->getFunctions()->all());
    }

    public function testAddConfig()
    {
        $config = new FakeConfig(configs: $configs = [new JsonAdapter('{}', priority: 10)]);

        $this->assertCount(1, $config->all());
        $this->assertSame($configs, $config->all());

        $config->addConfig($aDefaultConfig = new JsonAdapter('{}'));

        $this->assertCount(2, $config->all());
        $this->assertSame(array_merge($configs, [$aDefaultConfig]), $config->all());

        $config->addConfig($aPrioritizedConfig = new JsonAdapter('{}', priority: 100));

        $this->assertCount(3, $config->all());
        $this->assertSame(array_merge([$aPrioritizedConfig], $configs, [$aDefaultConfig]), $config->all());
    }

    public function testGetOrFail_valid()
    {
        $config = new Config([new JsonAdapter(__DIR__ . '/config.json5', true)]);

        $this->assertEquals('STRING', $config->getOrFail('bar'));
    }

    public function testGetOrFail_empty()
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Missing configuration value at "section2.baz"');

        $config = new Config([new JsonAdapter(__DIR__ . '/config.json5', true)]);
        $config->getOrFail('section2.baz');
    }

    public function testGetOrFail_unknown()
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Missing configuration value at "baz.unknown.foo"');

        $config = new Config([new JsonAdapter(__DIR__ . '/config.json5', true)]);
        $config->getOrFail('baz.unknown.foo');
    }

    public function testGet()
    {
        $config = new FakeConfig(
            [
                new JsonAdapter(__DIR__ . '/config.json5', true, priority: 0),
                new JsonAdapter(__DIR__ . '/config3.json5', true, priority: 10),
                new JsonAdapter(__DIR__ . '/config2.json5', true, priority: 1),
            ],
            variables: ['QUX' => 'QUX QUX QUX']
        );

        $this->assertEquals('ERASE ARRAY', $config->get('foo'));
        $this->assertEquals(['ERASE STRING'], $config->get('bar'));
        $this->assertEquals('QUX VALUE', $config->get('qux'));
        $this->assertEquals('value-test', $config->get('section2.bar'));
        $this->assertSame(123456, $config->get('section2.qux'));
        $this->assertEquals(['value2', '{not}', 'QUX QUX QUX', 'QUX QUX QUX'], $config->get('section.qux'));
        $this->assertEquals(
            ['bar' => 'value-test', 'baz' => 123456, 'qux' => 123456, '123' => 'FOO'],
            $config->get('section2')
        );
        $this->assertSame(true, $config->get('baz'));
    }

    public function testHas()
    {
        $config = new FakeConfig(
            [
                new JsonAdapter(__DIR__ . '/config.json5', true, priority: 0),
                new JsonAdapter(__DIR__ . '/config3.json5', true, priority: 10),
                new JsonAdapter(__DIR__ . '/config2.json5', true, priority: 1),
            ],
            variables: ['QUX' => 'QUX QUX QUX']
        );

        $this->assertTrue($config->has('baz'));
        $this->assertTrue($config->has('section.qux'));
        $this->assertFalse($config->has('babar'));
    }

    public function testGetArrayCopy()
    {
        $config = new FakeConfig(
            [
                new JsonAdapter(__DIR__ . '/config.json5', true, priority: 0),
                new JsonAdapter(__DIR__ . '/config3.json5', true, priority: 10),
                new JsonAdapter(__DIR__ . '/config2.json5', true, priority: 1),
            ],
            variables: ['QUX' => 'QUX QUX QUX']
        );
        $array = [
            "foo" => "ERASE ARRAY",
            "bar" => ["ERASE STRING"],
            "qux" => "QUX VALUE",
            "section" => [
                "foo" => "value",
                "qux" => [
                    "value2",
                    "{not}",
                    "{var:QUX}",
                    "{= QUX}",
                ]
            ],
            "section2" => [
                "bar" => "{config:section.foo}-test",
                "baz" => 123456,
                "qux" => "{config:section2.baz}",
                "123" => "FOO",
            ],
            "baz" => true
        ];

        $this->assertEquals($array, $config->getArrayCopy());
    }

    public function testGetArrayCopy_compiled()
    {
        $config = new FakeConfig(
            [
                new JsonAdapter(__DIR__ . '/config.json5', true, priority: 0),
                new JsonAdapter(__DIR__ . '/config3.json5', true, priority: 10),
                new JsonAdapter(__DIR__ . '/config2.json5', true, priority: 1),
            ],
            variables: ['QUX' => 'QUX QUX QUX']
        );
        $array = [
            "foo" => "ERASE ARRAY",
            "bar" => ["ERASE STRING"],
            "qux" => "QUX VALUE",
            "section" => [
                "foo" => "value",
                "qux" => [
                    "value2",
                    "{not}",
                    "QUX QUX QUX",
                    "QUX QUX QUX",
                ]
            ],
            "section2" => [
                "bar" => "value-test",
                "baz" => 123456,
                "qux" => 123456,
                "123" => "FOO",
            ],
            "baz" => true
        ];

        $this->assertEquals($array, $config->getArrayCopy(true));
    }

    public function testGetArrayCopy_empty()
    {
        $config = new FakeConfig();

        $this->assertEquals([], $config->getArrayCopy(true));
    }
}
