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

namespace Berlioz\Http\Client\Tests;

use Berlioz\Http\Client\Client;
use Berlioz\Http\Client\Exception\NetworkException;
use Berlioz\Http\Client\Options;
use Berlioz\Http\Client\Tests\Adapter\FakeAdapter;
use Berlioz\Http\Message\Request;
use Berlioz\Http\Message\Response;
use Berlioz\Http\Message\Uri;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class ClientRedirectTest.
 */
class ClientRedirectTest extends TestCase
{
    public static function provideAuthorizationSources(): iterable
    {
        foreach (['global', 'setter', 'call-array', 'call-object', 'request'] as $source) {
            yield $source . '-same-origin' => [$source, 'https://api.example.test/next', true];
            yield $source . '-cross-origin' => [$source, 'https://other.example.test/next', false];
        }
    }

    #[DataProvider('provideAuthorizationSources')]
    public function testRedirectAuthorization(string $source, string $location, bool $sameOrigin): void
    {
        $requests = [];
        $adapter = new FakeAdapter(
            static function (RequestInterface $request) use (&$requests, $location): ResponseInterface {
                $requests[] = $request;

                if (count($requests) === 1) {
                    return (new Response(statusCode: 302))->withHeader('Location', $location);
                }

                return new Response(statusCode: 200);
            },
        );
        $headers = ['Authorization' => 'Bearer test-secret'];
        $client = new Client($source === 'global' ? ['headers' => $headers] : [], $adapter);
        $request = new Request('GET', 'https://api.example.test/start');
        $options = [];

        switch ($source) {
            case 'setter':
                $client->setDefaultHeader('Authorization', 'Bearer test-secret');
                break;
            case 'call-array':
                $options = ['headers' => $headers];
                break;
            case 'call-object':
                $options = new Options(headers: $headers);
                break;
            case 'request':
                $request = $request->withHeader('Authorization', 'Bearer test-secret');
                break;
        }

        $response = $client->sendRequest($request, $options);

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(2, $requests);
        self::assertSame('Bearer test-secret', $requests[0]->getHeaderLine('Authorization'));
        self::assertSame($location, (string)$requests[1]->getUri());

        // Request-only headers are currently not copied when rebuilding a redirect, even on the same origin.
        if ($sameOrigin && $source !== 'request') {
            self::assertSame('Bearer test-secret', $requests[1]->getHeaderLine('Authorization'));
        } else {
            self::assertFalse($requests[1]->hasHeader('Authorization'));
        }
    }

    public static function provideRedirectChains(): iterable
    {
        yield 'same origin then cross origin' => [[
            'https://api.example.test/start',
            'https://api.example.test/next',
            'https://other.example.test/end',
        ], [true, true, false], false];
        yield 'cross origin then return' => [[
            'https://api.example.test/start',
            'https://other.example.test/next',
            'https://other.example.test/again',
            'https://api.example.test/end',
        ], [true, false, false, false], false];
        yield 'retry after crossing origin' => [[
            'https://api.example.test/start',
            'https://other.example.test/next',
            'https://api.example.test/end',
        ], [true, false, false, false], true];
    }

    #[DataProvider('provideRedirectChains')]
    public function testRedirectCredentialsStayRemoved(array $uris, array $authenticated, bool $retry): void
    {
        $requests = [];
        $step = 0;
        $retried = false;
        $adapter = new FakeAdapter(
            static function (RequestInterface $request) use (
                &$requests,
                &$step,
                &$retried,
                $uris,
                $retry,
            ): ResponseInterface {
                $requests[] = $request;

                if ($retry && $step === 1 && !$retried) {
                    $retried = true;
                    throw new NetworkException('Simulated retry', $request);
                }

                $step++;
                if (isset($uris[$step])) {
                    return new Response(statusCode: 302, headers: ['Location' => $uris[$step]]);
                }

                return new Response();
            },
        );
        $headers = [
            'aUtHoRiZaTiOn' => ['Bearer test-secret'],
            'Proxy-Authorization' => ['Basic proxy-secret'],
            'Cookie' => ['sid=cookie-secret'],
            'X-API-KEY' => ['application-secret'],
            'Accept' => ['application/json'],
        ];
        $options = new Options(
            cookies: false,
            retry: 2,
            retryTime: 0,
            headers: $headers,
            redirectSensitiveHeaders: ['x-api-key'],
        );
        $client = new Client($options, $adapter);

        // Passing the client's own Options also exercises the default-header reference on cloned options.
        self::assertSame(200, $client->sendRequest(new Request('GET', $uris[0]), $options)->getStatusCode());
        self::assertCount(count($authenticated), $requests);
        $expectedUris = $uris;
        if ($retry) {
            array_splice($expectedUris, 1, 0, [$uris[1]]);
        }
        self::assertSame($expectedUris, array_map(static fn($request) => (string)$request->getUri(), $requests));

        foreach ($requests as $index => $request) {
            foreach (array_keys($headers) as $name) {
                if ($name === 'Accept') {
                    self::assertSame(['application/json'], $request->getHeader($name));
                    continue;
                }

                self::assertSame($authenticated[$index], $request->hasHeader($name), $name . ' at hop ' . $index);
            }
        }

        self::assertSame($headers, $options->headers);
        self::assertSame($headers, $client->getDefaultHeaders());
        self::assertSame(['x-api-key'], $options->redirectSensitiveHeaders);
        $client->sendRequest(new Request('GET', $uris[0]));
        self::assertSame('Bearer test-secret', $requests[array_key_last($requests)]->getHeaderLine('Authorization'));
    }

    public static function provideRedirectReferers(): iterable
    {
        yield 'same origin' => ['https://api.example.test/next', 'https://api.example.test/start?token=secret'];
        yield 'cross origin' => ['https://other.example.test/next', 'https://api.example.test/'];
        yield 'downgrade' => ['http://api.example.test/next', null];
    }

    #[DataProvider('provideRedirectReferers')]
    public function testRedirectRefererCannotBeReapplied(string $location, ?string $expected): void
    {
        $requests = [];
        $adapter = new FakeAdapter(
            static function (RequestInterface $request) use (&$requests, $location): ResponseInterface {
                $requests[] = $request;

                return count($requests) === 1
                    ? new Response(statusCode: 302, headers: ['Location' => $location])
                    : new Response();
            },
        );
        $options = new Options(headers: ['rEfErEr' => ['https://private.example.test/?secret=configured']]);
        $client = new Client($options, $adapter);
        $client->sendRequest(new Request('GET', 'https://user:password@api.example.test/start?token=secret#fragment'));

        self::assertCount(2, $requests);
        self::assertSame($expected !== null, $requests[1]->hasHeader('Referer'));
        self::assertSame($expected ?? '', $requests[1]->getHeaderLine('Referer'));
        self::assertSame(['rEfErEr' => ['https://private.example.test/?secret=configured']], $options->headers);
        self::assertSame('', $requests[1]->getUri()->getUserInfo());
    }

    public static function provideRedirectBodies(): iterable
    {
        yield '302 discards body' => [302, 'GET', ''];
        yield '307 preserves body' => [307, 'POST', '{"message":"hello"}'];
        yield '308 preserves body' => [308, 'POST', '{"message":"hello"}'];
    }

    public function testRedirectWithAutomaticCookiesDisabled(): void
    {
        $requests = [];
        $adapter = new FakeAdapter(
            static function (RequestInterface $request) use (&$requests): ResponseInterface {
                $requests[] = $request;

                return count($requests) === 1
                    ? new Response(statusCode: 302, headers: [
                        'Location' => '/next',
                        'Set-Cookie' => 'received=secret; Path=/; Secure',
                    ])
                    : new Response();
            },
        );
        $client = new Client(['cookies' => false], $adapter);
        $cookies = $client->getSession()->getCookies();
        $cookies->addRawCookie('stored=secret; Path=/; Secure', new Uri('https', 'api.example.test'));
        $client->sendRequest(new Request('GET', 'https://api.example.test/start'));

        self::assertCount(2, $requests);
        self::assertFalse($requests[0]->hasHeader('Cookie'));
        self::assertFalse($requests[1]->hasHeader('Cookie'));
        self::assertCount(1, $cookies);
        self::assertSame(['stored'], array_map(static fn($cookie) => $cookie->getName(), iterator_to_array($cookies)));
    }

    #[DataProvider('provideRedirectBodies')]
    public function testRedirectMethodAndBody(int $status, string $method, string $body): void
    {
        $requests = [];
        $adapter = new FakeAdapter(
            static function (RequestInterface $request) use (&$requests, $status): ResponseInterface {
                $requests[] = $request;

                return count($requests) === 1
                    ? new Response(statusCode: $status, headers: ['Location' => 'https://other.example.test/next'])
                    : new Response();
            },
        );
        $client = new Client(['headers' => ['Authorization' => 'Bearer secret']], $adapter);
        $request = (new Request('POST', 'https://api.example.test/start', '{"message":"hello"}'))
            ->withHeader('Content-Type', 'application/json');
        $client->sendRequest($request);

        self::assertCount(2, $requests);
        self::assertSame($method, $requests[1]->getMethod());
        self::assertSame($body, (string)$requests[1]->getBody());
        self::assertSame($body === '' ? '' : 'application/json', $requests[1]->getHeaderLine('Content-Type'));
        self::assertSame((string)strlen($body), $requests[1]->getHeaderLine('Content-Length'));
        self::assertFalse($requests[1]->hasHeader('Authorization'));
    }
}
