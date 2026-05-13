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
use Berlioz\Cli\Core\Command\CommandHandler;
use Berlioz\Cli\Core\Command\CommandManager;
use Berlioz\Cli\Core\Console\Console;
use Berlioz\Cli\Core\Tests\FakeDefaultDirectories;
use Berlioz\Core\Core;
use Berlioz\Core\Tests\RestoresErrorHandler;
use PHPUnit\Framework\TestCase;

class CommandHandlerTest extends TestCase
{
    use RestoresErrorHandler;

    public function testHandle()
    {
        FakeCommand::$handled = false;
        $handler = new CommandHandler(
            $console = new Console(),
            $manager = new CommandManager(),
            new Core(new FakeDefaultDirectories(), cache: false)
        );
        $console->output->defaultTo('buffer');
        $manager->addCommand(new CommandDeclaration('foo', FakeCommand::class));
        $result = $handler->handle(['exec', 'foo']);

        $this->assertSame(0, $result);
        $this->assertTrue(FakeCommand::$handled);
    }

    public function testHandle_unknown()
    {
        FakeCommand::$handled = false;
        $handler = new CommandHandler(
            $console = new Console(),
            $manager = new CommandManager(),
            new Core(new FakeDefaultDirectories(), cache: false)
        );
        $console->output->defaultTo('buffer');
        $manager->addCommand(new CommandDeclaration('foo', FakeCommand::class));
        $result = $handler->handle(['exec', 'bar']);

        $this->assertSame(1, $result);
        $this->assertFalse(FakeCommand::$handled);
        $this->assertStringContainsString('Command "bar" does not exists', $console->output->get('buffer')->get());
    }

    public function testHandle_summary()
    {
        $handler = new CommandHandler(
            $console = new Console(),
            $manager = new CommandManager(),
            new Core(new FakeDefaultDirectories(), cache: false)
        );
        $console->output->defaultTo('buffer');
        $manager->addCommand(new CommandDeclaration('foo', FakeCommand::class));
        $result = $handler->handle(['exec']);

        $this->assertSame(0, $result);
        $this->assertStringContainsString('Available commands', $console->output->get('buffer')->get());
    }

    public function testHandle_helpCommand()
    {
        $handler = new CommandHandler(
            $console = new Console(),
            $manager = new CommandManager(),
            new Core(new FakeDefaultDirectories(), cache: false)
        );
        $console->output->defaultTo('buffer');
        $manager->addCommand(new CommandDeclaration('foo', FakeCommand::class));
        $result = $handler->handle(['exec', 'foo', '--help']);

        $this->assertSame(0, $result);
        $this->assertStringContainsString('Usage:', $console->output->get('buffer')->get());
        $this->assertStringContainsString('  foo', $console->output->get('buffer')->get());
    }

    public function testHandle_withPositionalArgument()
    {
        FakePositionalCommand::$handled = false;
        $handler = new CommandHandler(
            $console = new Console(),
            $manager = new CommandManager(),
            new Core(new FakeDefaultDirectories(), cache: false)
        );
        $console->output->defaultTo('buffer');
        $manager->addCommand(new CommandDeclaration('foo', FakePositionalCommand::class));

        // failOnDeprecation="true" in phpunit.xml.dist will fail this test
        // if league/climate's null-as-array-offset deprecation re-appears.
        $result = $handler->handle(['exec', 'foo']);

        $this->assertSame(0, $result);
        $this->assertTrue(FakePositionalCommand::$handled);
    }

    public function testHandle_commandFailed()
    {
        $handler = new CommandHandler(
            $console = new Console(),
            $manager = new CommandManager(),
            $core = new Core(new FakeDefaultDirectories(), cache: false)
        );
        $console->output->defaultTo('buffer');
        $console->output->add('error', $console->output->get('buffer'));
        $manager->addCommand(new CommandDeclaration('foo', Fake2Command::class));
        $result = $handler->handle(['exec', 'foo']);

        $this->assertSame(1, $result);
        $this->assertStringContainsString('[RuntimeException]', $console->output->get('buffer')->get());
        $this->assertStringNotContainsString('Stack trace', $console->output->get('buffer')->get());

        // With debug
        $core->getDebug()->setEnabled(true);
        $result = $handler->handle(['exec', 'foo']);

        $this->assertSame(1, $result);
        $this->assertStringContainsString('[RuntimeException]', $console->output->get('buffer')->get());
        $this->assertStringContainsString('Stack trace', $console->output->get('buffer')->get());
    }
}
