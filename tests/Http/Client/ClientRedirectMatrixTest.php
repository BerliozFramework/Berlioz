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
 * Class ClientRedirectMatrixTest.
 */
class ClientRedirectMatrixTest extends TestCase
{
    public static function provideRedirectMatrix(): iterable
    {
        $source = 'https://api.example.test/dir/start';
        $cases = [
            'same origin' => [$source, 'https://api.example.test/next', 'https://api.example.test/next', true],
            'case and default port' => [
                $source, 'HTTPS://API.EXAMPLE.TEST:443/next', 'https://api.example.test/next', true,
            ],
            'HTTP default port' => [
                'http://api.example.test/start', 'http://api.example.test:80/next', 'http://api.example.test/next', true,
            ],
            'different port' => [$source, 'https://api.example.test:8443/next', 'https://api.example.test:8443/next', false],
            'subdomain' => [$source, 'https://sub.api.example.test/next', 'https://sub.api.example.test/next', false],
            'downgrade' => [$source, 'http://api.example.test/next', 'http://api.example.test/next', false],
            'upgrade' => [
                'http://api.example.test/start', 'https://api.example.test/next', 'https://api.example.test/next', false,
            ],
            'relative path' => [$source, '../next', 'https://api.example.test/next', true],
            'root path' => [$source, '/next?value=1#fragment', 'https://api.example.test/next?value=1', true],
            'same-host network path' => [$source, '//api.example.test/next', 'https://api.example.test/next', true],
            'cross-host network path' => [$source, '//other.example.test/next', 'https://other.example.test/next', false],
            'target credentials' => [
                $source, 'https://user:secret@other.example.test/next', 'https://other.example.test/next', false,
            ],
            'same-origin target credentials' => [
                $source, 'https://user:secret@api.example.test/next', 'https://api.example.test/next', true,
            ],
            'IPv6 origin' => ['https://[::1]/start', '//[::1]:443/next', 'https://[::1]/next', true],
        ];

        // Includes 201, which the client already follows when Location is present.
        foreach ([201, 301, 302, 303, 307, 308] as $status) {
            foreach ($cases as $name => $case) {
                yield $status . '-' . $name => [$status, ...$case];
            }
        }
    }

    #[DataProvider('provideRedirectMatrix')]
    public function testRedirectMatrix(
        int $status,
        string $source,
        string $location,
        string $expectedUri,
        bool $sameOrigin,
    ): void {
        $requests = [];
        $client = new Client(['headers' => ['Authorization' => 'Bearer secret']], $this->createAdapter([
            new Response(statusCode: $status, headers: ['Location' => $location]),
            new Response(),
        ], $requests));
        $response = $client->sendRequest(new Request('GET', $source));

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(2, $requests);
        self::assertSame('Bearer secret', $requests[0]->getHeaderLine('Authorization'));
        self::assertSame($expectedUri, (string)$requests[1]->getUri());
        self::assertSame($sameOrigin, $requests[1]->hasHeader('Authorization'));
        self::assertSame($sameOrigin ? 'Bearer secret' : '', $requests[1]->getHeaderLine('Authorization'));
        self::assertSame('', $requests[1]->getUri()->getUserInfo());
        self::assertSame('', $requests[1]->getUri()->getFragment());
    }

    public static function provideUnfollowedResponses(): iterable
    {
        yield 'redirects disabled' => [false, 302, ['Location' => 'https://other.example.test/next']];
        yield 'Location on ordinary response' => [5, 200, ['Location' => 'https://other.example.test/next']];
        yield 'redirect without Location' => [5, 302, []];
    }

    #[DataProvider('provideUnfollowedResponses')]
    public function testResponseIsNotFollowed(int|false $followLocation, int $status, array $headers): void
    {
        $requests = [];
        $expected = new Response(statusCode: $status, headers: $headers);
        $client = new Client([
            'followLocation' => $followLocation,
            'headers' => ['Authorization' => 'Bearer secret'],
        ], $this->createAdapter([$expected], $requests));

        self::assertSame($expected, $client->sendRequest(new Request('GET', 'https://api.example.test/start')));
        self::assertCount(1, $requests);
        self::assertSame('Bearer secret', $requests[0]->getHeaderLine('Authorization'));
    }

    public function testApplicationSecretsMergeAcrossOptions(): void
    {
        $requests = [];
        $global = new Options(
            headers: ['Authorization' => ['Bearer global'], 'X-Api-Key' => ['global-key']],
            redirectSensitiveHeaders: ['x-api-key'],
        );
        $call = [
            'headers' => ['AUTHORIZATION' => 'Bearer call', 'X-Access-Token' => 'call-key'],
            'redirectSensitiveHeaders' => ['x-access-token'],
        ];
        $original = $call;
        $client = new Client($global, $this->createAdapter([
            new Response(statusCode: 302, headers: ['Location' => 'https://other.example.test/next']),
            new Response(),
        ], $requests));
        $client->sendRequest(new Request('GET', 'https://api.example.test/start'), $call);

        self::assertCount(2, $requests);
        self::assertSame('Bearer call', $requests[0]->getHeaderLine('Authorization'));
        self::assertSame('global-key', $requests[0]->getHeaderLine('X-Api-Key'));
        self::assertSame('call-key', $requests[0]->getHeaderLine('X-Access-Token'));
        foreach (['Authorization', 'X-Api-Key', 'X-Access-Token'] as $name) {
            self::assertFalse($requests[1]->hasHeader($name));
        }
        self::assertSame($original, $call);
        self::assertSame(['Authorization' => ['Bearer global'], 'X-Api-Key' => ['global-key']], $global->headers);
        self::assertSame(['x-api-key'], $global->redirectSensitiveHeaders);
    }

    public function testRelativeInitialRequestUsesResolvedOrigin(): void
    {
        $requests = [];
        $client = new Client([
            'baseUri' => 'https://api.example.test:8443/dir/',
            'headers' => ['Authorization' => 'Bearer secret'],
        ], $this->createAdapter([
            new Response(statusCode: 302, headers: ['Location' => '../next']),
            new Response(statusCode: 302, headers: ['Location' => '//api.example.test/end']),
            new Response(),
        ], $requests));
        $client->sendRequest(new Request('GET', 'start'));

        self::assertCount(3, $requests);
        self::assertSame('https://api.example.test:8443/dir/start', (string)$requests[0]->getUri());
        self::assertSame('https://api.example.test:8443/next', (string)$requests[1]->getUri());
        self::assertSame('Bearer secret', $requests[1]->getHeaderLine('Authorization'));
        self::assertSame('https://api.example.test/end', (string)$requests[2]->getUri());
        self::assertFalse($requests[2]->hasHeader('Authorization'));
    }

    public function testManagedCookiesAreRecomputedForEachDestination(): void
    {
        $requests = [];
        $client = new Client(['headers' => ['Authorization' => 'Bearer secret']], $this->createAdapter([
            new Response(statusCode: 302, headers: ['Location' => 'https://other.example.test/next']),
            new Response(statusCode: 302, headers: [
                'Location' => '/end',
                'Set-Cookie' => 'received=destination; Path=/; Secure',
            ]),
            new Response(),
        ], $requests));
        $cookies = $client->getSession()->getCookies();
        $cookies->addRawCookie('source=secret; Path=/; Secure', new Uri('https', 'api.example.test'));
        $cookies->addRawCookie('target=destination; Path=/; Secure', new Uri('https', 'other.example.test'));
        $client->sendRequest(new Request('GET', 'https://api.example.test/start'));

        self::assertCount(3, $requests);
        self::assertSame('source=secret', $requests[0]->getHeaderLine('Cookie'));
        self::assertSame('target=destination', $requests[1]->getHeaderLine('Cookie'));
        self::assertSame('target=destination; received=destination', $requests[2]->getHeaderLine('Cookie'));
        self::assertFalse($requests[1]->hasHeader('Authorization'));
        self::assertFalse($requests[2]->hasHeader('Authorization'));
    }

    public function testRefererAcrossDowngradeAndUpgradeChain(): void
    {
        $requests = [];
        $client = new Client(['headers' => [
            'Authorization' => 'Bearer secret',
            'Referer' => 'https://private.example.test/?secret=configured',
        ]], $this->createAdapter([
            new Response(statusCode: 302, headers: ['Location' => 'http://other.example.test/next?value=1']),
            new Response(statusCode: 302, headers: ['Location' => 'https://api.example.test/end']),
            new Response(),
        ], $requests));
        $client->sendRequest(new Request('GET', 'https://api.example.test/start?token=secret#fragment'));

        self::assertCount(3, $requests);
        self::assertFalse($requests[1]->hasHeader('Referer'));
        self::assertSame('http://other.example.test/', $requests[2]->getHeaderLine('Referer'));
        self::assertFalse($requests[1]->hasHeader('Authorization'));
        self::assertFalse($requests[2]->hasHeader('Authorization'));
    }

    /**
     * @param ResponseInterface[] $responses
     * @param RequestInterface[] $requests
     */
    private function createAdapter(array $responses, array &$requests): FakeAdapter
    {
        return new FakeAdapter(
            static function (RequestInterface $request) use ($responses, &$requests): ResponseInterface {
                $index = count($requests);
                $requests[] = $request;
                self::assertArrayHasKey($index, $responses, 'Unexpected additional request');

                return $responses[$index];
            },
        );
    }
}
