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


use Berlioz\Core\Exception\BerliozException;
use Psr\Http\Message\UriInterface;

/**
 * Class RouteSet.
 *
 * @package Berlioz\Core\Services\Routing\Router
 * @see     \Berlioz\Core\Services\Routing\RouteSetInterface
 */
class RouteSet implements RouteSetInterface
{
    /** @var \Berlioz\Core\Services\Routing\RouteInterface[] */
    private $routes;
    /** @var string[] */
    private $exceptionControllers;

    /**
     * RouteSet constructor.
     */
    public function __construct()
    {
        $this->routes = [];
        $this->exceptionControllers = [];
    }

    /**
     * @inheritdoc
     */
    public function addRoute(RouteInterface $route): RouteSetInterface
    {
        $this->routes[] = $route;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getByName($name): array
    {
        $routes = [];

        foreach ($this->routes as $route) {
            if ($route->getName() == $name) {
                $routes[] = $route;
            }
        }

        return $routes;
    }

    /**
     * @inheritdoc
     */
    public function searchRoute(UriInterface $uri, string $method = null): ?RouteInterface
    {
        $httpPath = $uri->getPath();
        $routes = $this->routes;

        // Order routes by number of parameters (DESC)
        usort(
            $routes,
            function (RouteInterface $a, RouteInterface $b) {
                if ($a->getPriority() == $b->getPriority()) {
                    if ($a->getNumberOfParameters() == $b->getNumberOfParameters()) {
                        return 0;
                    } else {
                        return ($a->getNumberOfParameters() > $b->getNumberOfParameters()) ? -1 : 1;
                    }
                } else {
                    return ($a->getPriority() > $b->getPriority()) ? -1 : 1;
                }
            });

        foreach ($routes as $route) {
            if (empty($method) || in_array($method, $route->getMethods())) {
                if ($route->test($httpPath)) {
                    return $route;
                }
            }
        }

        return null;
    }

    /**
     * Count routes.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->routes);
    }

    /**
     * @inheritdoc
     */
    public function addException(string $exceptionControllerClass, string $path = null): RouteSetInterface
    {
        if (class_exists($exceptionControllerClass)) {
            if (is_subclass_of($exceptionControllerClass, '\Berlioz\Core\Controller\ExceptionControllerInterface')) {
                $this->exceptionControllers[$path ?: '/'] = $exceptionControllerClass;
            } else {
                throw new BerliozException(sprintf('The exception controller class "%s" must implement \Berlioz\Core\Controller\ExceptionControllerInterface interface', $exceptionControllerClass));
            }
        } else {
            throw new BerliozException(sprintf('Exception controller class "%s" doesn\'t exists', $exceptionControllerClass));
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getException(UriInterface $uri): string
    {
        $exceptionController = '\Berlioz\Core\Controller\ExceptionController';

        $path = $uri->getPath() ?: '/';
        $paths = array_keys($this->exceptionControllers);
        usort($paths,
            function ($a, $b) {
                if (mb_strlen($a) < mb_strlen($b)) {
                    return 1;
                } else {
                    if (mb_strlen($a) > mb_strlen($b)) {
                        return -1;
                    } else {
                        return 0;
                    }
                }
            });

        foreach ($paths as $aPath) {
            if (substr($path, 0, mb_strlen($aPath)) == $aPath) {
                $exceptionController = $this->exceptionControllers[$aPath];
                break;
            }
        }

        return $exceptionController;
    }
}