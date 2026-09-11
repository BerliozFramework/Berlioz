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
}
