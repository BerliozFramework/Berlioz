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

namespace Berlioz\Cli\Core\Exception;

use Berlioz\Cli\Core\Command\CommandInterface;
use Throwable;

/**
 * Class CommandException.
 */
class CommandException extends CliException
{
    public static function invalidCommandClass(string $class): static
    {
        return new static(sprintf('Class "%s" must implements "%s" interface', $class, CommandInterface::class));
    }

    public static function parsingArguments(string $class, ?Throwable $previous): static
    {
        return new static(sprintf('Unable to parse arguments from command "%s"', $class), previous: $previous);
    }
}