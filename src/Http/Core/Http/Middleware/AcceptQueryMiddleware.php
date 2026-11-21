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
use Berlioz\Http\Core\Exception\Http\BadRequestHttpException;
use Berlioz\Http\Core\Exception\Http\UnsupportedMediaTypeHttpException;
use Berlioz\Http\Core\Exception\HttpAppException;
use Berlioz\Http\Core\Exception\HttpException;
use Berlioz\Http\Core\Http\Handler\Error\ErrorHandler;
use Berlioz\Http\Message\Request;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class AcceptQueryMiddleware.
 *
 * Handles the "acceptQuery" route option (RFC 10008): advertises the accepted query media types
 * with the "Accept-Query" response header and rejects QUERY requests with a missing or unsupported content type.
 */
class AcceptQueryMiddleware implements MiddlewareInterface
{
    public const ROUTE_OPTION = 'acceptQuery';
    public const HEADER = 'Accept-Query';

    public function __construct(
        protected HttpApp $app,
    ) {
    }

    /**
     * @inheritDoc
     * @throws HttpAppException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $mediaRanges = $this->getMediaRanges();

        if (empty($mediaRanges)) {
            return $handler->handle($request);
        }

        $headerValue = $this->serializeHeader($mediaRanges);

        if (Request::HTTP_METHOD_QUERY === strtoupper($request->getMethod())) {
            $contentType = $this->getMediaType($request->getHeaderLine('Content-Type'));

            $exception = match (true) {
                null === $contentType => new BadRequestHttpException('Missing content type for QUERY request'),
                !$this->isAccepted($contentType, $mediaRanges) => new UnsupportedMediaTypeHttpException(),
                default => null,
            };

            if (null !== $exception) {
                return $this->createErrorResponse($request, $exception)->withHeader(static::HEADER, $headerValue);
            }
        }

        return $handler->handle($request)->withHeader(static::HEADER, $headerValue);
    }

    /**
     * Get accepted media ranges from the current route.
     *
     * @return string[]
     * @throws HttpAppException
     */
    protected function getMediaRanges(): array
    {
        $option = $this->app->getRoute()?->getOption(static::ROUTE_OPTION);

        if (null === $option) {
            return [];
        }

        $mediaRanges = (array)$option;

        foreach ($mediaRanges as $mediaRange) {
            if (!is_string($mediaRange) || null === $this->getMediaType($mediaRange)) {
                throw new HttpAppException(
                    sprintf('Route option "%s" must contain media ranges as strings', static::ROUTE_OPTION)
                );
            }
        }

        return array_values($mediaRanges);
    }

    /**
     * Get the lowercase media type, without parameters.
     *
     * @param string $value
     *
     * @return string|null
     */
    protected function getMediaType(string $value): ?string
    {
        $mediaType = strtolower(trim(explode(';', $value, 2)[0]));

        if (1 !== preg_match('#^[^/\s]+/[^/\s]+$#', $mediaType)) {
            return null;
        }

        return $mediaType;
    }

    /**
     * Is content type accepted by one of media ranges?
     *
     * Media range parameters are ignored for matching.
     *
     * @param string $contentType
     * @param string[] $mediaRanges
     *
     * @return bool
     */
    protected function isAccepted(string $contentType, array $mediaRanges): bool
    {
        [$type] = explode('/', $contentType, 2);

        foreach ($mediaRanges as $mediaRange) {
            $mediaRange = $this->getMediaType($mediaRange);

            if ('*/*' === $mediaRange || $type . '/*' === $mediaRange || $contentType === $mediaRange) {
                return true;
            }
        }

        return false;
    }

    /**
     * Serialize media ranges as a structured field list (RFC 9651) of strings with parameters.
     *
     * @param string[] $mediaRanges
     *
     * @return string
     * @throws HttpAppException
     */
    protected function serializeHeader(array $mediaRanges): string
    {
        $members = [];

        foreach ($mediaRanges as $mediaRange) {
            $parts = explode(';', $mediaRange);
            $member = $this->serializeString((string)$this->getMediaType(array_shift($parts)));

            foreach ($parts as $parameter) {
                if ('' === ($parameter = trim($parameter))) {
                    continue;
                }

                [$name, $value] = array_map(trim(...), explode('=', $parameter, 2)) + [1 => ''];
                $name = strtolower($name);

                if (1 !== preg_match('/^[a-z*][a-z0-9_\-.*]*$/', $name)) {
                    throw new HttpAppException(sprintf('Invalid media type parameter name "%s"', $name));
                }

                if (str_starts_with($value, '"') && str_ends_with($value, '"') && strlen($value) >= 2) {
                    $value = stripslashes(substr($value, 1, -1));
                }

                $member .= ';' . $name . '=' . $this->serializeString($value);
            }

            $members[] = $member;
        }

        return implode(', ', $members);
    }

    /**
     * Serialize a structured field string (RFC 9651, section 4.1.6).
     *
     * @param string $value
     *
     * @return string
     * @throws HttpAppException
     */
    protected function serializeString(string $value): string
    {
        if (1 !== preg_match('/^[\x20-\x7E]*$/', $value)) {
            throw new HttpAppException(sprintf('Invalid characters in "%s" header value', static::HEADER));
        }

        return '"' . addcslashes($value, '"\\') . '"';
    }

    /**
     * Create error response through the application error handler.
     *
     * @param ServerRequestInterface $request
     * @param HttpException $exception
     *
     * @return ResponseInterface
     */
    protected function createErrorResponse(
        ServerRequestInterface $request,
        HttpException $exception,
    ): ResponseInterface {
        return (new ErrorHandler($this->app))->handle($request, $exception);
    }
}
