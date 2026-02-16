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

namespace Berlioz\Cli\Core\Tests\Console;

use Berlioz\Cli\Core\Console\CLImate\ArgumentsManager;
use Berlioz\Cli\Core\Console\Console;
use Berlioz\Cli\Core\Exception\CliException;
use League\CLImate\Argument\Manager;
use PHPUnit\Framework\TestCase;

class ConsoleTest extends TestCase
{
    public function testGetArgumentsManager()
    {
        $console = new Console();

        $this->assertInstanceOf(ArgumentsManager::class, $console->getArgumentsManager());
    }

    public function testGetArgumentsManager_redefined()
    {
        $this->expectException(CliException::class);

        $console = new Console();
        $console->setArgumentManager(new Manager());
        $console->getArgumentsManager();
    }
}
