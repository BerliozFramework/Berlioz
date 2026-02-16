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

namespace Berlioz\Package\Hector\Command;

use Berlioz\Cli\Core\Command\AbstractCommand;
use Berlioz\Cli\Core\Console\Environment;
use Exception;
use Hector\Orm\Orm;

class GenerateSchemaCommand extends AbstractCommand
{
    public function __construct()
    {
    }

    /**
     * @inheritDoc
     */
    public static function getDescription(): ?string
    {
        return 'Generate schema for Hector ORM';
    }

    /**
     * @inheritDoc
     */
    public function run(Environment $env): int
    {
        $env->console()->inline('Generate schema... ');
        $env->console()->spinner();

        try {
            $this->get(Orm::class);

            $env->console()->green('done!');
            return 0;
        } catch (Exception) {
            $env->console()->red('failed!');
            return 1;
        }
    }
}