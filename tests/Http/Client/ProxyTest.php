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
    use ProxyEnvironmentTrait;

    public function testFromContext_none()
    {
        $request = new Request('GET', 'https://getberlioz.com/');
        $this->setProxyEnv(['https_proxy' => 'http://env.local:3128']);

        $this->assertNull(Proxy::fromContext(null, $request));
        $this->assertNull(Proxy::fromContext(new HttpContext(), $request));
        $this->assertNull(Proxy::fromContext(new HttpContext(proxy: false), $request));
    }

    public function testResolve()
    {
        $request = new Request('GET', 'https://getberlioz.com/');
        $this->setProxyEnv(['https_proxy' => 'http://env.local:3128']);

        $this->assertSame('http://env.local:3128', Proxy::resolve(null, $request)?->getUrl());
        $this->assertSame('http://env.local:3128', Proxy::resolve(new HttpContext(), $request)?->getUrl());
        $this->assertNull(Proxy::resolve(new HttpContext(proxy: false), $request));
        $this->assertSame(
            'http://configured.local:8080',
            Proxy::resolve(new HttpContext(proxy: 'configured.local:8080'), $request)?->getUrl(),
        );
    }

    public function testResolve_noEnvironment()
    {
        $this->assertNull(Proxy::resolve(new HttpContext(), new Request('GET', 'https://getberlioz.com/')));
    }

    public static function provideEnvironments(): array
    {
        return [
            'https' => [['https_proxy' => 'http://s.local:1'], 'https://getberlioz.com/', 'http://s.local:1'],
            'HTTPS' => [['HTTPS_PROXY' => 'http://s.local:1'], 'https://getberlioz.com/', 'http://s.local:1'],
            'https before HTTPS' => [
                ['https_proxy' => 'http://lower.local:1', 'HTTPS_PROXY' => 'http://upper.local:1'],
                'https://getberlioz.com/',
                'http://lower.local:1',
            ],
            'https ignored for http' => [['https_proxy' => 'http://s.local:1'], 'http://getberlioz.com/', null],
            'http' => [['http_proxy' => 'http://h.local:1'], 'http://getberlioz.com/', 'http://h.local:1'],
            'http ignored for https' => [['http_proxy' => 'http://h.local:1'], 'https://getberlioz.com/', null],
            'HTTP never used (httpoxy)' => [['HTTP_PROXY' => 'http://h.local:1'], 'http://getberlioz.com/', null],
            'all for http' => [['all_proxy' => 'http://a.local:1'], 'http://getberlioz.com/', 'http://a.local:1'],
            'ALL for https' => [['ALL_PROXY' => 'http://a.local:1'], 'https://getberlioz.com/', 'http://a.local:1'],
            'specific before all' => [
                ['all_proxy' => 'http://a.local:1', 'https_proxy' => 'http://s.local:1'],
                'https://getberlioz.com/',
                'http://s.local:1',
            ],
            'empty value' => [
                ['https_proxy' => ' ', 'all_proxy' => 'a.local:1'],
                'https://getberlioz.com/',
                'http://a.local:1',
            ],
            'without scheme' => [['http_proxy' => 'h.local:3128'], 'http://getberlioz.com/', 'http://h.local:3128'],
            'https proxy' => [['https_proxy' => 'https://s.local'], 'https://getberlioz.com/', 'https://s.local:443'],
            'no_proxy' => [
                ['https_proxy' => 'http://s.local:1', 'no_proxy' => 'example.com, getberlioz.com'],
                'https://www.getberlioz.com/',
                null,
            ],
            'NO_PROXY' => [
                ['https_proxy' => 'http://s.local:1', 'NO_PROXY' => 'getberlioz.com'],
                'https://getberlioz.com/',
                null,
            ],
            'no_proxy not matching' => [
                ['https_proxy' => 'http://s.local:1', 'no_proxy' => 'example.com'],
                'https://getberlioz.com/',
                'http://s.local:1',
            ],
        ];
    }

    #[DataProvider('provideEnvironments')]
    public function testFromEnvironment(array $env, string $uri, ?string $expected)
    {
        $this->setProxyEnv($env);

        $this->assertSame($expected, Proxy::fromEnvironment(new Request('GET', $uri))?->getUrl());
    }

    public function testFromEnvironment_credentials()
    {
        $this->setProxyEnv(['https_proxy' => 'http://us%40er:p%3Ass@s.local:3128']);

        $proxy = Proxy::fromEnvironment(new Request('GET', 'https://getberlioz.com/'));

        $this->assertSame('us@er', $proxy->user);
        $this->assertSame('p:ss', $proxy->password);
    }

    public static function provideInvalidEnvironmentProxies(): array
    {
        return [
            'socks5' => ['socks5://proxy.local:1080'],
            'socks5h' => ['socks5h://proxy.local:1080'],
            'path' => ['http://proxy.local/path'],
        ];
    }

    #[DataProvider('provideInvalidEnvironmentProxies')]
    public function testFromEnvironment_invalid(string $value)
    {
        $this->setProxyEnv(['all_proxy' => $value]);

        $this->expectException(RequestException::class);

        Proxy::fromEnvironment(new Request('GET', 'https://getberlioz.com/'));
    }

    public static function provideNoProxy(): array
    {
        return [
            'all' => ['getberlioz.com', 443, '*', true],
            'empty' => ['getberlioz.com', 443, ' , ', false],
            'exact host' => ['getberlioz.com', 443, 'getberlioz.com', true],
            'case insensitive' => ['GetBerlioz.COM', 443, 'getberlioz.com', true],
            'sub-domain' => ['www.getberlioz.com', 443, 'getberlioz.com', true],
            'sub-domain with dot' => ['www.getberlioz.com', 443, '.getberlioz.com', true],
            'sub-domain with wildcard' => ['www.getberlioz.com', 443, '*.getberlioz.com', true],
            'domain with leading dot' => ['getberlioz.com', 443, '.getberlioz.com', true],
            'suffix is not a sub-domain' => ['notgetberlioz.com', 443, 'getberlioz.com', false],
            'list' => ['getberlioz.com', 443, 'localhost, example.com,getberlioz.com', true],
            'trailing dot of host' => ['getberlioz.com.', 443, 'getberlioz.com', true],
            'port matching' => ['getberlioz.com', 8080, 'getberlioz.com:8080', true],
            'port not matching' => ['getberlioz.com', 443, 'getberlioz.com:8080', false],
            'ipv4' => ['192.168.1.10', 80, '192.168.1.10', true],
            'ipv4 not matching' => ['192.168.1.11', 80, '192.168.1.10', false],
            'ipv4 cidr' => ['192.168.1.10', 80, '192.168.0.0/16', true],
            'ipv4 cidr not matching' => ['192.169.1.10', 80, '192.168.0.0/16', false],
            'ipv4 cidr partial byte' => ['10.0.0.130', 80, '10.0.0.128/25', true],
            'ipv4 cidr partial byte not matching' => ['10.0.0.127', 80, '10.0.0.128/25', false],
            'ipv4 cidr zero' => ['8.8.8.8', 80, '0.0.0.0/0', true],
            'ipv4 invalid cidr' => ['192.168.1.10', 80, '192.168.0.0/33', false],
            'ipv6' => ['[::1]', 80, '::1', true],
            'ipv6 brackets' => ['[::1]', 80, '[::1]', true],
            'ipv6 brackets and port' => ['[::1]', 8080, '[::1]:8080', true],
            'ipv6 cidr' => ['[2001:db8::1]', 80, '2001:db8::/32', true],
            'ipv6 cidr not matching' => ['[2001:db9::1]', 80, '2001:db8::/32', false],
            'ipv4 against ipv6' => ['127.0.0.1', 80, '::1', false],
            'host against cidr' => ['localhost', 80, '127.0.0.0/8', false],
        ];
    }

    #[DataProvider('provideNoProxy')]
    public function testIsExcluded(string $host, int $port, string $noProxy, bool $expected)
    {
        $this->assertSame($expected, Proxy::isExcluded($host, $port, $noProxy));
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
