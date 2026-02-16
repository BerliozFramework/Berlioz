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

namespace Berlioz\Cli\Core\Tests\App;

use Berlioz\Cli\Core\App\CliApp;
use Berlioz\Cli\Core\TestProject\FakeCommand;
use Berlioz\Cli\Core\Tests\FakeDefaultDirectories;
use Berlioz\Core\Core;
use Berlioz\Core\Tests\RestoresErrorHandler;
use PHPUnit\Framework\TestCase;

class CliAppTest extends TestCase
{
    use RestoresErrorHandler;

    public function testHandle()
    {
        $app = new CliApp($core = new Core(new FakeDefaultDirectories(), cache: false));
        $result = $app->handle($argv = ['berlioz', 'fake:command', '-b', 'value']);

        $this->assertEquals(1234, $result);

        /** @var FakeCommand $fakeCommand */
        $fakeCommand = $core->getContainer()->get(FakeCommand::class);

        $this->assertTrue($fakeCommand->isHandled());
        $this->assertNotNull($fakeCommand->getEnv());

        $this->assertTrue($fakeCommand->getEnv()->isArgumentDefined('bar', $argv));
        $this->assertNotNull($fakeCommand->getEnv()->getArgument('foo'));
    }
}
