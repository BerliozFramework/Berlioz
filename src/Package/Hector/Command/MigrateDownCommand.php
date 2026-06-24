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

declare(strict_types=1);

namespace Berlioz\Package\Hector\Command;

use Berlioz\Cli\Core\Command\AbstractCommand;
use Berlioz\Cli\Core\Command\Argument;
use Berlioz\Cli\Core\Console\Environment;
use Hector\Migration\MigrationRunner;
use Throwable;

#[Argument('steps', longPrefix: 'steps', description: 'Number of applied migrations to revert', defaultValue: 1, castTo: 'int')]
#[Argument('dryRun', longPrefix: 'dry-run', description: 'Compile and dispatch without executing', noValue: true, castTo: 'bool')]
#[Argument('interactive', prefix: 'i', longPrefix: 'interactive', description: 'Ask for confirmation before each migration', noValue: true, castTo: 'bool')]
class MigrateDownCommand extends AbstractCommand
{
    public function __construct(
        private readonly MigrationRunner $runner,
    ) {
    }

    /**
     * @inheritDoc
     */
    public static function getDescription(): ?string
    {
        return 'Revert applied Hector ORM migrations';
    }

    /**
     * @inheritDoc
     */
    public function run(Environment $env): int
    {
        $steps = (int)($env->getArgument('steps') ?: 1);
        $dryRun = (bool)$env->getArgument('dryRun');

        try {
            if (true === (bool)$env->getArgument('interactive')) {
                return $this->runInteractive($env, $steps, $dryRun);
            }

            $reverted = $this->runner->down($steps, $dryRun);
        } catch (Throwable $exception) {
            $env->console()->red(sprintf('Rollback failed: %s', $exception->getMessage()));
            return 1;
        }

        if ([] === $reverted) {
            $env->console()->yellow('No migration to revert.');
            return 0;
        }

        foreach ($reverted as $id) {
            $env->console()->green(sprintf('✓ %s', $id));
        }

        $env->console()->out(sprintf('%d migration(s) reverted.', count($reverted)));

        return 0;
    }

    /**
     * Revert migrations one by one, asking for confirmation before each.
     *
     * @param Environment $env
     * @param int $steps
     * @param bool $dryRun
     *
     * @return int
     * @throws Throwable
     */
    private function runInteractive(Environment $env, int $steps, bool $dryRun): int
    {
        $applied = array_reverse($this->runner->getApplied(), true);

        if ([] === $applied) {
            $env->console()->yellow('No migration to revert.');
            return 0;
        }

        $count = 0;
        foreach (array_keys($applied) as $id) {
            if ($count >= $steps) {
                break;
            }

            if (false === $env->console()->confirm(sprintf('Revert migration "%s"?', $id))->confirmed()) {
                $env->console()->yellow(sprintf('Stopped before "%s".', $id));
                break;
            }

            $this->runner->down(1, $dryRun);
            $env->console()->green(sprintf('✓ %s', $id));
            $count++;
        }

        $env->console()->out(sprintf('%d migration(s) reverted.', $count));

        return 0;
    }
}
