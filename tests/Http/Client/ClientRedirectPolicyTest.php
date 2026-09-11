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
use Berlioz\Http\Message\Uri;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Class ClientRedirectPolicyTest.
 */
class ClientRedirectPolicyTest extends TestCase
{
    public static function provideOrigins(): iterable
    {
        yield 'path and query do not affect origin' => [
            'https://api.example.test/a?token=secret', 'https://api.example.test/b', true,
        ];
        yield 'case and explicit HTTPS port' => [
            'HTTPS://API.example.test/a', 'https://api.example.test:443/b', true,
        ];
        yield 'explicit HTTP port' => ['http://api.example.test:80/a', 'http://api.example.test/b', true];
        yield 'different port' => ['https://api.example.test/a', 'https://api.example.test:8443/b', false];
        yield 'different host' => ['https://api.example.test/a', 'https://other.example.test/b', false];
        yield 'subdomain' => ['https://api.example.test/a', 'https://sub.api.example.test/b', false];
        yield 'downgrade' => ['https://api.example.test/a', 'http://api.example.test/b', false];
        yield 'upgrade' => ['http://api.example.test/a', 'https://api.example.test/b', false];
        yield 'same port different scheme' => ['https://api.example.test:80/a', 'http://api.example.test/b', false];
        yield 'relative URI is not an origin' => ['/a', '/b', false];
        yield 'unsupported scheme' => ['ftp://api.example.test/a', 'ftp://api.example.test/b', false];
        yield 'IPv6 default port' => ['https://[::1]/a', 'https://[::1]:443/b', true];
        yield 'IPv6 different host' => ['https://[::1]/a', 'https://[::2]/b', false];
    }

    #[DataProvider('provideOrigins')]
    public function testIsSameOrigin(string $source, string $target, bool $expected): void
    {
        $method = new ReflectionMethod(Client::class, 'isSameOrigin');
        $client = new Client();
        $sourceUri = Uri::createFromString($source);
        $targetUri = Uri::createFromString($target);

        self::assertSame($expected, $method->invoke($client, $sourceUri, $targetUri));
        self::assertSame($expected, $method->invoke($client, $targetUri, $sourceUri));
    }

    public function testFilterRedirectHeaders(): void
    {
        $headers = [
            'AUTHORIZATION' => ['Bearer secret'],
            'authorization' => ['Bearer another-secret'],
            'pRoXy-AuThOrIzAtIoN' => ['Basic secret'],
            'cOoKiE' => ['sid=secret'],
            'X-API-KEY' => ['application-secret'],
            'X-Access-Token' => ['another-application-secret'],
            'Accept' => ['application/json'],
            'X-Trace-Id' => ['trace'],
        ];
        $original = $headers;
        $options = Options::make(
            ['redirectSensitiveHeaders' => ['x-access-token']],
            new Options(redirectSensitiveHeaders: ['x-api-key']),
        );
        $method = new ReflectionMethod(Client::class, 'filterRedirectHeaders');

        self::assertSame(
            ['Accept' => ['application/json'], 'X-Trace-Id' => ['trace']],
            $method->invoke(new Client(), $headers, $options),
        );
        self::assertSame($original, $headers);
        self::assertSame(['X-Api-Key', 'X-Access-Token'], $options->redirectSensitiveHeaders);
    }

    public function testFilterRedirectHeaders_mandatoryCredentials(): void
    {
        $method = new ReflectionMethod(Client::class, 'filterRedirectHeaders');

        self::assertSame([], $method->invoke(new Client(), [
            'Authorization' => 'Bearer secret',
            'Proxy-Authorization' => 'Basic secret',
            'Cookie' => 'sid=secret',
        ], new Options(redirectSensitiveHeaders: [])));
    }

    public static function provideRedirectUris(): iterable
    {
        yield 'relative path' => ['next', 'https://api.example.test/dir/next'];
        yield 'parent path' => ['../next', 'https://api.example.test/next'];
        yield 'absolute path' => ['/next', 'https://api.example.test/next'];
        yield 'network-path reference' => ['//other.example.test/next', 'https://other.example.test/next'];
        yield 'userinfo supplied by target' => [
            'https://user:password@other.example.test/next#fragment', 'https://other.example.test/next',
        ];
        yield 'same-origin supplied userinfo' => [
            'https://user:password@api.example.test/next', 'https://api.example.test/next',
        ];
        yield 'strip fragment' => ['/next?key=value#fragment', 'https://api.example.test/next?key=value'];
    }

    #[DataProvider('provideRedirectUris')]
    public function testPrepareRedirectUri(string $location, string $expected): void
    {
        $method = new ReflectionMethod(Client::class, 'prepareRedirectUri');
        $source = Uri::createFromString('https://api.example.test/dir/start');

        self::assertSame($expected, (string)$method->invoke(new Client(), $location, $source));
        self::assertSame('https://api.example.test/dir/start', (string)$source);
    }

    public function testPrepareRedirectUri_inheritedCredentials(): void
    {
        $method = new ReflectionMethod(Client::class, 'prepareRedirectUri');
        $client = new Client();
        $source = Uri::createFromString('https://user:password@api.example.test/start');

        self::assertSame('user:password', $method->invoke($client, '/next', $source)->getUserInfo());
        self::assertSame('', $method->invoke($client, '//other.example.test/next', $source)->getUserInfo());
    }

    public static function provideReferers(): iterable
    {
        $source = 'https://user:password@api.example.test/private?token=secret#fragment';
        yield 'same origin strips userinfo and fragment' => [
            $source, 'https://api.example.test/next', 'https://api.example.test/private?token=secret',
        ];
        yield 'cross origin strips path and query' => [
            $source, 'https://other.example.test/next', 'https://api.example.test/',
        ];
        yield 'cross port strips path and query' => [
            $source, 'https://api.example.test:8443/next', 'https://api.example.test/',
        ];
        yield 'downgrade omits referer' => [$source, 'http://api.example.test/next', null];
        yield 'cross host downgrade omits referer' => [$source, 'http://other.example.test/next', null];
        yield 'upgrade uses origin only' => [
            'http://api.example.test/private?token=secret', 'https://api.example.test/next', 'http://api.example.test/',
        ];
        yield 'origin preserves non-default port' => [
            'https://api.example.test:8443/private', 'https://other.example.test/', 'https://api.example.test:8443/',
        ];
        yield 'unsupported source scheme' => ['file:///private', 'https://api.example.test/', null];
        yield 'unsupported target scheme' => [$source, 'ftp://api.example.test/', null];
    }

    #[DataProvider('provideReferers')]
    public function testCreateRedirectReferer(string $source, string $target, ?string $expected): void
    {
        $method = new ReflectionMethod(Client::class, 'createRedirectReferer');
        $sourceUri = Uri::createFromString($source);
        $original = (string)$sourceUri;

        self::assertSame(
            $expected,
            $method->invoke(new Client(), $sourceUri, Uri::createFromString($target)),
        );
        self::assertSame($original, (string)$sourceUri);
    }
}
