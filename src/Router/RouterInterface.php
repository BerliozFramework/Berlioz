<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2020 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\Router;

use Berlioz\Router\Exception\RoutingException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Interface RouterInterface.
 *
 * @package Berlioz\Router
 */
interface RouterInterface extends RouteSetInterface
{
    /**
     * Generate route.
     *
     * @param string|RouteInterface $route
     * @param array|RouteAttributes $parameters
     *
     * @return string
     * @throws RoutingException
     */
    public function generate(string|RouteInterface $route, array|RouteAttributes $parameters = []): string;

    /**
     * Finalize path.
     *
     * Prepends the reverse-proxy prefix (`X-Forwarded-Prefix`) to the given path
     * when the request comes from a trusted proxy.
     *
     * @param string $path
     * @param array|null $serverParams Server parameters (defaults to `$_SERVER` when null)
     *
     * @return string
     */
    public function finalizePath(string $path, ?array $serverParams = null): string;

    /**
     * Is valid request?
     *
     * @param ServerRequestInterface|string $request
     *
     * @return bool
     */
    public function isValid(ServerRequestInterface|string $request): bool;

    /**
     * Handle server request.
     *
     * @param ServerRequestInterface $request
     *
     * @return RouteInterface|null
     */
    public function handle(ServerRequestInterface &$request): ?RouteInterface;
}