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

namespace Berlioz\Cli\Core\Tests\Command;

use Berlioz\Cli\Core\Command\AbstractCommand;
use Berlioz\Cli\Core\Console\Environment;
use RuntimeException;

class Fake2Command extends AbstractCommand
{
    public static bool $handled = false;

    /**
     * @inheritDoc
     */
    public function run(Environment $env): int
    {
        throw new RuntimeException('Fake exception', previous: new RuntimeException('Fake 2 exception'));
    }
}