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

use Berlioz\Cli\Core\App\CliApp;
use Berlioz\Cli\Core\Command\AbstractCommand;
use Berlioz\Cli\Core\Command\CommandDeclaration;
use Berlioz\Cli\Core\Console\Console;
use Berlioz\Cli\Core\Console\Environment;
use Berlioz\Cli\Core\Tests\FakeDefaultDirectories;
use Berlioz\Core\Core;
use Berlioz\Core\Tests\RestoresErrorHandler;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class AbstractCommandTest extends TestCase
{
    use RestoresErrorHandler;

    public function testGetDescription()
    {
        $this->assertNull(FakeCommand::getDescription());
    }

    public function testGetHelp()
    {
        $this->assertNull(FakeCommand::getHelp());
    }

    public function testRun()
    {
        $declaration = new CommandDeclaration('foo', FakeCommand::class, []);
        $command = new FakeCommand();
        $command->setApp(new CliApp(new Core(new FakeDefaultDirectories(), cache: false)));

        $this->assertSame(0, $command->run(new Environment(new Console(), $declaration)));
    }

    public function testGet()
    {
        $command = new FakeCommand();
        $command->setApp($app = new CliApp(new Core(new FakeDefaultDirectories(), cache: false)));
        $reflectionClass = new ReflectionClass(AbstractCommand::class);
        $reflectionMethod = $reflectionClass->getMethod('get');
        $reflectionMethod->setAccessible(true);

        $this->assertSame($app, $reflectionMethod->invoke($command, 'app'));
    }
}
