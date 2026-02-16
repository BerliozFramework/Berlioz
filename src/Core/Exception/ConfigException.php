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

use Throwable;

/**
 * Class ConfigException.
 */
class ConfigException extends BerliozException
{
    public static function serviceConfig(string $service): static
    {
        return new static(sprintf('Bad configuration of "%s" service', $service));
    }

    public static function serviceProvidersConfig(?Throwable $previous = null): static
    {
        return new static('Bad configuration of service container providers', previous: $previous);
    }

    public static function listenersConfig(?Throwable $previous = null): static
    {
        return new static('Bad configuration of event listeners', previous: $previous);
    }

    public static function subscribersConfig(?Throwable $previous = null): static
    {
        return new static('Bad configuration of event subscribers', previous: $previous);
    }
}