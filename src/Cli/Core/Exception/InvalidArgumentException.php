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

/**
 * Class InvalidArgumentException.
 */
class InvalidArgumentException extends CliException
{
    public static function unknown(string $name, ?string $commandClass = null): static
    {
        if (null === $commandClass) {
            return new static(sprintf('Argument "%s" does not exists', $name));
        }

        return new static(sprintf('Argument "%s" does not exists for command "%s"', $name, $commandClass));
    }
}