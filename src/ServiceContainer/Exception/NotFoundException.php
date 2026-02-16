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

namespace Berlioz\ServiceContainer\Exception;

use Psr\Container\NotFoundExceptionInterface;

class NotFoundException extends ContainerException implements NotFoundExceptionInterface
{
    /**
     * Not found.
     *
     * @param string $id
     *
     * @return static
     */
    public static function notFound(string $id): static
    {
        return new static(sprintf('Service "%s" not found in container', $id));
    }

    /**
     * Class does not exists.
     *
     * @param string $class
     *
     * @return static
     */
    public static function classDoesNotExists(string $class): static
    {
        return new static(sprintf('Class "%s" does not exists', $class));
    }
}