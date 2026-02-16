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

namespace Berlioz\Package\Hector\Exception;

use Berlioz\Core\Exception\BerliozException;
use Throwable;

/**
 * Class HectorException.
 */
class HectorException extends BerliozException
{
    /**
     * Types config.
     *
     * @param Throwable|null $previous
     *
     * @return static
     */
    public static function typesConfig(?Throwable $previous = null): static
    {
        return new static('Types config error', previous: $previous);
    }
}