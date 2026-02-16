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

namespace Berlioz\Cli\Core\Tests\Console\CLImate;

use Berlioz\Cli\Core\Console\CLImate\ArgumentsManager;
use Berlioz\Cli\Core\Console\CLImate\Parser;
use League\CLImate\Argument\Manager;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class ArgumentsManagerTest extends TestCase
{
    public function test__construct()
    {
        $manager = new ArgumentsManager();
        $reflectionProperty = new ReflectionProperty(Manager::class, 'parser');
        $reflectionProperty->setAccessible(true);

        $this->assertInstanceOf(Parser::class, $reflectionProperty->getValue($manager));
    }

    public function testReset()
    {
        $reflectionProperty = new ReflectionProperty(Manager::class, 'description');
        $reflectionProperty->setAccessible(true);

        $manager = new ArgumentsManager();
        $manager->add('foo', []);
        $manager->description('Foo bar');

        $this->assertCount(1, $manager->all());
        $this->assertNotEmpty($reflectionProperty->getValue($manager));

        $manager->reset();

        $this->assertCount(0, $manager->all());
        $this->assertEmpty($reflectionProperty->getValue($manager));
    }

    public function testGetCommandName()
    {
        $manager = new ArgumentsManager();

        $this->assertEquals('command', $manager->getCommandName(['exec', 'command']));
        $this->assertNull($manager->getCommandName(['exec', '--arg']));
    }
}
