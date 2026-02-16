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
use Berlioz\Cli\Core\Command\Berlioz\CacheClearCommand;
use Berlioz\Cli\Core\Command\CommandDeclaration;
use Berlioz\Cli\Core\Console\Console;
use Berlioz\Cli\Core\Console\Environment;
use Berlioz\Cli\Core\Tests\FakeDefaultDirectories;
use Berlioz\Core\Core;
use Berlioz\Core\Tests\RestoresErrorHandler;
use PHPUnit\Framework\TestCase;

class CacheClearCommandTest extends TestCase
{
    use RestoresErrorHandler;

    public function testGetDescription()
    {
        $this->assertNotNull(CacheClearCommand::getDescription());
    }

    public function testGetHelp()
    {
        $this->assertNull(CacheClearCommand::getHelp());
    }

    public function testClearCache()
    {
        $app = new CliApp(new Core(new FakeDefaultDirectories(), cache: false));
        $app->getCore()->getCache()->set('foo', 'bar');
        $app->getCore()->getFilesystem()->write('cache://foo', 'foo content');
        $command = new CacheClearCommand();
        $command->setApp($app);

        $this->assertEquals('bar', $app->getCore()->getCache()->get('foo'));
        $this->assertTrue($app->getCore()->getFilesystem()->fileExists('cache://foo'));

        $this->assertTrue($command->clearCache());

        $this->assertNull($app->getCore()->getCache()->get('foo'));
        $this->assertTrue($app->getCore()->getFilesystem()->fileExists('cache://foo'));
    }

    public function testClearCache_all()
    {
        $app = new CliApp(new Core(new FakeDefaultDirectories(), cache: false));
        $app->getCore()->getCache()->set('foo', 'bar');
        $app->getCore()->getFilesystem()->write('cache://foo', 'foo content');
        $app->getCore()->getFilesystem()->write('cache://bar/bar', 'bar content');
        $command = new CacheClearCommand();
        $command->setApp($app);

        $this->assertEquals('bar', $app->getCore()->getCache()->get('foo'));
        $this->assertTrue($app->getCore()->getFilesystem()->fileExists('cache://foo'));
        $this->assertTrue($app->getCore()->getFilesystem()->fileExists('cache://bar/bar'));

        $this->assertTrue($command->clearCache(true));

        $this->assertNull($app->getCore()->getCache()->get('foo'));
        $this->assertFalse($app->getCore()->getFilesystem()->fileExists('cache://foo'));
        $this->assertFalse($app->getCore()->getFilesystem()->fileExists('cache://bar/bar'));
    }

    public function testClearCache_list()
    {
        $app = new CliApp(new Core(new FakeDefaultDirectories(), cache: false));
        $app->getCore()->getCache()->set('foo', 'bar');
        $app->getCore()->getFilesystem()->write('cache://bar/bar', 'bar content');
        $app->getCore()->getFilesystem()->write('cache://baz/baz', 'baz content');
        $app->getCore()->getFilesystem()->write('cache://qux/qux', 'bux content');
        $command = new CacheClearCommand();
        $command->setApp($app);

        $this->assertEquals('bar', $app->getCore()->getCache()->get('foo'));
        $this->assertTrue($app->getCore()->getFilesystem()->fileExists('cache://bar/bar'));
        $this->assertTrue($app->getCore()->getFilesystem()->fileExists('cache://baz/baz'));
        $this->assertTrue($app->getCore()->getFilesystem()->fileExists('cache://qux/qux'));

        $this->assertTrue($command->clearCache(['bar', 'qux']));

        $this->assertNull($app->getCore()->getCache()->get('foo'));
        $this->assertFalse($app->getCore()->getFilesystem()->fileExists('cache://bar/bar'));
        $this->assertTrue($app->getCore()->getFilesystem()->fileExists('cache://baz/baz'));
        $this->assertFalse($app->getCore()->getFilesystem()->fileExists('cache://qux/qux'));
    }

    public function testRun()
    {
        $app = new CliApp(new Core(new FakeDefaultDirectories(), cache: false));
        $app->getCore()->getCache()->set('foo', 'bar');
        $command = new CacheClearCommand();
        $command->setApp($app);
        $console = new Console();
        $console->output->defaultTo('buffer');
        $console->getArgumentsManager()->add(
            'all',
            [
                'name' => 'all',
                'longPrefix' => 'all',
                'description' => 'All caches directories',
                'noValue' => true,
                'castTo' => 'bool'
            ]
        );
        $console->getArgumentsManager()->add(
            'directory',
            [
                'name' => 'directory',
                'description' => 'Directories name',
                'castTo' => 'string'
            ]
        );

        $this->assertEquals('bar', $app->getCore()->getCache()->get('foo'));
        $this->assertSame(
            0,
            $command->run(
                new Environment(
                    $console,
                    new CommandDeclaration(
                        'berlioz:cache-clear',
                        CacheClearCommand::class,
                        [
                            new Argument(
                                name: 'all',
                                longPrefix: 'all',
                                description: 'All caches directories',
                                noValue: true,
                                castTo: 'bool'
                            ),
                            new Argument(name: 'directory', description: 'Directories name', castTo: 'string')
                        ],
                    )
                )
            )
        );
        $this->assertNull($app->getCore()->getCache()->get('foo'));
    }
}
