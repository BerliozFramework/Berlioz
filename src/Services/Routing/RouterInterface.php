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


use Berlioz\Core\App\AppAwareInterface;
use Psr\Http\Message\ServerRequestInterface;

interface RouterInterface extends AppAwareInterface
{
    // HTTP methods
    const HTTP_METHOD_UNKNOWN = 'UNKNOWN';
    const HTTP_METHOD_GET = 'GET';
    const HTTP_METHOD_HEAD = 'HEAD';
    const HTTP_METHOD_POST = 'POST';
    const HTTP_METHOD_OPTIONS = 'OPTIONS';
    const HTTP_METHOD_CONNECT = 'CONNECT';
    const HTTP_METHOD_TRACE = 'TRACE';
    const HTTP_METHOD_PUT = 'PUT';
    const HTTP_METHOD_DELETE = 'DELETE';
    // HTTP status codes
    const HTTP_STATUS_CONTINUE = 100;
    const HTTP_STATUS_SWITCHING_PROTOCOL = 101;
    const HTTP_STATUS_OK = 200;
    const HTTP_STATUS_CREATED = 201;
    const HTTP_STATUS_ACCEPTED = 202;
    const HTTP_STATUS_NON_AUTHORITATIVE = 203;
    const HTTP_STATUS_NO_CONTENT = 204;
    const HTTP_STATUS_RESET_CONTENT = 205;
    const HTTP_STATUS_PARTIAL_CONTENT = 206;
    const HTTP_STATUS_MULTIPLE_CHOICE = 300;
    const HTTP_STATUS_MOVED_PERMANENTLY = 301;
    const HTTP_STATUS_MOVED_TEMPORARILY = 302;
    const HTTP_STATUS_SEE_OTHER = 303;
    const HTTP_STATUS_NOT_MODIFIED = 304;
    const HTTP_STATUS_USE_PROXY = 305;
    const HTTP_STATUS_BAD_REQUEST = 400;
    const HTTP_STATUS_UNAUTHORIZED = 401;
    const HTTP_STATUS_PAYMENT_REQUIRED = 402;
    const HTTP_STATUS_FORBIDDEN = 403;
    const HTTP_STATUS_NOT_FOUND = 404;
    const HTTP_STATUS_METHOD_NOT_ALLOWED = 405;
    const HTTP_STATUS_NOT_ACCEPTABLE = 406;
    const HTTP_STATUS_PROXY_AUTHENTICATION_REQUIRED = 407;
    const HTTP_STATUS_REQUEST_TIME_OUT = 408;
    const HTTP_STATUS_CONFLICT = 409;
    const HTTP_STATUS_GONE = 410;
    const HTTP_STATUS_LENGTH_REQUIRED = 411;
    const HTTP_STATUS_PRECONDITION_FAILED = 412;
    const HTTP_STATUS_REQUEST_ENTITY_TOO_LARGE = 413;
    const HTTP_STATUS_REQUEST_URI_TOO_LARGE = 414;
    const HTTP_STATUS_UNSUPPORTED_MEDIA_TYPE = 415;
    const HTTP_STATUS_INTERNAL_SERVER_ERROR = 500;
    const HTTP_STATUS_NOT_IMPLEMENTED = 501;
    const HTTP_STATUS_BAD_GATEWAY = 502;
    const HTTP_STATUS_SERVICE_UNAVAILABLE = 503;
    const HTTP_STATUS_GATEWAY_TIME_OUT = 504;
    const HTTP_STATUS_HTTP_VERSION_NOT_SUPPORTED = 505;

    /**
     * Get route set.
     *
     * @return \Berlioz\Core\Services\Routing\RouteSetInterface
     */
    public function getRouteSet(): RouteSetInterface;

    /**
     * Set route set.
     *
     * @param \Berlioz\Core\Services\Routing\RouteSetInterface $routeSet
     *
     * @return static
     */
    public function setRouteSet(RouteSetInterface $routeSet): RouterInterface;

    /**
     * Add a controller class in routing system.
     *
     * @param string $controllerClass Controller class
     * @param string $basePath        Base of path for all routes in controller
     *
     * @return static
     * @throws \Berlioz\Core\Exception\BerliozException If controller class doesn't exists
     */
    public function addController(string $controllerClass, string $basePath = ''): RouterInterface;

    /**
     * Add an exception controller in routing system.
     *
     * @param string $exceptionControllerClass Exception controller class
     * @param string $path                     Path
     *
     * @return static
     * @throws \Berlioz\Core\Exception\BerliozException If exception controller class doesn't exists
     * @throws \Berlioz\Core\Exception\BerliozException If exception controller do not implement
     *                                                  \Berlioz\Core\Controller\ExceptionControllerInterface interface
     */
    public function addExceptionController(string $exceptionControllerClass, string $path = null): RouterInterface;

    /**
     * Get server request.
     *
     * Can called after RouterInterface::handle() method.
     * Return the ServerRequest object of current request.
     *
     * @return \Psr\Http\Message\ServerRequestInterface
     */
    public function getServerRequest(): ServerRequestInterface;

    /**
     * Set server request.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $serverRequest
     *
     * @return \Berlioz\Core\Services\Routing\RouterInterface
     */
    public function setServerRequest(ServerRequestInterface $serverRequest): RouterInterface;

    /**
     * Get current route.
     *
     * Can called after RouterInterface::handle() method.
     * Return the RouteInterface object of current route.
     *
     * @return \Berlioz\Core\Services\Routing\RouteInterface|null
     */
    public function getCurrentRoute(): ?RouteInterface;

    /**
     * Handle.
     *
     * @return \Psr\Http\Message\ResponseInterface|string
     */
    public function handle();

    /**
     * Generate route with parameters.
     *
     * Must return path route with given name of route and associated parameters.
     *
     * @param string $name       Name of route
     * @param array  $parameters Parameters for route
     *
     * @return string|false
     */
    public function generate(string $name, array $parameters = []);

    /**
     * Is valid route ?
     *
     * Check if a route is associate to the given path and HTTP method.
     *
     * @param string $path   Path to test
     * @param string $method Http method
     *
     * @return bool
     */
    public function isValid(string $path, string $method = null): bool;
}