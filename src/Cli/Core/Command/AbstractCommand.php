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

namespace Berlioz\Cli\Core\Command;

use Berlioz\Cli\Core\App\CliAppAwareInterface;
use Berlioz\Cli\Core\App\CliAppAwareTrait;

/**
 * Class AbstractCommand.
 */
abstract class AbstractCommand implements CliAppAwareInterface, CommandInterface
{
    use CliAppAwareTrait;

    /**
     * @inheritDoc
     */
    public static function getDescription(): ?string
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public static function getHelp(): ?string
    {
        return null;
    }

    /**
     * Get service.
     *
     * @param string $id
     *
     * @return mixed
     */
    protected function get(string $id): mixed
    {
        return $this->app->get($id);
    }
}