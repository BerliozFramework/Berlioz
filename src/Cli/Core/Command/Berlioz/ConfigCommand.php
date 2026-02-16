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

declare(strict_types=1);

namespace Berlioz\Cli\Core\Command\Berlioz;

use Berlioz\Cli\Core\Command\AbstractCommand;
use Berlioz\Cli\Core\Command\Argument;
use Berlioz\Cli\Core\Console\Environment;
use Berlioz\Cli\Core\Exception\CliException;
use Berlioz\Config\Exception\ConfigException;

/**
 * Class ConfigCommand.
 */
#[Argument('filter', prefix: 'f', longPrefix: 'filter', description: 'Filter', required: false)]
class ConfigCommand extends AbstractCommand
{
    /**
     * @inheritDoc
     */
    public static function getDescription(): ?string
    {
        return 'Show merged JSON configuration';
    }

    /**
     * @inheritDoc
     * @throws CliException
     * @throws ConfigException
     */
    public function run(Environment $env): int
    {
        $filter = $env->getArgument('filter') ?: null;

        if (null === $filter) {
            $env->console()->out('Configuration:');
            $env->console()->card(json_encode($this->getApp()->getConfig()->getArrayCopy(), JSON_PRETTY_PRINT));
            return 0;
        }

        $env->console()->out(sprintf('Configuration at "%s":', $filter));
        if (false === $this->getApp()->getConfig()->has($filter)) {
            $env->console()->br()->backgroundRed()->card('No entry.');
            return 1;
        }

        $env->console()->card(json_encode($this->getApp()->getConfig()->get($filter), JSON_PRETTY_PRINT));
        return 0;
    }
}