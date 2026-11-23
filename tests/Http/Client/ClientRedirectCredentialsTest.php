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
use Berlioz\Http\Client\CredentialsOrigin;
use Berlioz\Http\Client\Exception\InvalidOriginException;
use Berlioz\Http\Client\HttpContext;
use Berlioz\Http\Client\Tests\Adapter\FakeAdapter;
use Berlioz\Http\Message\Request;
use Berlioz\Http\Message\Response;
use Berlioz\Http\Message\Uri;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReflectionMethod;

/**
 * Class ClientRedirectCredentialsTest.
 */
class ClientRedirectCredentialsTest extends TestCase
{
    private const CONTEXT = [
        'proxy' => 'http://user:pass@proxy.example.test:3128',
        'ssl_verify_peer' => false,
        'ssl_cafile' => '/ca.pem',
        'ssl_local_cert' => '/client.pem',
        'ssl_local_pk' => '/client.key',
        'ssl_local_cert_passphrase' => 'cert-secret',
        'ssl_local_passphrase' => 'key-secret',
    ];

    /**
     * Send a request following the given redirections and collect the context given to the adapter on each hop.
     *
     * @param string $start
     * @param string[] $locations
     * @param array|null $context
     * @param Client|null $client
     *
     * @return array<HttpContext|null>
     */
    private function sendAndCollectContexts(
        string $start,
        array $locations,
        ?array $context = self::CONTEXT,
        ?Client &$client = null,
    ): array {
        $contexts = [];
        $adapter = new FakeAdapter(
            static function (
                RequestInterface $request,
                ?HttpContext $context,
            ) use (
                &$contexts,
                $locations,
            ): ResponseInterface {
                $contexts[] = $context;

                if (null !== ($location = $locations[count($contexts) - 1] ?? null)) {
                    return (new Response(statusCode: 302))->withHeader('Location', $location);
                }

                return new Response(statusCode: 200);
            },
        );
        $client = new Client(null === $context ? [] : ['context' => $context], $adapter);
        $client->sendRequest(new Request('GET', $start));

        self::assertCount(count($locations) + 1, $contexts);

        return $contexts;
    }

    private static function assertOriginCredentials(?HttpContext $context): void
    {
        self::assertNotNull($context);
        self::assertTrue($context->hasOriginCredentials());
        self::assertSame('/client.pem', $context->ssl_local_cert);
        self::assertSame('/client.key', $context->ssl_local_pk);
        self::assertSame('cert-secret', $context->ssl_local_cert_passphrase);
        self::assertSame('key-secret', $context->ssl_local_passphrase);
    }

    private static function assertNoOriginCredentials(?HttpContext $context): void
    {
        self::assertNotNull($context);
        self::assertFalse($context->hasOriginCredentials());
        self::assertNull($context->ssl_local_cert);
        self::assertNull($context->ssl_local_pk);
        self::assertNull($context->ssl_local_cert_passphrase);
        self::assertNull($context->ssl_local_passphrase);
    }

    public function testRedirect_sameOriginKeepsCredentials(): void
    {
        $contexts = $this->sendAndCollectContexts(
            'https://api.example.test/start',
            ['https://API.example.test:443/next', '/last'],
        );

        array_map(self::assertOriginCredentials(...), $contexts);
    }

    public function testRedirect_crossOriginDropsCredentials(): void
    {
        $contexts = $this->sendAndCollectContexts(
            'https://api.example.test/start',
            ['https://evil.example.test/collect'],
            client: $client,
        );

        self::assertOriginCredentials($contexts[0]);
        self::assertNoOriginCredentials($contexts[1]);

        // Transport settings are kept
        self::assertSame(self::CONTEXT['proxy'], $contexts[1]->proxy);
        self::assertFalse($contexts[1]->ssl_verify_peer);
        self::assertSame('/ca.pem', $contexts[1]->ssl_cafile);

        // Client configuration is untouched
        self::assertSame('/client.pem', $client->getOptions()->context->ssl_local_cert);
        self::assertTrue($client->getOptions()->context->hasOriginCredentials());
    }

    public static function provideCrossOriginTargets(): iterable
    {
        yield 'other host' => ['https://other.example.test/'];
        yield 'subdomain' => ['https://sub.api.example.test/'];
        yield 'parent domain' => ['https://example.test/'];
        yield 'other port' => ['https://api.example.test:8443/'];
        yield 'downgrade' => ['http://api.example.test/'];
        yield 'protocol relative' => ['//evil.example.test/'];
        yield 'user info' => ['https://user:pass@evil.example.test/'];
    }

    #[DataProvider('provideCrossOriginTargets')]
    public function testRedirect_crossOriginTargets(string $location): void
    {
        $contexts = $this->sendAndCollectContexts('https://api.example.test/start', [$location]);

        self::assertOriginCredentials($contexts[0]);
        self::assertNoOriginCredentials($contexts[1]);
    }

    public function testRedirect_downgradeIsSticky(): void
    {
        $contexts = $this->sendAndCollectContexts(
            'https://api.example.test/start',
            ['https://cdn.example.test/', 'https://api.example.test/back'],
        );

        self::assertOriginCredentials($contexts[0]);
        self::assertNoOriginCredentials($contexts[1]);
        self::assertNoOriginCredentials($contexts[2]);
    }

    public function testRedirect_approvedOrigins(): void
    {
        $contexts = $this->sendAndCollectContexts(
            'https://api.example.test/start',
            [
                'https://auth.example.test:443/token',
                'https://eu.partner.example/',
                'https://api.example.test/back',
                'https://a.b.partner.example/',
                'https://api.example.test/end',
            ],
            [
                ...self::CONTEXT,
                'credentials_origins' => ['https://auth.example.test', 'https://*.partner.example'],
            ],
        );

        self::assertOriginCredentials($contexts[0]);
        self::assertOriginCredentials($contexts[1]);
        self::assertOriginCredentials($contexts[2]);
        self::assertOriginCredentials($contexts[3]);
        self::assertNoOriginCredentials($contexts[4]);
        self::assertNoOriginCredentials($contexts[5]);
    }

    public function testRedirect_withoutContext(): void
    {
        $contexts = $this->sendAndCollectContexts(
            'https://api.example.test/start',
            ['https://api.example.test/next', 'https://evil.example.test/'],
            null,
        );

        self::assertNull($contexts[0]);
        self::assertNull($contexts[1]);
        self::assertNoOriginCredentials($contexts[2]);
        self::assertEquals((new HttpContext())->withoutOriginCredentials(), $contexts[2]);
    }

    public function testRedirect_invalidApprovedOrigin(): void
    {
        $this->expectException(InvalidOriginException::class);

        $this->sendAndCollectContexts(
            'https://api.example.test/start',
            [],
            ['credentials_origins' => ['https://*.test']],
        );
    }

    public static function provideCredentialOrigins(): iterable
    {
        yield 'same origin' => ['https://api.example.test/a', [], true];
        yield 'same origin explicit port' => ['https://api.example.test:443/a', [], true];
        yield 'other origin' => ['https://other.example.test/', [], false];
        yield 'approved exact' => ['https://other.example.test/', ['https://other.example.test'], true];
        yield 'approved wildcard' => ['https://other.example.test/', ['https://*.example.test'], true];
        yield 'approved other port' => ['https://other.example.test:8443/', ['https://other.example.test'], false];
        yield 'approved other scheme' => ['http://other.example.test/', ['https://other.example.test'], false];
    }

    #[DataProvider('provideCredentialOrigins')]
    public function testIsCredentialOrigin(string $target, array $allowed, bool $expected): void
    {
        $method = new ReflectionMethod(Client::class, 'isCredentialOrigin');

        self::assertSame(
            $expected,
            $method->invoke(
                new Client(),
                Uri::createFromString('https://api.example.test/start'),
                Uri::createFromString($target),
                array_map(CredentialsOrigin::fromString(...), $allowed),
            ),
        );
    }
}
