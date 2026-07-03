<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2026 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\Http\Core\Http\Middleware;

use Berlioz\Http\Core\App\HttpApp;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class ForwardedPrefixMiddleware.
 *
 * Rewrites the current request URI with the reverse-proxy prefix
 * (`X-Forwarded-Prefix`) so that any URL derived from it — pagination links,
 * self-URLs, redirections — is correctly prefixed, without the application code
 * having to know about the reverse-proxy topology.
 *
 * All the resolution logic (header lookup, trusted-proxy guard, idempotence) is
 * delegated to {@see \Berlioz\Router\Router::finalizePath()}, which is the single
 * source of truth for the prefix. The resolved prefix is also exposed as the
 * `berlioz.forwarded_prefix` request attribute for consumers that need the raw value.
 *
 * This middleware is applied last in the pipeline (closest to the controller),
 * hence after route matching: rewriting the path never affects routing.
 */
class ForwardedPrefixMiddleware implements MiddlewareInterface
{
    public const REQUEST_ATTRIBUTE = 'berlioz.forwarded_prefix';

    public function __construct(
        protected HttpApp $app,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $router = $this->app->getRouter();
        $uri = $request->getUri();
        $path = $uri->getPath();
        $finalizedPath = $router->finalizePath($path, $request->getServerParams());

        if ($finalizedPath !== $path) {
            // Extract the applied prefix (finalizePath prepended it to the path).
            $prefix = substr($finalizedPath, 0, strlen($finalizedPath) - strlen(ltrim($path, '/'))) ?: null;
            $prefix = null !== $prefix ? rtrim($prefix, '/') : null;

            $request = $request
                ->withUri($uri->withPath($finalizedPath))
                ->withAttribute(self::REQUEST_ATTRIBUTE, $prefix);

            // Keep the application-wide request in sync so helpers relying on
            // HttpApp::getRequest() (e.g. reload/self-redirect) see the prefixed URI.
            $this->app->setRequest($request);
        }

        return $handler->handle($request);
    }
}
