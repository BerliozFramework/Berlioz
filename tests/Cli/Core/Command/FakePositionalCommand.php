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

namespace Berlioz\Cli\Core\Tests\Command;

use Berlioz\Cli\Core\Command\AbstractCommand;
use Berlioz\Cli\Core\Command\Argument;
use Berlioz\Cli\Core\Console\Environment;

#[Argument(name: 'directory', description: 'Positional argument')]
class FakePositionalCommand extends AbstractCommand
{
    public static bool $handled = false;

    /**
     * @inheritDoc
     */
    public function run(Environment $env): int
    {
        self::$handled = true;

        return 0;
    }
}
