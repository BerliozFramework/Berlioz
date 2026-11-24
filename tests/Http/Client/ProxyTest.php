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

use Berlioz\Http\Client\Exception\RequestException;
use Berlioz\Http\Client\HttpContext;
use Berlioz\Http\Client\Proxy;
use Berlioz\Http\Message\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProxyTest extends TestCase
{
    public function testFromContext_none()
    {
        $request = new Request('GET', 'https://getberlioz.com/');

        $this->assertNull(Proxy::fromContext(null, $request));
        $this->assertNull(Proxy::fromContext(new HttpContext(), $request));
    }

    public static function provideValidProxies(): array
    {
        return [
            ['http://proxy.local', 'http', 'proxy.local', 80, null, null],
            ['https://proxy.local', 'https', 'proxy.local', 443, null, null],
            ['HTTPS://proxy.local:8443/', 'https', 'proxy.local', 8443, null, null],
            ['http://user@[::1]:3128', 'http', '[::1]', 3128, 'user', null],
            ['http://us%40er:p%3Ass@proxy.local:3128', 'http', 'proxy.local', 3128, 'us@er', 'p:ss'],
            ['proxy.local:3128', 'http', 'proxy.local', 3128, null, null],
            ['proxy.local', 'http', 'proxy.local', 80, null, null],
            ['user:pass@proxy.local:3128', 'http', 'proxy.local', 3128, 'user', 'pass'],
        ];
    }

    #[DataProvider('provideValidProxies')]
    public function testFromContext(
        string $value,
        string $scheme,
        string $host,
        int $port,
        ?string $user,
        ?string $password,
    ) {
        $proxy = Proxy::fromContext(new HttpContext(proxy: $value), new Request('GET', 'https://getberlioz.com/'));

        $this->assertSame($scheme, $proxy->scheme);
        $this->assertSame($host, $proxy->host);
        $this->assertSame($port, $proxy->port);
        $this->assertSame($user, $proxy->user);
        $this->assertSame($password, $proxy->password);
        $this->assertSame($scheme === 'https', $proxy->isSecure());
        $this->assertSame(sprintf('%s://%s:%d', $scheme, $host, $port), $proxy->getUrl());
    }

    public static function provideInvalidProxies(): array
    {
        return [
            'socks5' => ['socks5://proxy.local:1080'],
            'socks5h' => ['socks5h://proxy.local:1080'],
            'ftp' => ['ftp://proxy.local'],
            'no scheme with path' => ['proxy.local/path'],
            'no host' => ['http://'],
            'path' => ['http://proxy.local/path'],
            'query' => ['http://proxy.local?foo=bar'],
            'fragment' => ['http://proxy.local#foo'],
            'malformed' => ['http://:80'],
            'empty' => [''],
        ];
    }

    #[DataProvider('provideInvalidProxies')]
    public function testFromContext_invalid(string $value)
    {
        $this->expectException(RequestException::class);

        Proxy::fromContext(new HttpContext(proxy: $value), new Request('GET', 'https://getberlioz.com/'));
    }

    public function testDebugInfo_hidesPassword()
    {
        $dump = print_r(new Proxy('http', 'proxy.local', 80, 'user', 's3cr3t'), true);

        $this->assertStringContainsString('user', $dump);
        $this->assertStringNotContainsString('s3cr3t', $dump);
    }

    public function testGetAuthorization()
    {
        $this->assertNull((new Proxy('http', 'proxy.local', 80))->getAuthorization());
        $this->assertSame(
            'Basic ' . base64_encode('user:'),
            (new Proxy('http', 'proxy.local', 80, 'user'))->getAuthorization(),
        );
        $this->assertSame(
            'Basic ' . base64_encode('user:pass'),
            (new Proxy('http', 'proxy.local', 80, 'user', 'pass'))->getAuthorization(),
        );
    }
}
