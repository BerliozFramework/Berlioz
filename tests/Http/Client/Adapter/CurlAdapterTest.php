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
use Berlioz\Http\Message\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

class CurlAdapterTest extends TestCase
{
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

    private function createAdapter(bool $httpsProxySupported = true): CurlAdapter
    {
        return new class($httpsProxySupported) extends CurlAdapter {
            public function __construct(private readonly bool $httpsProxySupported)
            {
                parent::__construct();
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
        $this->assertArrayNotHasKey(CURLOPT_PROXY, $options);
        $this->assertArrayNotHasKey(CURLOPT_PROXYTYPE, $options);
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
