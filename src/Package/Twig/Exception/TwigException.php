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

namespace Berlioz\Package\Twig\Exception;

use Berlioz\Core\Exception\BerliozException;
use Berlioz\Package\Twig\TwigAwareInterface;
use Twig\Extension\ExtensionInterface;

class TwigException extends BerliozException
{
    /**
     * Invalid extension.
     *
     * @param mixed $extension
     *
     * @return static
     */
    public static function invalidExtension(mixed $extension): static
    {
        return new static(
            sprintf(
                'Twig extension must implement "%s" interface, actual "%s"',
                ExtensionInterface::class,
                get_debug_type($extension)
            )
        );
    }

    /**
     * Not loaded.
     *
     * @return static
     */
    public static function notLoaded(): static
    {
        return new static(sprintf('Twig is not loaded with method "%s::setTwig()"', TwigAwareInterface::class));
    }
}