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

namespace Berlioz\QueueManager\Queue;

use Berlioz\QueueManager\Exception\QueueException;
use SensitiveParameter;

/**
 * Class RabbitMqManagementApi.
 *
 * Read-only client of the RabbitMQ management HTTP API.
 * Redirections are never followed and only "200 OK" responses are used.
 *
 * @internal
 */
final readonly class RabbitMqManagementApi
{
    private string $baseUrl;
    /** @var resource */
    private mixed $context;

    /**
     * RabbitMqManagementApi constructor.
     *
     * @param string $baseUrl HTTP(S) base URL of the management API
     * @param string $username Management API username (a dedicated user with "monitoring" tag is recommended)
     * @param string $password Management API password
     * @param array|resource|null $context Stream context or stream context options (e.g. "ssl.cafile")
     * @param int $timeout Timeout in seconds
     *
     * @throws QueueException
     */
    public function __construct(
        string $baseUrl,
        private string $username,
        #[SensitiveParameter] private string $password,
        mixed $context = null,
        int $timeout = 2,
    ) {
        $this->baseUrl = $this->validateBaseUrl($baseUrl);
        $this->context = $this->createContext($context, $timeout);
    }

    /**
     * __debugInfo() magic method.
     *
     * @return array
     */
    public function __debugInfo(): array
    {
        return [
            'baseUrl' => $this->baseUrl,
            'username' => $this->username,
            'password' => '*** HIDDEN ***',
        ];
    }

    /**
     * Get base URL.
     *
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Get decoded JSON response of the given path.
     *
     * @param string $path
     *
     * @return mixed NULL on failure
     */
    public function get(string $path): mixed
    {
        $handle = @fopen($this->baseUrl . '/' . ltrim($path, '/'), 'r', false, $this->context);
        if (false === $handle) {
            return null;
        }

        try {
            if (200 !== $this->getStatusCode(stream_get_meta_data($handle)['wrapper_data'] ?? null)) {
                return null;
            }

            $body = stream_get_contents($handle);
        } finally {
            fclose($handle);
        }

        if (false === $body) {
            return null;
        }

        return json_decode($body, true);
    }

    /**
     * Validate base URL.
     *
     * @param string $baseUrl
     *
     * @return string
     * @throws QueueException
     */
    private function validateBaseUrl(string $baseUrl): string
    {
        $parts = parse_url($baseUrl);

        if (false === $parts) {
            throw QueueException::invalidManagementApi('base URL is malformed');
        }
        if (!in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            throw QueueException::invalidManagementApi('base URL scheme must be "http" or "https"');
        }
        if ('' === ($parts['host'] ?? '')) {
            throw QueueException::invalidManagementApi('base URL must have a host');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw QueueException::invalidManagementApi('base URL must not contain credentials');
        }
        if (isset($parts['query']) || isset($parts['fragment'])) {
            throw QueueException::invalidManagementApi('base URL must not contain a query or a fragment');
        }

        return rtrim($baseUrl, '/');
    }

    /**
     * Create stream context.
     *
     * A copy is made of given stream context, to not alter it.
     * HTTP method, authorization and redirection options are forced.
     *
     * @param mixed $context
     * @param int $timeout
     *
     * @return resource
     * @throws QueueException
     */
    private function createContext(mixed $context, int $timeout)
    {
        $params = [];

        if (is_resource($context) && 'stream-context' === get_resource_type($context)) {
            $params = stream_context_get_params($context);
            unset($params['options']);
            $context = stream_context_get_options($context);
        }

        if (!is_array($context) && null !== $context) {
            throw QueueException::invalidManagementApi('context must be an array of options or a stream context');
        }

        $context ??= [];
        $context['http']['timeout'] ??= $timeout;
        $context['http'] = array_replace(
            $context['http'],
            [
                'method' => 'GET',
                'header' => [
                    sprintf('Authorization: Basic %s', base64_encode($this->username . ':' . $this->password)),
                    'Accept: application/json',
                ],
                'follow_location' => 0,
                'max_redirects' => 1,
                'ignore_errors' => true,
            ],
        );
        $context['ssl']['verify_peer'] ??= true;
        $context['ssl']['verify_peer_name'] ??= true;

        return stream_context_create($context, $params);
    }

    /**
     * Get status code of the last response.
     *
     * @param mixed $headers
     *
     * @return int|null
     */
    private function getStatusCode(mixed $headers): ?int
    {
        $statusCode = null;

        foreach ((array)$headers as $header) {
            if (is_string($header) && 1 === preg_match('#^HTTP/\S+\s+(\d{3})#i', $header, $matches)) {
                $statusCode = (int)$matches[1];
            }
        }

        return $statusCode;
    }
}
