<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2024 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Berlioz\Package\QueueManager\Tests\Handler;

use Berlioz\Cli\Core\Command\Argument;
use Berlioz\Cli\Core\Command\CommandDeclaration;
use Berlioz\Cli\Core\Command\CommandManager;
use Berlioz\Cli\Core\Console\Console;
use Berlioz\Core\Core;
use Berlioz\Core\Tests\RestoresErrorHandler;
use Berlioz\Package\QueueManager\Handler\BerliozCommandJobHandler;
use Berlioz\Package\QueueManager\TestProject\TestEnvDirectories;
use Berlioz\Package\QueueManager\Tests\FakeCommand;
use Berlioz\Package\QueueManager\Tests\FakeJob;
use PHPUnit\Framework\TestCase;

class BerliozCommandJobHandlerTest extends TestCase
{
    use RestoresErrorHandler;

    public function testHandle()
    {
        $this->expectNotToPerformAssertions();

        $core = new Core(new TestEnvDirectories(), false);
        $handler = new class($core, $manager = new CommandManager(), $console = new Console()) extends BerliozCommandJobHandler {
            public function __construct(
                Core $core,
                CommandManager $commandManager,
                protected Console|null $console = null
            ) {
                parent::__construct($core, $commandManager);
            }

            protected function newConsole(): Console
            {
                if (null !== $this->console) {
                    return $this->console;
                }
                return parent::newConsole();
            }
        };
        $manager->addCommand(new CommandDeclaration(
            'test',
            FakeCommand::class,
            [
                new Argument('arg1', null, 'arg1'),
                new Argument('v', 'v', noValue: true),
            ]
        ));

        $handler->handle(new FakeJob(
            'foo',
            'berlioz:command',
            0,
            ['command' => ['test', '--arg1', 'value1', '-v', '-v2', 'value2']],
        ));
    }
}
