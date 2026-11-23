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

    public function testOptions_originCredentials()
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
        $context = new HttpContext(
            ssl_local_cert: '/context.pem',
            ssl_local_pk: '/context.key',
            ssl_local_passphrase: 'context-secret',
        );

        // Allowed origin
        $preparedOptions = $adapter->prepareCurlOptions($request, context: $context);
        $this->assertSame('/context.pem', $preparedOptions[CURLOPT_SSLCERT]);
        $this->assertSame('/context.key', $preparedOptions[CURLOPT_SSLKEY]);
        $this->assertSame('context-secret', $preparedOptions[CURLOPT_KEYPASSWD]);
        $this->assertSame('user:password', $preparedOptions[CURLOPT_USERPWD]);
        $this->assertSame('session=secret', $preparedOptions[CURLOPT_COOKIE]);

        // Without context
        $preparedOptions = $adapter->prepareCurlOptions($request);
        $this->assertEquals($credentials, array_intersect_key($preparedOptions, $credentials));

        // Downgraded origin
        $downgradedContexts = [$context->withoutOriginCredentials(), (new HttpContext())->withoutOriginCredentials()];
        foreach ($downgradedContexts as $downgraded) {
            $preparedOptions = $adapter->prepareCurlOptions($request, context: $downgraded);

            $this->assertSame(
                [CURLOPT_HTTPAUTH => CURLAUTH_BASIC],
                array_intersect_key($preparedOptions, $credentials),
            );
            $this->assertEquals($transport, array_intersect_key($preparedOptions, $transport));
        }
    }

    public function testOptions_downgradedWithoutConfiguredContext()
    {
        $raw = [
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPPROXYTUNNEL => true,
        ];
        $adapter = new class($raw) extends CurlAdapter {
            public function prepareCurlOptions(
                RequestInterface $request,
                array $options = [],
                ?HttpContext $context = null,
            ): array {
                return parent::prepareCurlOptions($request, $options, $context);
            }
        };
        $request = new ServerRequest(method: Request::HTTP_METHOD_GET, uri: 'https://getberlioz.com/');

        // Without configured context, raw options apply
        $this->assertEquals($raw, array_intersect_key($adapter->prepareCurlOptions($request), $raw));

        // A redirected hop without origin credentials gets a default context, failing closed:
        // its transport options override the raw ones on that hop only
        $this->assertEquals(
            [
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPPROXYTUNNEL => false,
            ],
            array_intersect_key(
                $adapter->prepareCurlOptions($request, context: (new HttpContext())->withoutOriginCredentials()),
                $raw,
            ),
        );
    }
}
