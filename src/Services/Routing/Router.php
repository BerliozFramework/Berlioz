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


use Berlioz\Core\App;
use Berlioz\Core\App\AppAwareTrait;
use Berlioz\Core\Http\Response;
use Berlioz\Core\Http\ServerRequest;
use Berlioz\Core\Http\Stream;
use Berlioz\Core\Http\UploadedFile;
use Berlioz\Core\Http\Uri;
use Berlioz\Core\Exception\BerliozException;
use Berlioz\Core\Exception\RoutingException;
use phpDocumentor\Reflection\DocBlockFactory;
use Psr\Http\Message\ServerRequestInterface;

class Router implements RouterInterface
{
    use AppAwareTrait;
    /** @var \Berlioz\Core\Services\Routing\RouteSetInterface Route set */
    private $routeSet;
    /** @var \Berlioz\Core\Http\ServerRequest Server request */
    private $server_request;
    /** @var \Berlioz\Core\Services\Routing\RouteInterface Current route */
    private $current_route;
    /** @var \phpDocumentor\Reflection\DocBlockFactory */
    private static $docBlockFactory;

    /**
     * Router constructor.
     *
     * @param App $app Application
     */
    public function __construct(App $app = null)
    {
        $this->setApp($app);
    }

    /**
     * @inheritdoc
     */
    public function __sleep(): array
    {
        return ['routeSet', 'exceptionControllers'];
    }

    /**
     * @inheritdoc
     */
    public function getRouteSet(): RouteSetInterface
    {
        if (is_null($this->routeSet)) {
            $this->routeSet = new RouteSet;
        }

        return $this->routeSet;
    }

    /**
     * @inheritdoc
     */
    public function setRouteSet(RouteSetInterface $routeSet): RouterInterface
    {
        $this->routeSet = $routeSet;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function addController(string $controllerClass, string $basePath = ''): RouterInterface
    {
        // Do the reflection of class, create the object and do the mapping
        if (class_exists($controllerClass)) {
            $reflectionClass = new \ReflectionClass($controllerClass);

            // Get all public methods of controller
            $methods = $reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC);

            foreach ($methods as $method) {
                foreach (Route::createFromReflection($method, $basePath) as $route) {
                    $this->getRouteSet()->addRoute($route);
                }
            }
        } else {
            throw new BerliozException(sprintf('Class "%s" doesn\'t exists', $controllerClass));
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function addExceptionController(string $exceptionControllerClass, string $path = null): RouterInterface
    {
        $this->getRouteSet()->addException($exceptionControllerClass, $path);

        return $this;
    }

    /**
     * Get DockBlockFactory object to read doc block.
     *
     * @return \phpDocumentor\Reflection\DocBlockFactory
     */
    public static function getDocBlockFactory(): DocBlockFactory
    {
        if (is_null(self::$docBlockFactory)) {
            self::$docBlockFactory = DocBlockFactory::createInstance();
        }

        return self::$docBlockFactory;
    }

    /**
     * Get HTTP method of request.
     *
     * @return string
     */
    private function getHttpMethod(): string
    {
        if (!empty($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
            $method = mb_strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
        } else {
            $method = mb_strtoupper($_SERVER['REQUEST_METHOD']);
        }

        switch ($method) {
            case 'GET':
                return static::HTTP_METHOD_GET;
            case 'HEAD':
                return static::HTTP_METHOD_HEAD;
            case 'POST':
                return static::HTTP_METHOD_POST;
            case 'OPTIONS':
                return static::HTTP_METHOD_OPTIONS;
            case 'CONNECT':
                return static::HTTP_METHOD_CONNECT;
            case 'TRACE':
                return static::HTTP_METHOD_TRACE;
            case 'PUT':
                return static::HTTP_METHOD_PUT;
            case 'DELETE':
                return static::HTTP_METHOD_DELETE;
            default:
                return static::HTTP_METHOD_UNKNOWN;
        }
    }

    /**
     * Get HTTP path of request.
     *
     * @return string|null
     */
    private function getHttpPath(): ?string
    {
        $path = null;

        if (isset($_SERVER['REDIRECT_URL'])) {
            $path = $_SERVER['REDIRECT_URL'];
        } else {
            if (isset($_SERVER['REQUEST_URI'])) {
                $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            }
        }

        return $path;
    }

    /**
     * Get HTTP query string.
     *
     * @return string
     */
    private function getHttpQueryString(): string
    {
        if (isset($_SERVER['REDIRECT_QUERY_STRING'])) {
            return $_SERVER['REDIRECT_QUERY_STRING'];
        } else {
            if (isset($_SERVER['REQUEST_URI'])) {
                return $_SERVER['QUERY_STRING'];
            } else {
                return '';
            }
        }
    }

    /**
     * Get HTTP headers.
     *
     * @param string $prefix Return only headers with prefix defined
     *
     * @return string[]
     */
    protected function getHttpHeaders(string $prefix = null): array
    {
        $headers = [];

        // Get all headers
        if (function_exists('\getallheaders')) {
            $headers = \getallheaders() ?: [];
        } else {
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }
        }

        // Headers filter
        if (!empty($prefix)) {
            $prefixLength = mb_strlen($prefix);

            $headers =
                array_filter(
                    $headers,
                    function ($key) use ($prefix, $prefixLength) {
                        return substr($key, 0, $prefixLength) == $prefix;
                    }, ARRAY_FILTER_USE_KEY);
        }

        return $headers;
    }

    /**
     * @inheritdoc
     */
    public function getServerRequest(): ServerRequestInterface
    {
        return $this->server_request;
    }

    /**
     * @inheritdoc
     */
    public function setServerRequest(ServerRequestInterface $serverRequest): RouterInterface
    {
        $this->server_request = $serverRequest;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getCurrentRoute(): ?RouteInterface
    {
        return $this->current_route;
    }

    /**
     * @inheritdoc
     */
    public function handle()
    {
        // Log
        $this->getApp()->getService('logging')->debug(sprintf('%s', __METHOD__));

        // Server request
        {
            // Create ServerRequest object
            $stream = fopen('php://temp', 'w+');
            stream_copy_to_stream(fopen('php://input', 'r'), $stream);
            rewind($stream);

            // Request URI
            $requestUri = new Uri($_SERVER['REQUEST_SCHEME'] ?? '',
                                  $_SERVER['HTTP_HOST'] ?? '',
                                  $_SERVER['SERVER_PORT'] ?? 80,
                                  $this->getHttpPath(),
                                  $this->getHttpQueryString(),
                                  '',
                                  $_SERVER['PHP_AUTH_USER'] ?? '',
                                  $_SERVER['PHP_AUTH_PW'] ?? '');

            // Request
            $this->server_request = new ServerRequest($this->getHttpMethod(),
                                                      $requestUri,
                                                      $this->getHttpHeaders(),
                                                      $_COOKIE,
                                                      $_SERVER,
                                                      new Stream($stream),
                                                      UploadedFile::parseUploadedFiles($_FILES));
        }

        // Log
        $this->getApp()->getService('logging')->debug(sprintf('%s / ServerRequest created', __METHOD__));

        try {
            try {
                /** @var \Berlioz\Core\Services\Routing\RouteInterface $route */
                if (!is_null($route = $this->getRouteSet()->searchRoute($requestUri, $this->server_request->getMethod()))) {
                    // Log
                    $this->getApp()->getService('logging')->debug(sprintf('%s / Route found', __METHOD__));

                    // Parameters
                    {
                        // Order invocation parameters
                        $parameters = $route->extractParameters($requestUri->getPath());
                        $this->server_request = $this->server_request->withAttributes($parameters);
                        $this->getApp()->getProfile()->setRequest($this->server_request);
                    }

                    // Set current route
                    $this->current_route = $route->withParametersValues(array_merge($parameters, $_GET));
                    $this->getApp()->getProfile()->setRoute($this->current_route);

                    // Create controller
                    $invoke = $this->current_route->getInvoke();

                    /** @var \Berlioz\Core\Controller\ControllerInterface $controller */
                    $controller = new $invoke[0]($this->getApp());

                    // Log
                    $this->getApp()->getService('logging')->debug(sprintf('%s / Controller instanced', __METHOD__));

                    // Check authentication
                    $authenticationResponse = null;
                    if ($this->current_route->noAuthentication() || ($authenticationResponse = $controller->_b_authentication($this->server_request)) === true) {
                        // Log
                        $this->getApp()->getService('logging')->debug(sprintf('%s / Authentication magic method called', __METHOD__));

                        // Call magic Berlioz method to init controller (after authentication control)
                        $controller->_b_init($this->server_request);

                        // Log
                        $this->getApp()->getService('logging')->debug(sprintf('%s / Init magic method called', __METHOD__));

                        // Invoke method
                        $result = (call_user_func([$controller, $invoke[1]], $this->server_request));

                        return $result;
                    } else {
                        if ($authenticationResponse instanceof Response) {
                            return $authenticationResponse;
                        } else {
                            throw new RoutingException(Router::HTTP_STATUS_UNAUTHORIZED);
                        }
                    }
                } else {
                    throw new RoutingException(Router::HTTP_STATUS_NOT_FOUND);
                }
            } catch (RoutingException $e) {
                throw $e;
            } catch (\Exception $e) {
                throw new RoutingException(Router::HTTP_STATUS_INTERNAL_SERVER_ERROR, null, $e);
            } catch (\Error $e) {
                throw new RoutingException(Router::HTTP_STATUS_INTERNAL_SERVER_ERROR, null, $e);
            }
        } catch (RoutingException $e) {
            $exceptionControllerClass = $this->getRouteSet()->getException($requestUri);
            /** @var \Berlioz\Core\Controller\ExceptionControllerInterface $exceptionController */
            $exceptionController = new $exceptionControllerClass($this->getApp());

            // Log
            $this->getApp()->getService('logging')->debug(sprintf('%s / ExceptionController instanced', __METHOD__));

            // Call magic Berlioz method to init controller
            $exceptionController->_b_init($this->server_request);

            // Log
            $this->getApp()->getService('logging')->debug(sprintf('%s / Init magic method called', __METHOD__));

            return $exceptionController->catchException($e);
        }
    }

    /**
     * @inheritdoc
     */
    public function generate(string $name, array $parameters = [])
    {
        $routes = $this->getRouteSet()->getByName($name);

        // Order routes by number of parameters (DESC)
        usort(
            $routes,
            function (RouteInterface $a, RouteInterface $b) {
                if ($a->getNumberOfParameters() == $b->getNumberOfParameters()) {
                    return 0;
                } else {
                    return ($a->getNumberOfParameters() > $b->getNumberOfParameters()) ? -1 : 1;
                }
            });

        foreach ($routes as $route) {
            $routeGenerated = $route->generate($parameters);

            if ($routeGenerated !== false) {
                return $routeGenerated;
            }
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function isValid(string $path, string $method = null): bool
    {
        $uri = Uri::createFromString($path);

        return !is_null($this->getRouteSet()->searchRoute($uri, $method));
    }
}