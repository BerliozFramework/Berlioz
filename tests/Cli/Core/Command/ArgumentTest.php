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

namespace Berlioz\Cli\Core\Tests\Command;

use Berlioz\Cli\Core\Command\Argument;
use PHPUnit\Framework\TestCase;

class ArgumentTest extends TestCase
{
    public function testGetArrayCopy()
    {
        $argument = new Argument(
            'foo',
            prefix: 'f',
            longPrefix: 'foo',
            description: 'Foo bar',
            defaultValue: 'default',
            required: true,
            noValue: true,
            castTo: 'bool',
        );
        $this->assertEquals(
            [
                'name' => 'foo',
                'prefix' => 'f',
                'longPrefix' => 'foo',
                'description' => 'Foo bar',
                'defaultValue' => 'default',
                'required' => true,
                'noValue' => true,
                'castTo' => 'bool',
            ],
            $argument->getArrayCopy()
        );

        $argument = new Argument(
            'foo',
            prefix: 'f',
            longPrefix: 'foo',
            description: 'Foo bar',
            required: false,
        );
        $this->assertEquals(
            [
                'name' => 'foo',
                'prefix' => 'f',
                'longPrefix' => 'foo',
                'description' => 'Foo bar',
                'required' => false,
            ],
            $argument->getArrayCopy()
        );
    }

    public function testGetName()
    {
        $argument = new Argument($name = 'foo');
        $this->assertEquals($name, $argument->getName());
    }

    public function testGetPrefix()
    {
        $argument = new Argument('foo');
        $this->assertNull($argument->getPrefix());

        $argument = new Argument('foo', prefix: $prefix = 'f');
        $this->assertEquals($prefix, $argument->getPrefix());
    }

    public function testGetLongPrefix()
    {
        $argument = new Argument('foo');
        $this->assertNull($argument->getLongPrefix());

        $argument = new Argument('foo', longPrefix: $longPrefix = 'foo');
        $this->assertEquals($longPrefix, $argument->getLongPrefix());
    }

    public function testHasPrefix()
    {
        $argument = new Argument('foo');
        $this->assertFalse($argument->hasPrefix());

        $argument = new Argument('foo', longPrefix: 'foo');
        $this->assertTrue($argument->hasPrefix());

        $argument = new Argument('foo', prefix: 'f');
        $this->assertTrue($argument->hasPrefix());

        $argument = new Argument('foo', prefix: 'f', longPrefix: 'foo');
        $this->assertTrue($argument->hasPrefix());
    }

    public function testGetDescription()
    {
        $argument = new Argument('foo');
        $this->assertNull($argument->getDescription());

        $argument = new Argument('foo', description: $description = 'Foo bar');
        $this->assertEquals($description, $argument->getDescription());
    }

    public function testGetDefaultValue()
    {
        $argument = new Argument('foo');
        $this->assertNull($argument->getDefaultValue());

        $argument = new Argument('foo', defaultValue: $defaultValue = 'default');
        $this->assertEquals($defaultValue, $argument->getDefaultValue());
    }

    public function testIsRequired()
    {
        $argument = new Argument('foo');
        $this->assertFalse($argument->isRequired());

        $argument = new Argument('foo', required: true);
        $this->assertTrue($argument->isRequired());

        $argument = new Argument('foo', required: false);
        $this->assertFalse($argument->isRequired());
    }

    public function testHasNoValue()
    {
        $argument = new Argument('foo');
        $this->assertFalse($argument->hasNoValue());

        $argument = new Argument('foo', noValue: true);
        $this->assertTrue($argument->hasNoValue());

        $argument = new Argument('foo', noValue: false);
        $this->assertFalse($argument->hasNoValue());
    }

    public function testGetCastTo()
    {
        $argument = new Argument('foo');
        $this->assertNull($argument->getCastTo());

        $argument = new Argument('foo', castTo: $castTo = 'bool');
        $this->assertEquals($castTo, $argument->getCastTo());
    }
}
