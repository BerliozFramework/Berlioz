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

namespace Berlioz\Cli\Core\Tests\Command\Berlioz;

use Berlioz\Cli\Core\App\CliApp;
use Berlioz\Cli\Core\Command\Argument;
use Berlioz\Cli\Core\Command\Berlioz\ConfigCommand;
use Berlioz\Cli\Core\Command\CommandDeclaration;
use Berlioz\Cli\Core\Console\Console;
use Berlioz\Cli\Core\Console\Environment;
use Berlioz\Cli\Core\Tests\FakeDefaultDirectories;
use Berlioz\Core\Core;
use Berlioz\Core\Tests\RestoresErrorHandler;
use PHPUnit\Framework\TestCase;

class ConfigCommandTest extends TestCase
{
    use RestoresErrorHandler;

    public function testGetDescription()
    {
        $this->assertNotNull(ConfigCommand::getDescription());
    }

    public function testGetHelp()
    {
        $this->assertNull(ConfigCommand::getHelp());
    }

    public function testRun()
    {
        $app = new CliApp(new Core(new FakeDefaultDirectories(), cache: false));
        $command = new ConfigCommand();
        $command->setApp($app);
        $console = new Console();
        $console->output->defaultTo('buffer');
        $console->getArgumentsManager()->add('filter', []);

        $result = $command->run(
            new Environment(
                $console,
                new CommandDeclaration('berlioz:config', ConfigCommand::class, [new Argument('filter')])
            )
        );

        $this->assertSame(0, $result);
        $this->assertStringContainsString('"berlioz": {', $console->output->get('buffer')->get());
    }
}
