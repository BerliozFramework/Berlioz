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
use Berlioz\Cli\Core\Console\Environment;
use RuntimeException;

class FakeUnresolvableCommand extends AbstractCommand
{
    public static bool $instantiated = false;

    public function __construct()
    {
        self::$instantiated = true;

        throw new RuntimeException('Unavailable dependency');
    }

    /**
     * @inheritDoc
     */
    public function run(Environment $env): int
    {
        return 0;
    }
}
