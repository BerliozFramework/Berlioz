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

use Berlioz\Http\Client\Cookies\Cookie;
use Berlioz\Http\Client\Cookies\CookiesManager;
use Berlioz\Http\Message\Request;
use Berlioz\Http\Message\Response;
use Berlioz\Http\Message\Uri;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CookieDomainTest extends TestCase
{
    public static function provideInvalidDomains(): iterable
    {
        yield 'double leading dot' => ['..example.test'];
        yield 'trailing dot' => ['example.test.'];
        yield 'empty label' => ['example..test'];
        yield 'space' => ['exam ple.test'];
        yield 'underscore' => ['exam_ple.test'];
        yield 'leading hyphen' => ['-example.test'];
        yield 'invalid punycode' => ['xn--.test'];
        yield 'port' => ['example.test:443'];
        yield 'invalid brackets' => ['[example.test]'];
        yield 'oversized label' => [str_repeat('a', 64) . '.test'];
    }

    #[DataProvider('provideInvalidDomains')]
    public function testMalformedDomainIsIgnored(string $domain): void
    {
        $cookies = new CookiesManager();
        $cookies->addCookiesFromResponse(
            new Uri('https', 'example.test'),
            new Response(null, 200, ['Set-Cookie' => [
                'sid=invalid; Domain=' . $domain,
                'sid=valid; Path=/',
            ]]),
        );

        $this->assertCount(1, $cookies);
        $request = $cookies->addCookiesToRequest(new Request('GET', 'https://example.test/'));
        $this->assertSame('sid=valid', $request->getHeaderLine('Cookie'));
    }

    public function testNonTransitionalIdnaScope(): void
    {
        $cookies = new CookiesManager();
        $cookies->addCookiesFromResponse(
            new Uri('https', 'xn--fa-hia.de'),
            new Response(null, 200, ['Set-Cookie' => ['sid=value; Domain=faß.de; Path=/']]),
        );

        $this->assertCount(1, $cookies);
        $request = $cookies->addCookiesToRequest(new Request('GET', new Uri('https', 'sub.faß.de')));
        $this->assertSame('sid=value', $request->getHeaderLine('Cookie'));
        $request = $cookies->addCookiesToRequest(new Request('GET', 'https://fass.de/'));
        $this->assertSame('', $request->getHeaderLine('Cookie'));
    }

    public function testUnicodeHostOnlyMatchesAsciiHost(): void
    {
        $cookie = Cookie::parse('sid=value', new Uri('https', 'bücher.de'));

        $this->assertSame('xn--bcher-kva.de', $cookie->getDomain());
        $this->assertTrue($cookie->isValidForUri(new Uri('https', 'xn--bcher-kva.de')));
        $this->assertFalse($cookie->isValidForUri(new Uri('https', 'sub.xn--bcher-kva.de')));
    }

    public function testIpv6RepresentationsMatch(): void
    {
        $cookie = Cookie::parse('sid=value; Domain=[::1]', new Uri('https', '[0:0:0:0:0:0:0:1]'));

        $this->assertTrue($cookie->isValidForUri(new Uri('https', '[::1]')));
        $this->assertFalse($cookie->isValidForUri(new Uri('https', '[::2]')));
    }
}
