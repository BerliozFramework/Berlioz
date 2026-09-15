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

namespace Berlioz\Http\Client\Tests\Cookies;

use Berlioz\Http\Client\Cookies\CookiesManager;
use Berlioz\Http\Client\Exception\HttpClientException;
use Berlioz\Http\Client\Exception\InvalidCookieDomainException;
use Berlioz\Http\Message\Request;
use Berlioz\Http\Message\Response;
use Berlioz\Http\Message\Uri;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CookieIsolationTest extends TestCase
{
    public static function provideResponseDomains(): iterable
    {
        yield 'parent domain' => ['api.example.test', 'example.test', 'example.test', true];
        yield 'parent normalized' => ['api.example.test', '.EXAMPLE.test', 'sub.example.test', true];
        yield 'child domain' => ['example.test', 'api.example.test', 'api.example.test', false];
        yield 'sibling domain' => ['api.example.test', 'www.example.test', 'www.example.test', false];
        yield 'false suffix' => ['badexample.test', 'example.test', 'example.test', false];
        yield 'IPv4 exact' => ['127.0.0.1', '127.0.0.1', '127.0.0.1', true];
        yield 'IPv4 suffix' => ['127.0.0.1', '0.0.1', '0.0.1', false];
        yield 'empty source host' => ['', 'example.test', 'example.test', false];
    }

    #[DataProvider('provideResponseDomains')]
    public function testDomainValidatedBeforeStorage(
        string $source,
        string $domain,
        string $destination,
        bool $accepted,
    ): void {
        $cookies = new CookiesManager();
        $cookies->addCookiesFromResponse(
            new Uri('https', $source),
            new Response(null, 200, ['Set-Cookie' => ['sid=value; Domain=' . $domain . '; Path=/']]),
        );

        $this->assertCount($accepted ? 1 : 0, $cookies);
        $request = $cookies->addCookiesToRequest(new Request('GET', new Uri('https', $destination)));
        $this->assertSame($accepted ? 'sid=value' : '', $request->getHeaderLine('Cookie'));
    }

    public function testExplicitRawInsertionReportsDomainRejection(): void
    {
        $cookies = new CookiesManager();

        $this->expectException(InvalidCookieDomainException::class);
        $cookies->addRawCookie('sid=value; Domain=victim.test', new Uri('https', 'attacker.test'));
    }

    public function testResponseParsingErrorsAreNotSilenced(): void
    {
        $cookies = new CookiesManager();

        $this->expectException(HttpClientException::class);
        $cookies->addCookiesFromResponse(
            new Uri('https', 'example.test'),
            new Response(null, 200, ['Set-Cookie' => ['sid=value; Expires=not-a-date']]),
        );
    }

    public static function provideCookieScopes(): iterable
    {
        yield 'host-only exact host' => ['sid=secret; Path=/', 'https://example.test/', 'sid=secret'];
        yield 'host-only subdomain' => ['sid=secret; Path=/', 'https://sub.example.test/', ''];
        yield 'explicit domain subdomain' => [
            'sid=secret; Domain=example.test; Path=/', 'https://sub.example.test/', 'sid=secret',
        ];
        yield 'domain boundary' => [
            'sid=secret; Domain=example.test; Path=/', 'https://badexample.test/', '',
        ];
        yield 'path exact' => ['sid=secret; Path=/admin', 'https://example.test/admin', 'sid=secret'];
        yield 'path child' => ['sid=secret; Path=/admin', 'https://example.test/admin/users', 'sid=secret'];
        yield 'path false prefix' => ['sid=secret; Path=/admin', 'https://example.test/administrator', ''];
        yield 'default path inside' => ['sid=secret', 'https://example.test/account/profile', 'sid=secret'];
        yield 'default path outside' => ['sid=secret', 'https://example.test/other', ''];
        yield 'invalid path outside' => ['sid=secret; Path=invalid', 'https://example.test/other', ''];
        yield 'invalid path inside' => [
            'sid=secret; Path=invalid', 'https://example.test/account/profile', 'sid=secret',
        ];
        yield 'secure over HTTP' => ['sid=secret; Path=/; Secure', 'http://example.test/', ''];
    }

    #[DataProvider('provideCookieScopes')]
    public function testResponseCookieScope(string $cookie, string $destination, string $expected): void
    {
        $cookies = new CookiesManager();
        $cookies->addCookiesFromResponse(
            Uri::createFromString('https://example.test/account/login'),
            new Response(null, 200, ['Set-Cookie' => [$cookie]]),
        );

        $request = $cookies->addCookiesToRequest(new Request('GET', $destination));

        $this->assertSame($expected, $request->getHeaderLine('Cookie'));
    }

    public function testThirdPartyCookieIsRejectedWithoutDiscardingValidCookies(): void
    {
        $cookies = new CookiesManager();
        $cookies->addCookiesFromResponse(
            Uri::createFromString('https://attacker.test/'),
            new Response(null, 200, ['Set-Cookie' => [
                'sid=injected; Domain=victim.test; Path=/',
                'local=valid; Path=/',
            ]]),
        );

        $request = $cookies->addCookiesToRequest(new Request('GET', 'https://victim.test/'));
        $this->assertSame('', $request->getHeaderLine('Cookie'));
        $this->assertCount(1, $cookies);
        $request = $cookies->addCookiesToRequest(new Request('GET', 'https://attacker.test/'));
        $this->assertSame('local=valid', $request->getHeaderLine('Cookie'));
    }

    public static function provideThirdPartyUpdates(): iterable
    {
        yield 'overwrite' => ['sid=injected; Domain=victim.test; Path=/'];
        yield 'delete' => ['sid=deleted; Domain=victim.test; Path=/; Max-Age=-1'];
    }

    #[DataProvider('provideThirdPartyUpdates')]
    public function testThirdPartyCannotChangeExistingCookie(string $cookie): void
    {
        $cookies = new CookiesManager();
        $cookies->addCookiesFromResponse(
            Uri::createFromString('https://victim.test/'),
            new Response(null, 200, ['Set-Cookie' => ['sid=legitimate; Path=/']]),
        );
        $cookies->addCookiesFromResponse(
            Uri::createFromString('https://attacker.test/'),
            new Response(null, 200, ['Set-Cookie' => [$cookie]]),
        );

        $request = $cookies->addCookiesToRequest(new Request('GET', 'https://victim.test/'));

        $this->assertSame('sid=legitimate', $request->getHeaderLine('Cookie'));
        $this->assertCount(1, $cookies);
    }
}
