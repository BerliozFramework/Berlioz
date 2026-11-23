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

use Berlioz\Http\Client\CredentialsOrigin;
use Berlioz\Http\Client\Exception\InvalidOriginException;
use Berlioz\Http\Message\Uri;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Class CredentialsOriginTest.
 */
class CredentialsOriginTest extends TestCase
{
    public static function provideValidOrigins(): iterable
    {
        yield 'exact' => ['https://api.partner.example', 'https', 'api.partner.example', 443, false];
        yield 'trailing slash' => ['https://api.partner.example/', 'https', 'api.partner.example', 443, false];
        yield 'normalized' => ['HTTPS://API.Partner.Example.', 'https', 'api.partner.example', 443, false];
        yield 'http default port' => ['http://api.partner.example', 'http', 'api.partner.example', 80, false];
        yield 'explicit port' => ['https://api.partner.example:8443', 'https', 'api.partner.example', 8443, false];
        yield 'wildcard' => ['https://*.partner.example', 'https', 'partner.example', 443, true];
        yield 'wildcard port' => ['https://*.partner.example:8443', 'https', 'partner.example', 8443, true];
        yield 'single label' => ['http://localhost:8080', 'http', 'localhost', 8080, false];
        yield 'IPv4' => ['https://10.0.0.1', 'https', '10.0.0.1', 443, false];
        yield 'IPv6' => ['https://[::1]:8443', 'https', '[::1]', 8443, false];
    }

    #[DataProvider('provideValidOrigins')]
    public function testFromString(string $origin, string $scheme, string $host, int $port, bool $wildcard): void
    {
        $credentialsOrigin = CredentialsOrigin::fromString($origin);

        self::assertSame($scheme, $credentialsOrigin->scheme);
        self::assertSame($host, $credentialsOrigin->host);
        self::assertSame($port, $credentialsOrigin->port);
        self::assertSame($wildcard, $credentialsOrigin->wildcard);
    }

    public static function provideInvalidOrigins(): iterable
    {
        yield 'wildcard only' => ['*'];
        yield 'wildcard host only' => ['https://*'];
        yield 'wildcard on TLD' => ['https://*.com'];
        yield 'wildcard on single label' => ['https://*.example'];
        yield 'wildcard not leftmost' => ['https://api.*.example.com'];
        yield 'partial wildcard label' => ['https://*api.partner.example'];
        yield 'double wildcard' => ['https://*.*.partner.example'];
        yield 'wildcard on IP' => ['https://*.10.0.0.1'];
        yield 'missing scheme' => ['partner.example'];
        yield 'protocol relative' => ['//partner.example'];
        yield 'unsupported scheme' => ['ftp://partner.example'];
        yield 'path' => ['https://partner.example/path'];
        yield 'query' => ['https://partner.example?foo=bar'];
        yield 'fragment' => ['https://partner.example#foo'];
        yield 'user info' => ['https://user:pass@partner.example'];
        yield 'empty label' => ['https://api..partner.example'];
        yield 'empty' => [''];
    }

    #[DataProvider('provideInvalidOrigins')]
    public function testFromString_invalid(string $origin): void
    {
        $this->expectException(InvalidOriginException::class);

        CredentialsOrigin::fromString($origin);
    }

    public static function provideMatches(): iterable
    {
        // Exact
        yield 'exact' => ['https://api.partner.example', 'https://api.partner.example/path?q=1', true];
        yield 'exact default port' => ['https://api.partner.example', 'https://api.partner.example:443/', true];
        yield 'exact case and trailing dot' => ['https://api.partner.example', 'https://API.Partner.Example./', true];
        yield 'exact other host' => ['https://api.partner.example', 'https://auth.partner.example/', false];
        yield 'exact subdomain' => ['https://api.partner.example', 'https://sub.api.partner.example/', false];
        yield 'exact other port' => ['https://api.partner.example', 'https://api.partner.example:8443/', false];
        yield 'exact other scheme' => ['https://api.partner.example', 'http://api.partner.example/', false];
        yield 'exact port other scheme' => ['https://api.partner.example:80', 'http://api.partner.example/', false];
        yield 'exact IPv6' => ['https://[::1]:8443', 'https://[::1]:8443/', true];
        yield 'relative URI' => ['https://api.partner.example', '/path', false];

        // Wildcard
        yield 'wildcard subdomain' => ['https://*.partner.example', 'https://auth.partner.example/', true];
        yield 'wildcard case and trailing dot' => ['https://*.partner.example', 'https://AUTH.Partner.Example./', true];
        yield 'wildcard parent domain' => ['https://*.partner.example', 'https://partner.example/', false];
        yield 'wildcard two levels' => ['https://*.partner.example', 'https://a.b.partner.example/', false];
        yield 'wildcard label boundary' => ['https://*.partner.example', 'https://evil-partner.example/', false];
        yield 'wildcard fake suffix' => ['https://*.partner.example', 'https://a.partner.example.evil.com/', false];
        yield 'wildcard other scheme' => ['https://*.partner.example', 'http://auth.partner.example/', false];
        yield 'wildcard other port' => ['https://*.partner.example', 'https://auth.partner.example:8443/', false];
        yield 'wildcard port' => ['https://*.partner.example:8443', 'https://auth.partner.example:8443/', true];
        yield 'wildcard numeric suffix' => ['https://*.0.1', 'https://10.0.0.1/', false];
    }

    #[DataProvider('provideMatches')]
    public function testMatches(string $origin, string $uri, bool $expected): void
    {
        self::assertSame($expected, CredentialsOrigin::fromString($origin)->matches(Uri::createFromString($uri)));
    }
}
