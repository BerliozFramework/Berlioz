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

use Berlioz\Cli\Core\Command\CommandDeclaration;
use Berlioz\Cli\Core\Command\CommandManager;
use Berlioz\Cli\Core\Exception\CommandException;
use PHPUnit\Framework\TestCase;
use stdClass;

class CommandManagerTest extends TestCase
{
    public function testCount()
    {
        $manager = new CommandManager();
        $this->assertCount(0, $manager);

        $manager = new CommandManager(['foo' => FakeCommand::class, 'bar' => FakeCommand::class]);
        $this->assertCount(2, $manager);
    }

    public function testNewCommand()
    {
        $manager = new CommandManager();
        $declaration = $manager->newCommand($name = 'foo', $class = FakeCommand::class);

        $this->assertEquals($name, $declaration->getName());
        $this->assertEquals($class, $declaration->getClass());
    }

    public function testNewCommand_badClass()
    {
        $this->expectException(CommandException::class);

        $manager = new CommandManager();
        $manager->newCommand('foo', stdClass::class);
    }

    public function testAddCommand()
    {
        $manager = new CommandManager();
        $manager->addCommand($declaration = new CommandDeclaration('foo', FakeCommand::class));

        $this->assertSame($declaration, $manager->getCommand('foo'));
    }

    public function testAddCommands()
    {
        $manager = new CommandManager();
        $manager->addCommands(['foo' => FakeCommand::class, 'bar' => FakeCommand::class]);

        $this->assertCount(2, $manager);
        $this->assertNotNull($manager->getCommand('foo'));
        $this->assertNotNull($manager->getCommand('bar'));
    }

    public function testGetCommand()
    {
        $manager = new CommandManager();
        $manager->addCommands(['foo' => FakeCommand::class, 'bar' => FakeCommand::class]);
        $declaration = $manager->getCommand('foo');

        $this->assertNotNull($declaration);
        $this->assertEquals('foo', $declaration->getName());
        $this->assertEquals(FakeCommand::class, $declaration->getClass());
    }

    public function testGetCommands()
    {
        $manager = new CommandManager();
        $manager->addCommand($declaration1 = new CommandDeclaration('foo', FakeCommand::class));
        $manager->addCommand($declaration2 = new CommandDeclaration('bar', FakeCommand::class));

        $this->assertEquals(['foo' => $declaration1, 'bar' => $declaration2], $manager->getCommands());
    }
}
