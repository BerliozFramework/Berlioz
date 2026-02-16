<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2024 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Berlioz\Package\QueueManager\Tests;

use Berlioz\Cli\Core\Command\AbstractCommand;
use Berlioz\Cli\Core\Console\Environment;
use Berlioz\QueueManager\Exception\QueueException;

class FakeCommand extends AbstractCommand
{
    public function __construct()
    {
    }

    /**
     * @inheritDoc
     */
    public function run(Environment $env): int
    {
        $env->isArgumentDefined('arg1') || throw new QueueException('Fail test argument "arg1" defined');
        $env->isArgumentDefined('v') || throw new QueueException('Fail test argument "v" defined');

        return 0;
    }
}