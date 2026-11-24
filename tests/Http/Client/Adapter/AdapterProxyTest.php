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

namespace Berlioz\Http\Client\Tests\Adapter;

use Berlioz\Http\Client\Adapter\AdapterInterface;
use Berlioz\Http\Client\Adapter\CurlAdapter;
use Berlioz\Http\Client\Adapter\StreamAdapter;
use Berlioz\Http\Client\Exception\NetworkException;
use Berlioz\Http\Client\Exception\RequestException;
use Berlioz\Http\Client\HttpContext;
use Berlioz\Http\Client\Tests\ProxyEnvironmentTrait;
use Berlioz\Http\Message\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Proxy behavior of transport adapters, against a local fake proxy.
 *
 * Origins use the reserved ".invalid" TLD: a direct connection cannot succeed, so a response proves that the request
 * went through the proxy.
 */
class AdapterProxyTest extends TestCase
{
    use ProxyEnvironmentTrait;

    private static string $dir;
    private static string $certFile;
    /** @var array<string, int> Fake proxies ports, by name ("http" or "https") */
    private static array $ports = [];
    /** @var Process[] */
    private static array $processes = [];

    public static function setUpBeforeClass(): void
    {
        self::$dir = sys_get_temp_dir() . '/berlioz-proxy-test-' . bin2hex(random_bytes(4));
        mkdir(self::$dir);
        self::$certFile = self::createCertificate(self::$dir);

        foreach (['http' => null, 'https' => 'tls'] as $name => $flag) {
            $process = new Process(array_filter([
                PHP_BINARY,
                __DIR__ . '/../proxy/proxy.php',
                self::logFile($name),
                self::$certFile,
                $flag,
            ]));
            $process->start();
            self::$processes[] = $process;

            $process->waitUntil(fn($type, $output) => Process::OUT === $type && str_contains($output, PHP_EOL));
            if (0 === (self::$ports[$name] = (int)$process->getOutput())) {
                throw new RuntimeException('Unable to start fake proxy: ' . $process->getErrorOutput());
            }
        }
    }

    public static function tearDownAfterClass(): void
    {
        foreach (self::$processes as $process) {
            $process->stop();
        }
        self::$processes = [];
        array_map(unlink(...), glob(self::$dir . '/*'));
        rmdir(self::$dir);
    }

    protected function setUp(): void
    {
        file_put_contents(self::logFile('http'), '');
        file_put_contents(self::logFile('https'), '');
    }

    private static function logFile(string $name): string
    {
        return self::$dir . '/proxy-' . $name . '.log';
    }

    /**
     * Create a self-signed certificate for the proxy (127.0.0.1) and the origins (*.invalid).
     */
    private static function createCertificate(string $dir): string
    {
        $config = $dir . '/openssl.cnf';
        file_put_contents(
            $config,
            <<<CNF
            [req]
            distinguished_name = dn
            [dn]
            [v3]
            subjectAltName = IP:127.0.0.1, DNS:origin.invalid, DNS:forged.invalid
            basicConstraints = critical, CA:TRUE
            keyUsage = critical, digitalSignature, keyEncipherment, keyCertSign
            CNF
        );
        $options = ['config' => $config, 'x509_extensions' => 'v3', 'digest_alg' => 'sha256'];

        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $csr = openssl_csr_new(['commonName' => 'Berlioz test'], $key, $options);
        $cert = openssl_csr_sign($csr, null, $key, 1, $options, random_int(1, PHP_INT_MAX));

        openssl_x509_export($cert, $certPem);
        openssl_pkey_export($key, $keyPem, null, ['config' => $config]);
        file_put_contents($file = $dir . '/cert.pem', $certPem . $keyPem);

        return $file;
    }

    /**
     * Get received request heads.
     *
     * @return array<array{type: string, head: string}>
     */
    private static function received(string $name): array
    {
        return array_map(
            fn(string $line) => json_decode($line, true),
            array_filter(explode("\n", file_get_contents(self::logFile($name)))),
        );
    }

    public static function provideAdapters(): array
    {
        return [
            'curl' => [new CurlAdapter([CURLOPT_TIMEOUT => 5])],
            'stream' => [new StreamAdapter(timeout: 5)],
        ];
    }

    #[DataProvider('provideAdapters')]
    public function testHttpOriginThroughHttpProxy(AdapterInterface $adapter): void
    {
        $response = $adapter->sendRequest(
            new Request('GET', 'http://origin.invalid/path?foo=bar'),
            new HttpContext(proxy: 'http://us%40er:p%3Ass@127.0.0.1:' . self::$ports['http']),
        );

        $this->assertSame(200, $response->getStatusCode());

        $received = self::received('http');
        $this->assertNotEmpty($received);
        $this->assertStringContainsString(
            'Proxy-Authorization: Basic ' . base64_encode('us@er:p:ss'),
            $received[0]['head'],
        );

        // Absolute-form, without tunnel
        $this->assertSame(['request'], array_column($received, 'type'));
        $this->assertStringStartsWith('GET http://origin.invalid/path?foo=bar HTTP/1.1', $received[0]['head']);
        $this->assertSame('proxied', (string)$response->getBody());
        $this->assertNoAuthorizationHeader($received);
    }

    public static function provideAdaptersAndContexts(): array
    {
        $data = [];

        foreach (self::provideAdapters() as $adapterName => [$adapter]) {
            $data[$adapterName . ' / default context'] = [$adapter, new HttpContext()];
            $data[$adapterName . ' / no context'] = [$adapter, null];
        }

        return $data;
    }

    #[DataProvider('provideAdaptersAndContexts')]
    public function testHttpOriginThroughEnvironmentProxy(AdapterInterface $adapter, ?HttpContext $context): void
    {
        $this->setProxyEnv(['http_proxy' => 'http://user:pass@127.0.0.1:' . self::$ports['http']]);

        $response = $adapter->sendRequest(new Request('GET', 'http://origin.invalid/path'), $context);

        $this->assertSame(200, $response->getStatusCode());

        $received = self::received('http');
        $this->assertSame(['request'], array_column($received, 'type'));
        $this->assertStringStartsWith('GET http://origin.invalid/path HTTP/1.1', $received[0]['head']);
        $this->assertStringContainsString(
            'Proxy-Authorization: Basic ' . base64_encode('user:pass'),
            $received[0]['head'],
        );
        $this->assertNoAuthorizationHeader($received);
    }

    public function testCurl_disabledProxyBeforeRawProxyOption(): void
    {
        $adapter = new CurlAdapter([CURLOPT_TIMEOUT => 5, CURLOPT_PROXY => 'http://127.0.0.1:' . self::$ports['http']]);

        $this->expectException(NetworkException::class);

        try {
            // Direct connection to the ".invalid" origin cannot succeed
            $adapter->sendRequest(new Request('GET', 'http://origin.invalid/'), new HttpContext(proxy: false));
        } finally {
            $this->assertSame([], self::received('http'));
        }
    }

    #[DataProvider('provideAdapters')]
    public function testHttpsOriginThroughEnvironmentProxy(AdapterInterface $adapter): void
    {
        $this->setProxyEnv(['https_proxy' => 'http://127.0.0.1:' . self::$ports['http']]);

        $response = $adapter->sendRequest(
            new Request('GET', 'https://origin.invalid/path'),
            new HttpContext(ssl_cafile: self::$certFile),
        );

        $this->assertSame('tunneled', (string)$response->getBody());
        $this->assertSame(['connect', 'tunneled'], array_column(self::received('http'), 'type'));
    }

    public static function provideBypassedEnvironments(): array
    {
        $data = [];

        foreach (self::provideAdapters() as $adapterName => [$adapter]) {
            $data[$adapterName . ' / disabled'] = [
                $adapter,
                new HttpContext(proxy: false),
                ['http_proxy' => '{proxy}', 'all_proxy' => '{proxy}'],
            ];
            $data[$adapterName . ' / no_proxy'] = [
                $adapter,
                new HttpContext(),
                ['http_proxy' => '{proxy}', 'no_proxy' => '.invalid'],
            ];
            $data[$adapterName . ' / NO_PROXY'] = [
                $adapter,
                null,
                ['all_proxy' => '{proxy}', 'NO_PROXY' => 'origin.invalid'],
            ];
            $data[$adapterName . ' / HTTP_PROXY'] = [$adapter, new HttpContext(), ['HTTP_PROXY' => '{proxy}']];
        }

        return $data;
    }

    #[DataProvider('provideBypassedEnvironments')]
    public function testEnvironmentProxyBypassed(AdapterInterface $adapter, ?HttpContext $context, array $env): void
    {
        $proxy = 'http://127.0.0.1:' . self::$ports['http'];
        $this->setProxyEnv(array_map(fn($value) => str_replace('{proxy}', $proxy, $value), $env));

        $this->expectException(NetworkException::class);

        try {
            // Direct connection to the ".invalid" origin cannot succeed
            $adapter->sendRequest(new Request('GET', 'http://origin.invalid/'), $context);
        } finally {
            $this->assertSame([], self::received('http'));
        }
    }

    #[DataProvider('provideAdapters')]
    public function testProxyWithoutSchemeIsHttp(AdapterInterface $adapter): void
    {
        $response = $adapter->sendRequest(
            new Request('GET', 'http://origin.invalid/'),
            new HttpContext(proxy: '127.0.0.1:' . self::$ports['http']),
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty(self::received('http'));
    }

    /**
     * Proxy credentials must never be sent in the "Authorization" header.
     */
    private function assertNoAuthorizationHeader(array $received): void
    {
        foreach ($received as $entry) {
            $this->assertDoesNotMatchRegularExpression('/^Authorization:/mi', $entry['head']);
        }
    }

    #[DataProvider('provideAdapters')]
    public function testHttpsOriginThroughHttpProxy(AdapterInterface $adapter): void
    {
        $response = $adapter->sendRequest(
            new Request('GET', 'https://origin.invalid/path'),
            new HttpContext(
                proxy: 'http://user:pass@127.0.0.1:' . self::$ports['http'],
                ssl_cafile: self::$certFile,
            ),
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('tunneled', (string)$response->getBody());

        $received = self::received('http');
        $this->assertSame(['connect', 'tunneled'], array_column($received, 'type'));
        $this->assertStringStartsWith('CONNECT origin.invalid:443 HTTP/1.1', $received[0]['head']);
        $this->assertStringContainsString(
            'Proxy-Authorization: Basic ' . base64_encode('user:pass'),
            $received[0]['head'],
        );
        $this->assertStringStartsWith('GET /path HTTP/1.1', $received[1]['head']);
        $this->assertStringNotContainsString('Proxy-Authorization', $received[1]['head']);
        $this->assertNoAuthorizationHeader($received);
    }

    #[DataProvider('provideAdapters')]
    public function testHttpsOriginThroughHttpProxy_originCertificateVerified(AdapterInterface $adapter): void
    {
        $this->expectException(NetworkException::class);

        try {
            $adapter->sendRequest(
                new Request('GET', 'https://origin.invalid/'),
                new HttpContext(
                    proxy: 'http://127.0.0.1:' . self::$ports['http'],
                    proxy_ssl_cafile: self::$certFile,
                ),
            );
        } finally {
            $this->assertNotContains('tunneled', array_column(self::received('http'), 'type'));
        }
    }

    #[DataProvider('provideAdapters')]
    public function testHttpsOriginThroughHttpProxy_connectRefused(AdapterInterface $adapter): void
    {
        $this->expectException(NetworkException::class);

        $adapter->sendRequest(
            new Request('GET', 'https://denied.invalid/'),
            new HttpContext(proxy: 'http://127.0.0.1:' . self::$ports['http'], ssl_cafile: self::$certFile),
        );
    }

    #[DataProvider('provideAdapters')]
    public function testHttpsOriginThroughHttpProxy_plaintextAfterConnectRejected(AdapterInterface $adapter): void
    {
        $this->expectException(NetworkException::class);

        try {
            // The proxy sends a forged response with the CONNECT response, then a trusted TLS handshake
            $adapter->sendRequest(
                new Request('GET', 'https://forged.invalid/'),
                new HttpContext(proxy: 'http://127.0.0.1:' . self::$ports['http'], ssl_cafile: self::$certFile),
            );
        } finally {
            $this->assertNotContains('tunneled', array_column(self::received('http'), 'type'));
        }
    }

    #[DataProvider('provideAdapters')]
    public function testHttpOriginThroughHttpsProxy(AdapterInterface $adapter): void
    {
        $response = $adapter->sendRequest(
            new Request('GET', 'http://origin.invalid/path'),
            new HttpContext(
                proxy: 'https://127.0.0.1:' . self::$ports['https'],
                proxy_ssl_cafile: self::$certFile,
            ),
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotContains('proxy-tls-failed', array_column(self::received('https'), 'type'));
        $this->assertNotEmpty(self::received('https'));
    }

    #[DataProvider('provideAdapters')]
    public function testHttpsProxy_certificateVerifiedIndependently(AdapterInterface $adapter): void
    {
        $this->expectException(NetworkException::class);

        try {
            // Disabling origin verification must not disable proxy verification
            $adapter->sendRequest(
                new Request('GET', 'http://origin.invalid/'),
                new HttpContext(
                    proxy: 'https://127.0.0.1:' . self::$ports['https'],
                    ssl_verify_peer: false,
                    ssl_verify_host: false,
                ),
            );
        } finally {
            $this->assertSame(
                [],
                array_diff(array_column(self::received('https'), 'type'), ['proxy-tls-failed']),
            );
        }
    }

    #[DataProvider('provideAdapters')]
    public function testHttpsProxy_verificationDisabled(AdapterInterface $adapter): void
    {
        $response = $adapter->sendRequest(
            new Request('GET', 'http://origin.invalid/'),
            new HttpContext(
                proxy: 'https://127.0.0.1:' . self::$ports['https'],
                proxy_ssl_verify_peer: false,
                proxy_ssl_verify_host: false,
            ),
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testStream_httpsOriginThroughHttpsProxyRejected(): void
    {
        $this->expectException(RequestException::class);

        try {
            (new StreamAdapter(timeout: 5))->sendRequest(
                new Request('GET', 'https://origin.invalid/'),
                new HttpContext(
                    proxy: 'https://127.0.0.1:' . self::$ports['https'],
                    ssl_cafile: self::$certFile,
                ),
            );
        } finally {
            $this->assertSame([], self::received('https'));
        }
    }

    public static function provideInvalidProxies(): array
    {
        $proxies = [
            'socks5' => 'socks5://127.0.0.1:{port}',
            'ftp' => 'ftp://127.0.0.1:{port}',
            'no host' => 'http://',
            'path' => 'http://127.0.0.1:{port}/path',
            'query' => 'http://127.0.0.1:{port}?foo',
            'empty' => '',
        ];
        $data = [];

        foreach (self::provideAdapters() as $adapterName => [$adapter]) {
            foreach ($proxies as $proxyName => $proxy) {
                $data[$adapterName . ' / ' . $proxyName] = [$adapter, $proxy];
            }
        }

        return $data;
    }

    #[DataProvider('provideInvalidProxies')]
    public function testInvalidProxyRejectedBeforeIo(AdapterInterface $adapter, string $proxy): void
    {
        $this->expectException(RequestException::class);

        try {
            // Reachable origin: a direct connection would succeed if the proxy was ignored
            $adapter->sendRequest(
                new Request('GET', 'http://127.0.0.1:' . self::$ports['http'] . '/'),
                new HttpContext(proxy: str_replace('{port}', (string)self::$ports['http'], $proxy)),
            );
        } finally {
            $this->assertSame([], self::received('http'));
        }
    }
}
