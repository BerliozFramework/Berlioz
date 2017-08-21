<?php
/**
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2017 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Berlioz\Core\Services\Routing;


use Psr\Http\Message\UriInterface;

/**
 * Interface RouteSetInterface
 *
 * @package Berlioz\Core\Services\Routing
 */
interface RouteSetInterface extends \Countable
{
    /**
     * Add new route.
     *
     * @param \Berlioz\Core\Services\Routing\RouteInterface $route Route to add
     *
     * @throws \Berlioz\Core\Exception\BerliozException If route already exists
     */
    public function addRoute(RouteInterface $route);

    /**
     * Get routes by name.
     *
     * @param string $name Name of route
     *
     * @return \Berlioz\Core\Services\Routing\RouteInterface[]
     */
    public function getByName($name): array;

    /**
     * Search route for given uri and method.
     *
     * @param \Psr\Http\Message\UriInterface $uri    Uri
     * @param string|null                    $method Http method
     *
     * @return \Berlioz\Core\Services\Routing\RouteInterface|null
     */
    public function searchRoute(UriInterface $uri, string $method = null);

    /**
     * Add exception controller to router.
     *
     * @param string $exceptionControllerClass
     * @param string $path
     */
    public function addException(string $exceptionControllerClass, string $path = null);

    /**
     * Get exception for given uri.
     *
     * @param \Psr\Http\Message\UriInterface $uri
     *
     * @return string|null Exception controller
     */
    public function getException(UriInterface $uri);
}