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

namespace Berlioz\Core\Tests\Composer;

use Berlioz\Core\Composer\Package;
use PHPUnit\Framework\TestCase;

class PackageTest extends TestCase
{
    public function testGetName()
    {
        $package = new Package(name: $name = 'berlioz');

        $this->assertEquals($name, $package->getName());
    }

    public function testGetVersion_default()
    {
        $package = new Package('berlioz');

        $this->assertNull($package->getVersion());
    }

    public function testGetVersion()
    {
        $package = new Package(name: 'berlioz', version: $version = '1.0');

        $this->assertEquals($version, $package->getVersion());
    }

    public function testGetType_default()
    {
        $package = new Package(name: 'berlioz');

        $this->assertEquals(Package::DEFAULT_TYPE, $package->getType());
    }

    public function testGetType()
    {
        $package = new Package(name: 'berlioz', type: $type = 'typeOfLib');

        $this->assertEquals($type, $package->getType());
    }

    public function testGetDescription_default()
    {
        $package = new Package(name: 'berlioz');

        $this->assertNull($package->getDescription());
    }

    public function testGetDescription()
    {
        $package = new Package(name: 'berlioz', description: $description = 'Foo bar');

        $this->assertEquals($description, $package->getDescription());
    }

    public function testGetConfig_default()
    {
        $package = new Package(name: 'berlioz');

        $this->assertIsArray($package->getConfig());
        $this->assertEmpty($package->getConfig());
    }

    public function testGetConfig()
    {
        $package = new Package(name: 'berlioz', config: $config = ['foo' => ['bar' => ['baz']], 'qux' => 'quux']);

        $this->assertEquals($config, $package->getConfig());
        $this->assertEquals(['baz'], $package->getConfig('foo.bar'));
        $this->assertEquals('foo', $package->getConfig('bar', 'foo'));
    }
}
