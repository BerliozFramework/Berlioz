<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2021 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\Http\Client\Tests\Cookies;

use Berlioz\Http\Client\Cookies\Cookie;
use Berlioz\Http\Message\Uri;
use DateInterval;
use DateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CookieTest extends TestCase
{
    public static function provideDomainScopes(): iterable
    {
        yield 'host-only' => ['sid=value', 'https://example.test/', 'https://sub.example.test/', true, false];
        yield 'empty domain' => ['sid=value; Domain=', 'https://example.test/', 'https://example.test/', true, true];
        yield 'explicit domain' => [
            'sid=value; Domain=example.test', 'https://example.test/', 'https://sub.example.test/', false, true,
        ];
        yield 'leading dot and case' => [
            'sid=value; Domain=.EXAMPLE.test', 'https://example.test/', 'https://SUB.example.test/', false, true,
        ];
        yield 'IPv4 exact' => ['sid=value', 'https://127.0.0.1/', 'https://127.0.0.1/', true, true];
        yield 'IPv4 suffix' => [
            'sid=value; Domain=127.0.0.1', 'https://127.0.0.1/', 'https://sub.127.0.0.1/', false, false,
        ];
        yield 'IPv6 exact' => ['sid=value', 'https://[::1]/', 'https://[::1]/', true, true];
        yield 'IPv6 different' => ['sid=value', 'https://[::1]/', 'https://[::2]/', true, false];
        yield 'empty target host' => ['sid=value', 'https://example.test/', '/relative', true, false];
    }

    #[DataProvider('provideDomainScopes')]
    public function testDomainScope(
        string $raw,
        string $source,
        string $target,
        bool $hostOnly,
        bool $valid,
    ): void {
        $cookie = Cookie::parse($raw, Uri::createFromString($source));

        $this->assertSame($hostOnly, $cookie->isHostOnly());
        $this->assertSame($hostOnly, $cookie->getArrayCopy()['hostOnly']);
        $this->assertSame($valid, $cookie->isValidForUri(Uri::createFromString($target)));
    }

    public static function provideDefaultPaths(): iterable
    {
        yield 'empty' => ['', '/'];
        yield 'root' => ['/', '/'];
        yield 'single segment' => ['/login', '/'];
        yield 'nested' => ['/account/login', '/account'];
        yield 'trailing slash' => ['/account/', '/account'];
    }

    #[DataProvider('provideDefaultPaths')]
    public function testDefaultPath(string $path, string $expected): void
    {
        $cookie = Cookie::parse('sid=value', new Uri('https', 'example.test', null, $path));

        $this->assertSame($expected, $cookie->getPath());
    }

    public function testUpdateScopeAndSameSite(): void
    {
        $uri = Uri::createFromString('https://example.test/');
        $subdomain = Uri::createFromString('https://sub.example.test/');
        $cookie = Cookie::parse('sid=old; Domain=.EXAMPLE.test; Path=/; SameSite=Lax', $uri);
        $replacement = Cookie::parse('sid=new; Path=/; SameSite=Strict', $uri);

        $this->assertTrue($cookie->update($replacement));
        $this->assertTrue($cookie->isHostOnly());
        $this->assertSame('Strict', $cookie->getSameSite());
        $this->assertSame('new', $cookie->getValue());
        $this->assertFalse($cookie->isValidForUri($subdomain));
        $this->assertStringNotContainsString('Domain=', $cookie->getRequestHeader());

        $this->assertTrue($cookie->update(Cookie::parse('sid=domain; Domain=example.test; Path=/', $uri)));
        $this->assertFalse($cookie->isHostOnly());
        $this->assertNull($cookie->getSameSite());
        $this->assertTrue($cookie->isValidForUri($subdomain));
        $this->assertStringContainsString('Domain=example.test', $cookie->getRequestHeader());
    }

    public function testParseMaxAge()
    {
        $dateTime = new DateTime();
        $cookie = Cookie::parse('test=value; max-age=100; Domain=getberlioz.com');

        $this->assertEquals(
            $cookie->getExpires()->format('Y-m-d H:i:s'),
            $dateTime->add(new DateInterval('PT100S'))->format('Y-m-d H:i:s')
        );
    }

    public function testParseNegativeMaxAge()
    {
        $dateTime = new DateTime();
        $cookie = Cookie::parse('test=value; max-age=-100; Domain=getberlioz.com');

        $this->assertEquals(
            $cookie->getExpires()->format('Y-m-d H:i:s'),
            $dateTime->sub(new DateInterval('PT100S'))->format('Y-m-d H:i:s')
        );
    }

    public function testIsSame()
    {
        $cookie = Cookie::parse('foo=value; domain=getberlioz.com');

        $this->assertTrue($cookie->isSame($cookie));
        $this->assertTrue(
            $cookie->isSame(
                Cookie::parse(
                    'foo=value2',
                    Uri::createFromString('https://getberlioz.com')
                )
            )
        );
        $this->assertFalse($cookie->isSame(Cookie::parse('bar=value; domain=getberlioz.com')));
        $this->assertFalse($cookie->isSame(Cookie::parse('foo=value; domain=getberlioz.com; path=/qux/')));
        $this->assertFalse($cookie->isSame(Cookie::parse('foo=value; domain=gethectororm.com')));
        $this->assertTrue($cookie->isSame(Cookie::parse('foo=value; domain=getberlioz.com; version=qux')));
    }

    public function testUpdate()
    {
        $cookie = Cookie::parse('foo=value; domain=getberlioz.com; path=/qux/');

        $this->assertFalse($cookie->update(Cookie::parse('bar=value; domain=getberlioz.com')));
        $this->assertFalse($cookie->update(Cookie::parse('foo=value2; domain=getberlioz.com')));

        $this->assertTrue($cookie->update(Cookie::parse('foo=value2; domain=getberlioz.com; path=/qux/')));
        $this->assertEquals('value2', $cookie->getValue());
    }
}
