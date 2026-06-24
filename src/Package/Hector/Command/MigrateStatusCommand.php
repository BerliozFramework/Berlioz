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
use Berlioz\Cli\Core\Console\Environment;
use Hector\Migration\MigrationRunner;
use Throwable;

class MigrateStatusCommand extends AbstractCommand
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
        return 'Show status of Hector ORM migrations';
    }

    /**
     * @inheritDoc
     */
    public function run(Environment $env): int
    {
        try {
            $status = $this->runner->getStatus();
        } catch (Throwable $exception) {
            $env->console()->red(sprintf('Unable to read migration status: %s', $exception->getMessage()));
            return 1;
        }

        if ([] === $status) {
            $env->console()->yellow('No migration found.');
            return 0;
        }

        $rows = [];
        foreach ($status as $id => $applied) {
            $rows[] = [
                'Migration' => $id,
                'Status' => $applied ? 'applied' : 'pending',
            ];
        }

        $env->console()->table($rows);

        return 0;
    }
}
