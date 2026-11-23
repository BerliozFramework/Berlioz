<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2022 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Berlioz\Http\Client\Tests;

use Berlioz\Http\Client\HttpContext;
use PHPUnit\Framework\TestCase;

class HttpContextTest extends TestCase
{
    public function testMake_HttpContext()
    {
        $options = new HttpContext();

        $this->assertSame($options, HttpContext::make($options));
        $this->assertSame($options, HttpContext::make($options, new HttpContext(proxy: 'https://getberlioz.com:8080')));
    }

    public function testMake_array()
    {
        $options = HttpContext::make(['proxy' => $expected = 'https://getberlioz.com:8080']);

        $this->assertSame($expected, $options->proxy);

        $options2 = HttpContext::make(['ssl_ciphers' => $expected2 = 'foo'], $options);

        $this->assertSame($expected, $options2->proxy);
        $this->assertSame($expected2, $options2->ssl_ciphers);
    }

    public function testProxySslDefaults()
    {
        $context = new HttpContext(ssl_verify_peer: false, ssl_verify_host: false);

        $this->assertTrue($context->proxy_ssl_verify_peer);
        $this->assertTrue($context->proxy_ssl_verify_host);
        $this->assertNull($context->proxy_ssl_cafile);
        $this->assertNull($context->proxy_ssl_capath);
    }

    public function testMake_null()
    {
        $options = HttpContext::make(['proxy' => 'https://getberlioz.com:8080']);
        $options2 = HttpContext::make(null, $options);

        $this->assertEquals($options, $options2);
        $this->assertNotSame($options, $options2);
    }

    public function testWithoutOriginCredentials()
    {
        $context = new HttpContext(
            proxy: 'http://user:pass@proxy:3128',
            ssl_verify_peer: false,
            ssl_verify_host: false,
            ssl_ciphers: 'HIGH',
            ssl_cafile: '/ca.pem',
            ssl_capath: '/ca',
            ssl_local_cert: '/client.pem',
            ssl_local_pk: '/client.key',
            ssl_local_cert_passphrase: 'cert-secret',
            ssl_local_passphrase: 'key-secret',
            credentials_origins: ['https://*.example.com'],
        );
        $without = $context->withoutOriginCredentials();

        $this->assertNotSame($context, $without);
        $this->assertNull($without->ssl_local_cert);
        $this->assertNull($without->ssl_local_pk);
        $this->assertNull($without->ssl_local_cert_passphrase);
        $this->assertNull($without->ssl_local_passphrase);
        $this->assertSame('http://user:pass@proxy:3128', $without->proxy);
        $this->assertFalse($without->ssl_verify_peer);
        $this->assertFalse($without->ssl_verify_host);
        $this->assertSame('HIGH', $without->ssl_ciphers);
        $this->assertSame('/ca.pem', $without->ssl_cafile);
        $this->assertSame('/ca', $without->ssl_capath);

        // Original is untouched
        $this->assertSame('/client.pem', $context->ssl_local_cert);
    }
}
