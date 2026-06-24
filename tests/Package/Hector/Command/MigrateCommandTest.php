<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2026 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Berlioz\Package\Hector\Tests\Command;

use Berlioz\Cli\Core\Command\Argument;
use Berlioz\Cli\Core\Command\CommandDeclaration;
use Berlioz\Cli\Core\Console\Console;
use Berlioz\Cli\Core\Console\Environment;
use Berlioz\Package\Hector\Command\MigrateCommand;
use Hector\Migration\MigrationRunner;
use PHPUnit\Framework\TestCase;

class MigrateCommandTest extends TestCase
{
    private function environment(array $argv = []): Environment
    {
        $console = new Console();
        $console->output->defaultTo('buffer');
        $console->getArgumentsManager()->add('steps', ['longPrefix' => 'steps', 'castTo' => 'int']);
        $console->getArgumentsManager()->add(
            'dryRun',
            ['longPrefix' => 'dry-run', 'noValue' => true, 'castTo' => 'bool'],
        );
        $console->getArgumentsManager()->add(
            'interactive',
            ['prefix' => 'i', 'longPrefix' => 'interactive', 'noValue' => true, 'castTo' => 'bool'],
        );
        if ([] !== $argv) {
            $console->getArgumentsManager()->parse(array_merge(['command'], $argv));
        }

        return new Environment(
            $console,
            new CommandDeclaration(
                'hector:migrate',
                MigrateCommand::class,
                [
                    new Argument(name: 'steps', longPrefix: 'steps', castTo: 'int'),
                    new Argument(name: 'dryRun', longPrefix: 'dry-run', noValue: true, castTo: 'bool'),
                    new Argument(
                        name: 'interactive',
                        prefix: 'i',
                        longPrefix: 'interactive',
                        noValue: true,
                        castTo: 'bool',
                    ),
                ],
            ),
            $argv,
        );
    }

    public function testGetDescription(): void
    {
        $this->assertNotNull(MigrateCommand::getDescription());
    }

    public function testRunWithAppliedMigrations(): void
    {
        $runner = $this->createMock(MigrationRunner::class);
        $runner->expects($this->once())
            ->method('up')
            ->with(null, false)
            ->willReturn(['20260101000000_CreateUsers']);

        $command = new MigrateCommand($runner);

        $this->assertSame(0, $command->run($this->environment()));
    }

    public function testRunWithNoPendingMigration(): void
    {
        $runner = $this->createMock(MigrationRunner::class);
        $runner->expects($this->once())
            ->method('up')
            ->willReturn([]);

        $command = new MigrateCommand($runner);

        $this->assertSame(0, $command->run($this->environment()));
    }

    public function testRunFailure(): void
    {
        $runner = $this->createMock(MigrationRunner::class);
        $runner->expects($this->once())
            ->method('up')
            ->willThrowException(new \RuntimeException('boom'));

        $command = new MigrateCommand($runner);

        $this->assertSame(1, $command->run($this->environment()));
    }

    public function testRunDryRunDelegatesToRunner(): void
    {
        $runner = $this->createMock(MigrationRunner::class);
        $runner->expects($this->once())
            ->method('up')
            ->with(null, true)
            ->willReturn(['20260101000000_CreateUsers']);

        $command = new MigrateCommand($runner);

        $this->assertSame(0, $command->run($this->environment(['--dry-run'])));
    }

    public function testRunInteractiveWithNoPendingMigration(): void
    {
        $runner = $this->createMock(MigrationRunner::class);
        $runner->expects($this->once())
            ->method('getPending')
            ->willReturn([]);
        // No pending migration: up() must never be called, no confirmation prompt.
        $runner->expects($this->never())->method('up');

        $command = new MigrateCommand($runner);

        $this->assertSame(0, $command->run($this->environment(['--interactive'])));
    }
}
