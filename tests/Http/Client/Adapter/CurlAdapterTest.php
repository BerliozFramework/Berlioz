<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2025 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Berlioz\Http\Client\Tests\Adapter;

use Berlioz\Http\Client\Adapter\CurlAdapter;
use Berlioz\Http\Client\Exception\RequestException;
use Berlioz\Http\Client\HttpContext;
use Berlioz\Http\Message\Request;
use Berlioz\Http\Client\Tests\ProxyEnvironmentTrait;
use Berlioz\Http\Message\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

class CurlAdapterTest extends TestCase
{
    use ProxyEnvironmentTrait;

    public function testOptions()
    {
        $adapter = new class([
            CURLOPT_TIMEOUT => 20,
            CURLOPT_URL => 'https://gethectororm.com/',
        ]) extends CurlAdapter {
            public function prepareCurlOptions(
                RequestInterface $request,
                array $options = [],
                ?HttpContext $context = null,
            ): array {
                return parent::prepareCurlOptions($request, $options, $context);
            }
        };

        $preparedOptions = $adapter->prepareCurlOptions(
            new ServerRequest(method: Request::HTTP_METHOD_GET, uri: 'https://getberlioz.com/'),
            [
                CURLOPT_CONNECTTIMEOUT => 10,
            ],
            new HttpContext(ssl_verify_host: false),
        );

        $expected = [
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYHOST => 0,
        ];

        $this->assertEquals(
            $expected,
            array_intersect_key($preparedOptions, $expected),
        );
    }

    public function testWithoutOriginCredentials()
    {
        $credentials = [
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => 'user:password',
            CURLOPT_USERNAME => 'user',
            CURLOPT_PASSWORD => 'password',
            CURLOPT_XOAUTH2_BEARER => 'token',
            CURLOPT_SSLCERT => '/client.pem',
            CURLOPT_SSLKEY => '/client.key',
            CURLOPT_KEYPASSWD => 'key-secret',
            CURLOPT_NETRC => 1,
            CURLOPT_COOKIE => 'session=secret',
            CURLOPT_COOKIEFILE => '/cookies.txt',
            CURLOPT_COOKIELIST => 'Set-Cookie: session=secret',
        ];
        $transport = [
            CURLOPT_TIMEOUT => 20,
            CURLOPT_PROXY => 'proxy:3128',
            CURLOPT_PROXYUSERPWD => 'proxy-user:proxy-password',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ];
        $adapter = new class($credentials + $transport) extends CurlAdapter {
            public function prepareCurlOptions(
                RequestInterface $request,
                array $options = [],
                ?HttpContext $context = null,
            ): array {
                return parent::prepareCurlOptions($request, $options, $context);
            }
        };
        $request = new ServerRequest(method: Request::HTTP_METHOD_GET, uri: 'https://getberlioz.com/');
        $without = $adapter->withoutOriginCredentials();

        $this->assertNotSame($adapter, $without);
        $this->assertInstanceOf($adapter::class, $without);

        // Origin credentials are removed, transport options (including proxy credentials) are kept
        $preparedOptions = $without->prepareCurlOptions($request);
        $this->assertSame([CURLOPT_HTTPAUTH => CURLAUTH_BASIC], array_intersect_key($preparedOptions, $credentials));
        $this->assertEquals($transport, array_intersect_key($preparedOptions, $transport));

        // Original adapter is untouched
        $preparedOptions = $adapter->prepareCurlOptions($request);
        $this->assertEquals($credentials, array_intersect_key($preparedOptions, $credentials));
    }

    public function testWithoutOriginCredentials_keepsContextProxy()
    {
        $adapter = $this->createAdapter([CURLOPT_USERPWD => 'user:password']);
        $context = new HttpContext(
            proxy: 'https://proxy-user:proxy-password@proxy.local:8443',
            ssl_local_cert: '/client.pem',
            proxy_ssl_cafile: '/proxy/ca.pem',
        );

        $options = $adapter->withoutOriginCredentials()->prepareCurlOptions(
            new Request(method: Request::HTTP_METHOD_GET, uri: 'https://getberlioz.com/'),
            context: $context->withoutOriginCredentials(),
        );

        $this->assertArrayNotHasKey(CURLOPT_USERPWD, $options);
        $this->assertArrayNotHasKey(CURLOPT_SSLCERT, $options);
        $this->assertSame('https://proxy.local:8443', $options[CURLOPT_PROXY]);
        $this->assertSame(CURLPROXY_HTTPS, $options[CURLOPT_PROXYTYPE]);
        $this->assertTrue($options[CURLOPT_HTTPPROXYTUNNEL]);
        $this->assertSame('proxy-user', $options[CURLOPT_PROXYUSERNAME]);
        $this->assertSame('proxy-password', $options[CURLOPT_PROXYPASSWORD]);
        $this->assertSame('/proxy/ca.pem', $options[CURLOPT_PROXY_CAINFO]);
        $this->assertTrue($options[CURLOPT_PROXY_SSL_VERIFYPEER]);
    }

    private function createAdapter(array $options = [], bool $httpsProxySupported = true): CurlAdapter
    {
        return new class($options, $httpsProxySupported) extends CurlAdapter {
            public function __construct(array $options, private readonly bool $httpsProxySupported)
            {
                parent::__construct($options);
            }

            public function prepareCurlOptions(
                RequestInterface $request,
                array $options = [],
                ?HttpContext $context = null,
            ): array {
                return parent::prepareCurlOptions($request, $options, $context);
            }

            protected function supportsHttpsProxy(): bool
            {
                return $this->httpsProxySupported;
            }
        };
    }

    public function testOptions_httpProxy()
    {
        $options = $this->createAdapter()->prepareCurlOptions(
            new Request(method: Request::HTTP_METHOD_GET, uri: 'https://getberlioz.com/'),
            context: new HttpContext(proxy: 'http://us%40er:p%3Ass@proxy.local'),
        );

        $this->assertSame('http://proxy.local:80', $options[CURLOPT_PROXY]);
        $this->assertSame(CURLPROXY_HTTP, $options[CURLOPT_PROXYTYPE]);
        $this->assertTrue($options[CURLOPT_HTTPPROXYTUNNEL]);
        $this->assertSame('us@er', $options[CURLOPT_PROXYUSERNAME]);
        $this->assertSame('p:ss', $options[CURLOPT_PROXYPASSWORD]);
        $this->assertArrayNotHasKey(CURLOPT_PROXYUSERPWD, $options);
    }

    public function testOptions_httpsProxy()
    {
        $options = $this->createAdapter()->prepareCurlOptions(
            new Request(method: Request::HTTP_METHOD_GET, uri: 'https://getberlioz.com/'),
            context: new HttpContext(
                proxy: 'HTTPS://proxy.local',
                ssl_verify_peer: false,
                ssl_verify_host: false,
                ssl_cafile: '/origin/ca.pem',
                proxy_ssl_cafile: '/proxy/ca.pem',
            ),
        );

        $this->assertSame('https://proxy.local:443', $options[CURLOPT_PROXY]);
        $this->assertSame(CURLPROXY_HTTPS, $options[CURLOPT_PROXYTYPE]);
        $this->assertArrayNotHasKey(CURLOPT_PROXYUSERNAME, $options);
        // Origin and proxy TLS are configured independently
        $this->assertFalse($options[CURLOPT_SSL_VERIFYPEER]);
        $this->assertSame(0, $options[CURLOPT_SSL_VERIFYHOST]);
        $this->assertSame('/origin/ca.pem', $options[CURLOPT_CAINFO]);
        $this->assertTrue($options[CURLOPT_PROXY_SSL_VERIFYPEER]);
        $this->assertSame(2, $options[CURLOPT_PROXY_SSL_VERIFYHOST]);
        $this->assertSame('/proxy/ca.pem', $options[CURLOPT_PROXY_CAINFO]);
    }

    public function testOptions_proxyCaFallback()
    {
        $options = $this->createAdapter()->prepareCurlOptions(
            new Request(method: Request::HTTP_METHOD_GET, uri: 'https://getberlioz.com/'),
            context: new HttpContext(
                proxy: 'https://proxy.local:8443',
                ssl_cafile: '/origin/ca.pem',
                ssl_capath: '/origin/certs',
                proxy_ssl_verify_peer: false,
                proxy_ssl_verify_host: false,
            ),
        );

        $this->assertSame('https://proxy.local:8443', $options[CURLOPT_PROXY]);
        $this->assertSame('/origin/ca.pem', $options[CURLOPT_PROXY_CAINFO]);
        $this->assertSame('/origin/certs', $options[CURLOPT_PROXY_CAPATH]);
        $this->assertTrue($options[CURLOPT_SSL_VERIFYPEER]);
        $this->assertFalse($options[CURLOPT_PROXY_SSL_VERIFYPEER]);
        $this->assertSame(0, $options[CURLOPT_PROXY_SSL_VERIFYHOST]);
    }

    public function testOptions_withoutProxy()
    {
        $options = $this->createAdapter()->prepareCurlOptions(
            new Request(method: Request::HTTP_METHOD_GET, uri: 'https://getberlioz.com/'),
            context: new HttpContext(),
        );

        $this->assertFalse($options[CURLOPT_HTTPPROXYTUNNEL]);
        // Explicitly empty: libcurl must not read the environment by itself
        $this->assertSame('', $options[CURLOPT_PROXY]);
        $this->assertArrayNotHasKey(CURLOPT_PROXYTYPE, $options);
    }

    public static function provideContexts(): array
    {
        return [
            'default context' => [new HttpContext()],
            'no context' => [null],
        ];
    }

    #[DataProvider('provideContexts')]
    public function testOptions_environmentProxy(?HttpContext $context)
    {
        $this->setProxyEnv(['https_proxy' => 'http://user:pass@env.local:3128', 'no_proxy' => 'example.com']);

        $options = $this->createAdapter()->prepareCurlOptions(
            new Request(method: Request::HTTP_METHOD_GET, uri: 'https://getberlioz.com/'),
            context: $context,
        );

        $this->assertSame('http://env.local:3128', $options[CURLOPT_PROXY]);
        $this->assertSame(CURLPROXY_HTTP, $options[CURLOPT_PROXYTYPE]);
        $this->assertTrue($options[CURLOPT_HTTPPROXYTUNNEL]);
        $this->assertSame('user', $options[CURLOPT_PROXYUSERNAME]);
        $this->assertSame('pass', $options[CURLOPT_PROXYPASSWORD]);
        // "no_proxy" applied by the adapter, not by libcurl
        $this->assertSame('', $options[CURLOPT_NOPROXY]);
    }

    #[DataProvider('provideContexts')]
    public function testOptions_environmentProxyExcluded(?HttpContext $context)
    {
        $this->setProxyEnv(['https_proxy' => 'http://env.local:3128', 'no_proxy' => '.getberlioz.com']);

        $options = $this->createAdapter()->prepareCurlOptions(
            new Request(method: Request::HTTP_METHOD_GET, uri: 'https://www.getberlioz.com/'),
            context: $context,
        );

        $this->assertSame('', $options[CURLOPT_PROXY]);
        $this->assertArrayNotHasKey(CURLOPT_PROXYTYPE, $options);
    }

    public function testOptions_proxyDisabled()
    {
        $this->setProxyEnv(['https_proxy' => 'http://env.local:3128', 'all_proxy' => 'http://env.local:3128']);

        $options = $this->createAdapter()->prepareCurlOptions(
            new Request(method: Request::HTTP_METHOD_GET, uri: 'https://getberlioz.com/'),
            context: new HttpContext(proxy: false),
        );

        $this->assertSame('', $options[CURLOPT_PROXY]);
        $this->assertFalse($options[CURLOPT_HTTPPROXYTUNNEL]);
        $this->assertArrayNotHasKey(CURLOPT_PROXYTYPE, $options);
    }

    public function testOptions_configuredProxyIgnoresNoProxy()
    {
        $this->setProxyEnv(['https_proxy' => 'http://env.local:3128', 'no_proxy' => 'getberlioz.com']);

        $options = $this->createAdapter()->prepareCurlOptions(
            new Request(method: Request::HTTP_METHOD_GET, uri: 'https://getberlioz.com/'),
            context: new HttpContext(proxy: 'http://configured.local:8080'),
        );

        $this->assertSame('http://configured.local:8080', $options[CURLOPT_PROXY]);
        $this->assertSame('', $options[CURLOPT_NOPROXY]);
    }

    public function testOptions_rawProxyOptionBeforeEnvironment()
    {
        $this->setProxyEnv(['https_proxy' => 'http://env.local:3128']);

        $options = $this->createAdapter()->prepareCurlOptions(
            new Request(method: Request::HTTP_METHOD_GET, uri: 'https://getberlioz.com/'),
            [CURLOPT_PROXY => 'http://raw.local:3128'],
            new HttpContext(),
        );

        $this->assertSame('http://raw.local:3128', $options[CURLOPT_PROXY]);

        $options = $this->createAdapter()->prepareCurlOptions(
            new Request(method: Request::HTTP_METHOD_GET, uri: 'https://getberlioz.com/'),
            [CURLOPT_PROXY => 'http://raw.local:3128'],
            new HttpContext(proxy: 'http://configured.local:8080'),
        );

        $this->assertSame('http://configured.local:8080', $options[CURLOPT_PROXY]);
    }

    public function testOptions_httpsProxyUnsupported()
    {
        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('HTTPS proxy is not supported');

        $this->createAdapter(httpsProxySupported: false)->prepareCurlOptions(
            new Request(method: Request::HTTP_METHOD_GET, uri: 'https://getberlioz.com/'),
            context: new HttpContext(proxy: 'https://proxy.local'),
        );
    }
}
