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

namespace Berlioz\Core\Exception;

use Berlioz\Core\Package\PackageInterface;
use Throwable;

/**
 * Class PackageException.
 */
class PackageException extends BerliozException
{
    public static function invalidPackage(string $package): static
    {
        return new static(sprintf('Class "%s" must implements "%s" interface', $package, PackageInterface::class));
    }

    public static function config(string $package, ?Throwable $previous = null): static
    {
        return new static(sprintf('Error config package "%s"', $package), 0, $previous);
    }

    public static function registration(string $package, ?Throwable $previous = null): static
    {
        return new static(sprintf('Error registration package "%s"', $package), 0, $previous);
    }

    public static function boot(string $package, ?Throwable $previous = null): static
    {
        return new static(sprintf('Error package boot "%s"', $package), 0, $previous);
    }
}