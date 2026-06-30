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

namespace Berlioz\Cli\Core\Command\Berlioz;

use Berlioz\Cli\Core\Command\AbstractCommand;
use Berlioz\Cli\Core\Command\Argument;
use Berlioz\Cli\Core\Console\Environment;
use Berlioz\Core\Debug\SnapshotCleaner;
use Berlioz\Core\Exception\BerliozException;

/**
 * Class DebugClearCommand.
 */
#[Argument(name: 'all', longPrefix: 'all', description: 'Delete all debug reports', noValue: true, castTo: 'bool')]
#[Argument(name: 'days', longPrefix: 'days', description: 'Delete reports older than N days', castTo: 'int')]
class DebugClearCommand extends AbstractCommand
{
    /**
     * @inheritDoc
     */
    public static function getDescription(): ?string
    {
        return 'Clear debug reports of Berlioz Framework';
    }

    /**
     * @inheritDoc
     * @throws BerliozException
     */
    public function run(Environment $env): int
    {
        $core = $this->getApp()->getCore();
        $cleaner = new SnapshotCleaner($core->getFilesystem());

        $env->console()->inline('Debug reports clear... ');
        $env->console()->spinner();

        // Delete all reports
        if (true === $env->getArgument('all')) {
            $deleted = $cleaner->clear();
            $env->console()->green(sprintf('done! (%d report(s) deleted)', $deleted));

            return 0;
        }

        // Delete reports older than N days
        if (null !== ($days = $env->getArgument('days'))) {
            $deleted = $cleaner->clean((int)$days, null);
            $env->console()->green(sprintf('done! (%d report(s) deleted)', $deleted));

            return 0;
        }

        // Default: apply configured retention policy
        $config = $core->getConfig();
        $maxAge = $config->get('berlioz.debug.gc.max_age');
        $maxFiles = $config->get('berlioz.debug.gc.max_files');

        $deleted = $cleaner->clean(
            null !== $maxAge ? (int)$maxAge : null,
            null !== $maxFiles ? (int)$maxFiles : null,
        );
        $env->console()->green(sprintf('done! (%d report(s) deleted)', $deleted));

        return 0;
    }
}
