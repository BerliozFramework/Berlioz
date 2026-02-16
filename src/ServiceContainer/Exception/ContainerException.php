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

use Berlioz\ServiceContainer\Service\Service;
use Exception;
use Psr\Container\ContainerExceptionInterface;
use Throwable;

class ContainerException extends Exception implements ContainerExceptionInterface
{
    /**
     * Instantiation.
     *
     * @param string $id
     * @param Throwable $exception
     *
     * @return static
     */
    public static function instantiation(string $id, Throwable $exception): static
    {
        return new static(sprintf('Error during instantiation of service "%s"', $id), 0, $exception);
    }

    /**
     * Excepted factory.
     *
     * @param Service $service
     * @param mixed $actual
     *
     * @return static
     */
    public static function exceptedFactory(Service $service, mixed $actual): static
    {
        return new static(
            sprintf(
                'Excepted "%s" class from factory "%s" of service "%s", actual "%s" class',
                $service->getClass(),
                get_debug_type($service->getFactory()),
                $service->getAlias(),
                get_debug_type($actual),
            )
        );
    }
}