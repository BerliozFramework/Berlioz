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

use Berlioz\Config\Adapter\YamlAdapter;
use Berlioz\Config\Exception\ConfigException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

#[RequiresPhpExtension('yaml')]
class YamlAdapterTest extends TestCase
{
    public static function parserProvider(): array
    {
        return [
            [YamlAdapter::PARSER_AUTO],
            [YamlAdapter::PARSER_EXTENSION],
            [YamlAdapter::PARSER_SYMFONY],
        ];
    }

    /**
     * @throws ConfigException
     */
    #[DataProvider('parserProvider')]
    public function testLoadString(int $parser)
    {
        $yml = <<<EOF
qux: value1

section:
  foo: value
  qux: value2

section2:
  bar: value3
EOF;

        $adapter = new YamlAdapter($yml, forceParser: $parser);

        $this->assertEquals('value1', $adapter->get('qux'));
        $this->assertEquals('value', $adapter->get('section.foo'));
        $this->assertEquals('value2', $adapter->get('section.qux'));
        $this->assertEquals(['bar' => 'value3'], $adapter->get('section2'));
        $this->assertEquals('bar', $adapter->get('foo', 'bar'));
    }

    /**
     * @throws ConfigException
     */
    #[DataProvider('parserProvider')]
    public function testLoadStringFailed(int $parser)
    {
        $this->expectException(ConfigException::class);

        $yml = <<<EOF
&
qux: value1
EOF;
        new YamlAdapter($yml, forceParser: $parser);
    }

    /**
     * @throws ConfigException
     */
    #[DataProvider('parserProvider')]
    public function testLoadFile(int $parser)
    {
        $adapter = new YamlAdapter(__DIR__ . '/config.yml', true, forceParser: $parser);

        $this->assertEquals('value1', $adapter->get('qux'));
        $this->assertEquals('value', $adapter->get('section.foo'));
        $this->assertEquals('value2', $adapter->get('section.qux'));
        $this->assertEquals(['bar' => 'value3'], $adapter->get('section2'));
    }

    /**
     * @throws ConfigException
     */
    #[DataProvider('parserProvider')]
    public function testLoadFileFailed(int $parser)
    {
        $this->expectException(ConfigException::class);

        new YamlAdapter(__DIR__ . '/config-failed.yml', true, forceParser: $parser);
    }

    /**
     * @throws ConfigException
     */
    #[DataProvider('parserProvider')]
    public function testGetArrayCopy(int $parser)
    {
        $adapter = new YamlAdapter(
            <<<EOF
qux: value1

section:
  foo: value
  qux: value2

section2:
  bar: value3
EOF,
            forceParser: $parser
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
