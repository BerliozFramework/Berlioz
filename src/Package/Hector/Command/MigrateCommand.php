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

#[Argument('steps', longPrefix: 'steps', description: 'Number of pending migrations to apply', castTo: 'int')]
#[Argument('dryRun', longPrefix: 'dry-run', description: 'Compile and dispatch without executing', noValue: true, castTo: 'bool')]
#[Argument('interactive', prefix: 'i', longPrefix: 'interactive', description: 'Ask for confirmation before each migration', noValue: true, castTo: 'bool')]
class MigrateCommand extends AbstractCommand
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
        return 'Apply pending Hector ORM migrations';
    }

    /**
     * @inheritDoc
     */
    public function run(Environment $env): int
    {
        $steps = $env->getArgument('steps') ?: null;
        $dryRun = (bool)$env->getArgument('dryRun');

        try {
            if (true === (bool)$env->getArgument('interactive')) {
                return $this->runInteractive($env, $steps, $dryRun);
            }

            $applied = $this->runner->up($steps, $dryRun);
        } catch (Throwable $exception) {
            $env->console()->red(sprintf('Migration failed: %s', $exception->getMessage()));
            return 1;
        }

        if ([] === $applied) {
            $env->console()->yellow('No pending migration to apply.');
            return 0;
        }

        foreach ($applied as $id) {
            $env->console()->green(sprintf('✓ %s', $id));
        }

        $env->console()->out(sprintf('%d migration(s) applied.', count($applied)));

        return 0;
    }

    /**
     * Run migrations one by one, asking for confirmation before each.
     *
     * @param Environment $env
     * @param int|null $steps
     * @param bool $dryRun
     *
     * @return int
     * @throws Throwable
     */
    private function runInteractive(Environment $env, ?int $steps, bool $dryRun): int
    {
        $pending = $this->runner->getPending();

        if ([] === $pending) {
            $env->console()->yellow('No pending migration to apply.');
            return 0;
        }

        $count = 0;
        foreach (array_keys($pending) as $id) {
            if (null !== $steps && $count >= $steps) {
                break;
            }

            if (false === $env->console()->confirm(sprintf('Apply migration "%s"?', $id))->confirmed()) {
                $env->console()->yellow(sprintf('Stopped before "%s".', $id));
                break;
            }

            $this->runner->up(1, $dryRun);
            $env->console()->green(sprintf('✓ %s', $id));
            $count++;
        }

        $env->console()->out(sprintf('%d migration(s) applied.', $count));

        return 0;
    }
}
