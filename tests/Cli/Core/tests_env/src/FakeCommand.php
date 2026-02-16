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

namespace Berlioz\Cli\Core\TestProject;

use Berlioz\Cli\Core\Command\AbstractCommand;
use Berlioz\Cli\Core\Command\Argument;
use Berlioz\Cli\Core\Console\Environment;

#[Argument('foo', prefix: 'f', longPrefix: 'foo')]
#[Argument('bar', prefix: 'b', longPrefix: 'bar', required: true)]
class FakeCommand extends AbstractCommand
{
    private bool $handled = false;
    private ?Environment $env = null;

    public static function getDescription(): ?string
    {
        return 'Long description';
    }

    public function run(Environment $env): int
    {
        $this->handled = true;
        $this->env = $env;

        return 1234;
    }

    public function getEnv(): ?Environment
    {
        return $this->env;
    }

    public function isHandled(): bool
    {
        return $this->handled;
    }
}
