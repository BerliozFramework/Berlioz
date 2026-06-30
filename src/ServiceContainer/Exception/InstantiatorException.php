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

use Closure;
use Throwable;

class InstantiatorException extends ContainerException
{
    /**
     * Class.
     *
     * @param string|object $class
     * @param Throwable $error
     *
     * @return static
     */
    public static function classError(object|string $class, Throwable $error): static
    {
        if (is_object($class)) {
            $class = $class::class;
        }

        return new static(sprintf('Error during dependency injection of class "%s"', $class), 0, $error);
    }

    /**
     * Method.
     *
     * @param string|object $class
     * @param string $method
     * @param Throwable $error
     *
     * @return static
     */
    public static function methodError(object|string $class, string $method, Throwable $error): static
    {
        if (is_object($class)) {
            $class = $class::class;
        }

        return new static(sprintf('Error during dependency injection of method "%s::%s"', $class, $method), 0, $error);
    }

    /**
     * Function.
     *
     * @param Closure|string $function
     * @param Throwable $error
     *
     * @return static
     */
    public static function functionError(Closure|string $function, Throwable $error)
    {
        if ($function instanceof Closure) {
            return new static('Error during dependency injection of closure', 0, $error);
        }

        return new static(sprintf('Error during dependency injection of function "%s"', $function), 0, $error);
    }
}
