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

namespace Berlioz\Package\Twig\Extension;

use Berlioz\Router\Exception\NotFoundException;
use Berlioz\Router\Exception\RoutingException;
use Berlioz\Router\RouteAttributes;
use Berlioz\Router\Router;
use Exception;
use Twig\Error\Error;
use Twig\Error\RuntimeError;

class RouterRuntimeExtension
{
    public function __construct(protected Router $router)
    {
    }

    /**
     * Function to generate path.
     *
     * @param string $name
     * @param array|RouteAttributes $parameters
     *
     * @return string
     * @throws Error
     */
    public function functionPath(string $name, array|RouteAttributes $parameters = []): string
    {
        try {
            return $this->router->generate($name, $parameters);
        } catch (NotFoundException|RoutingException $exception) {
            throw new RuntimeError($exception->getMessage());
        } catch (Exception $exception) {
            throw new RuntimeError('Routing treatment error', previous: $exception);
        }
    }

    /**
     * Function to check if a path exists.
     *
     * @param string $name
     * @param array $parameters
     *
     * @return bool
     * @throws Error
     */
    public function functionPathExists(string $name, array $parameters = []): bool
    {
        try {
            $this->router->generate($name, $parameters);

            return true;
        } catch (NotFoundException|RoutingException) {
            return false;
        } catch (Exception $exception) {
            throw new RuntimeError('Routing treatment error', previous: $exception);
        }
    }

    /**
     * Function to finalize a path.
     *
     * @param string $path
     *
     * @return string
     */
    public function functionFinalizePath(string $path): string
    {
        return $this->router->finalizePath($path);
    }
}