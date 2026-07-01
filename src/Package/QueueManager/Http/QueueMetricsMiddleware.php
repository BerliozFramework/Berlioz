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

namespace Berlioz\Package\QueueManager\Http;

use Berlioz\Config\ConfigInterface;
use Berlioz\Config\Exception\ConfigException;
use Berlioz\Helpers\NetworkHelper;
use Berlioz\Http\Core\App\HttpApp;
use Berlioz\Http\Message\Response;
use Berlioz\Package\QueueManager\Metrics\QueueMetricsExporter;
use Berlioz\QueueManager\QueueManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class QueueMetricsMiddleware.
 *
 * Serves queue metrics (Prometheus or JSON) on a configurable path without relying on a
 * declared route. The endpoint is opt-in and may be gated by a client IP allow-list and an
 * optional bearer token.
 *
 * The middleware only serves metrics when ALL of the following hold:
 *  - the request path matches `berlioz.queues.metrics.path`,
 *  - no application route already claims that path (an explicit route always wins),
 *  - `berlioz.queues.metrics.enable` is true,
 *  - the (trusted) client IP matches the allow-list when one is configured,
 *  - the bearer token matches when one is configured.
 *
 * Every unmet condition results in a transparent pass-through: the request keeps flowing
 * down the pipeline and naturally ends up as a 404, never disclosing the endpoint.
 */
class QueueMetricsMiddleware implements MiddlewareInterface
{
    public function __construct(
        protected HttpApp $app,
        protected QueueManager $queueManager,
    ) {
    }

    /**
     * @inheritDoc
     * @throws ConfigException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $config = $this->app->getConfig();
        $path = $config->get('berlioz.queues.metrics.path', '/metrics/queues');

        // Not the metrics path: let everything else through untouched.
        if ($request->getUri()->getPath() !== $path) {
            return $handler->handle($request);
        }

        // An application route already claims this path: it always takes precedence.
        if (null !== $this->app->getRoute()) {
            return $handler->handle($request);
        }

        // Metrics are read-only: only GET (and its HEAD counterpart) are served. Any other verb
        // stays silent and falls back to the natural 404.
        if (false === in_array(strtoupper($request->getMethod()), ['GET', 'HEAD'], true)) {
            return $handler->handle($request);
        }

        // Disabled or unauthorized: stay silent and fall back to the natural 404.
        if (false === $this->isAllowed($request, $config)) {
            return $handler->handle($request);
        }

        // A HEAD request must return the same status and Content-Type as GET, but with an empty
        // body. Short-circuit before collecting metrics to avoid querying the queue backends
        // (and their latency) just to produce a body that will be discarded.
        if ('HEAD' === strtoupper($request->getMethod())) {
            return new Response(null, Response::HTTP_STATUS_OK, ['Content-Type' => $this->contentType($config)]);
        }

        return $this->metricsResponse($config);
    }

    /**
     * Is the request allowed to read the metrics endpoint?
     *
     * @param ServerRequestInterface $request
     * @param ConfigInterface $config
     *
     * @return bool
     * @throws ConfigException
     */
    private function isAllowed(ServerRequestInterface $request, ConfigInterface $config): bool
    {
        $enabled = $config->get('berlioz.queues.metrics.enable', false);

        if (!is_bool($enabled) || false === $enabled) {
            return false;
        }

        if (false === $this->isIpAllowed($request, $config)) {
            return false;
        }

        return $this->isTokenAllowed($request, $config);
    }

    /**
     * Is the client IP allowed?
     *
     * An empty allow-list means no IP restriction.
     *
     * @param ServerRequestInterface $request
     * @param ConfigInterface $config
     *
     * @return bool
     * @throws ConfigException
     */
    private function isIpAllowed(ServerRequestInterface $request, ConfigInterface $config): bool
    {
        $allowedIps = $config->get('berlioz.queues.metrics.ip', []);

        if (!is_array($allowedIps)) {
            return false;
        }

        // No IP restriction.
        if ([] === $allowedIps) {
            return true;
        }

        $trustedProxies = $config->get('berlioz.proxies.trusted', []);

        if (!is_array($trustedProxies)) {
            $trustedProxies = [];
        }

        // `X-Forwarded-For` is only honoured when the direct peer is a trusted proxy.
        $clientIp = NetworkHelper::clientIp($trustedProxies, $request->getServerParams());

        if (null === $clientIp) {
            return false;
        }

        if (in_array($clientIp, $allowedIps, true)) {
            return true;
        }

        return in_array(gethostbyaddr($clientIp), $allowedIps, true);
    }

    /**
     * Is the bearer token allowed?
     *
     * A null/empty configured token means no token requirement.
     *
     * @param ServerRequestInterface $request
     * @param ConfigInterface $config
     *
     * @return bool
     * @throws ConfigException
     */
    private function isTokenAllowed(ServerRequestInterface $request, ConfigInterface $config): bool
    {
        $token = $config->get('berlioz.queues.metrics.token', null);

        if (null === $token || '' === $token) {
            return true;
        }

        $authorization = $request->getHeaderLine('Authorization');

        if (1 !== preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            return false;
        }

        return hash_equals((string)$token, trim($matches[1]));
    }

    /**
     * Build the metrics response according to the configured format.
     *
     * @param ConfigInterface $config
     *
     * @return ResponseInterface
     * @throws ConfigException
     */
    private function metricsResponse(ConfigInterface $config): ResponseInterface
    {
        $exporter = new QueueMetricsExporter($this->queueManager);
        $withTotal = (bool)$config->get('berlioz.queues.metrics.total', false);

        if ('json' === $config->get('berlioz.queues.metrics.format', 'prometheus')) {
            $body = (string)json_encode($exporter->json($withTotal));
        } else {
            $labels = $config->get('berlioz.queues.metrics.prometheus_labels', []);
            $body = $exporter->prometheus(is_array($labels) ? $labels : [], $withTotal);
        }

        return new Response($body, Response::HTTP_STATUS_OK, ['Content-Type' => $this->contentType($config)]);
    }

    /**
     * Resolve the response Content-Type for the configured format.
     *
     * @param ConfigInterface $config
     *
     * @return string
     * @throws ConfigException
     */
    private function contentType(ConfigInterface $config): string
    {
        return match ($config->get('berlioz.queues.metrics.format', 'prometheus')) {
            'json' => 'application/json',
            default => 'text/plain; version=0.0.4; charset=utf-8',
        };
    }
}
