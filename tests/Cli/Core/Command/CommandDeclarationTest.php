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
use Berlioz\Cli\Core\Command\CommandDeclaration;
use PHPUnit\Framework\TestCase;
use stdClass;

class CommandDeclarationTest extends TestCase
{
    public function testGetName()
    {
        $declaration = new CommandDeclaration($name = 'foo:bar', stdClass::class);
        $this->assertEquals($name, $declaration->getName());
    }

    public function testGetClass()
    {
        $declaration = new CommandDeclaration('foo:bar', $class = stdClass::class);
        $this->assertEquals($class, $declaration->getClass());
    }

    public function testGetArguments()
    {
        $declaration = new CommandDeclaration('foo:bar', stdClass::class);
        $this->assertEmpty($declaration->getArguments());

        $declaration = new CommandDeclaration(
            'foo:bar',
            stdClass::class,
            $arguments = [
                new Argument('foo'),
                new Argument('bar', required: true),
            ]
        );
        $this->assertEquals($arguments, $declaration->getArguments());
    }

    public function testGetArguments_filtered()
    {
        $declaration = new CommandDeclaration(
            'foo:bar',
            stdClass::class,
            [
                $optionalArgument = new Argument('foo'),
                $requiredArgument = new Argument('bar', required: true),
            ]
        );

        $this->assertEquals([$optionalArgument], $declaration->getArguments(false));
        $this->assertEquals([$requiredArgument], $declaration->getArguments(true));
    }
}
