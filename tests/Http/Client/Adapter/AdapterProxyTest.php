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
    private const HTTP_PROXY_PORT = 8181;
    private const HTTPS_PROXY_PORT = 8182;

    private static string $dir;
    private static string $certFile;
    /** @var Process[] */
    private static array $processes = [];

    public static function setUpBeforeClass(): void
    {
        self::$dir = sys_get_temp_dir() . '/berlioz-proxy-test-' . bin2hex(random_bytes(4));
        mkdir(self::$dir);
        self::$certFile = self::createCertificate(self::$dir);

        foreach ([self::HTTP_PROXY_PORT => null, self::HTTPS_PROXY_PORT => 'tls'] as $port => $flag) {
            $process = new Process(array_filter([
                'php',
                __DIR__ . '/../proxy/proxy.php',
                (string)$port,
                self::logFile($port),
                self::$certFile,
                $flag,
            ]));
            $process->start();
            self::$processes[] = $process;
            self::waitForPort($port);
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
        file_put_contents(self::logFile(self::HTTP_PROXY_PORT), '');
        file_put_contents(self::logFile(self::HTTPS_PROXY_PORT), '');
    }

    private static function logFile(int $port): string
    {
        return self::$dir . '/proxy-' . $port . '.log';
    }

    private static function waitForPort(int $port): void
    {
        for ($i = 0; $i < 50; $i++) {
            if (false !== ($fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1))) {
                fclose($fp);
                return;
            }
            usleep(100000);
        }

        throw new RuntimeException(sprintf('Fake proxy not started on port %d', $port));
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
            subjectAltName = IP:127.0.0.1, DNS:origin.invalid
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
    private static function received(int $port): array
    {
        return array_map(
            fn(string $line) => json_decode($line, true),
            array_filter(explode("\n", file_get_contents(self::logFile($port)))),
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
            new HttpContext(proxy: 'http://us%40er:p%3Ass@127.0.0.1:' . self::HTTP_PROXY_PORT),
        );

        $this->assertSame(200, $response->getStatusCode());

        $received = self::received(self::HTTP_PROXY_PORT);
        $this->assertNotEmpty($received);
        $this->assertStringContainsString(
            'Proxy-Authorization: Basic ' . base64_encode('us@er:p:ss'),
            $received[0]['head'],
        );

        match ($adapter->getName()) {
            // cURL tunnels every request through the proxy
            'curl' => $this->assertSame(['connect', 'tunneled'], array_column($received, 'type')),
            'stream' => $this->assertStringStartsWith(
                'GET http://origin.invalid/path?foo=bar HTTP/1.1',
                $received[0]['head'],
            ),
        };
        $this->assertSame($adapter->getName() == 'curl' ? 'tunneled' : 'proxied', (string)$response->getBody());
        $this->assertNoAuthorizationHeader($received);
    }

    #[DataProvider('provideAdapters')]
    public function testProxyWithoutSchemeIsHttp(AdapterInterface $adapter): void
    {
        $response = $adapter->sendRequest(
            new Request('GET', 'http://origin.invalid/'),
            new HttpContext(proxy: '127.0.0.1:' . self::HTTP_PROXY_PORT),
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty(self::received(self::HTTP_PROXY_PORT));
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
                proxy: 'http://user:pass@127.0.0.1:' . self::HTTP_PROXY_PORT,
                ssl_cafile: self::$certFile,
            ),
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('tunneled', (string)$response->getBody());

        $received = self::received(self::HTTP_PROXY_PORT);
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
                    proxy: 'http://127.0.0.1:' . self::HTTP_PROXY_PORT,
                    proxy_ssl_cafile: self::$certFile,
                ),
            );
        } finally {
            $this->assertNotContains('tunneled', array_column(self::received(self::HTTP_PROXY_PORT), 'type'));
        }
    }

    #[DataProvider('provideAdapters')]
    public function testHttpsOriginThroughHttpProxy_connectRefused(AdapterInterface $adapter): void
    {
        $this->expectException(NetworkException::class);

        $adapter->sendRequest(
            new Request('GET', 'https://denied.invalid/'),
            new HttpContext(proxy: 'http://127.0.0.1:' . self::HTTP_PROXY_PORT, ssl_cafile: self::$certFile),
        );
    }

    #[DataProvider('provideAdapters')]
    public function testHttpOriginThroughHttpsProxy(AdapterInterface $adapter): void
    {
        $response = $adapter->sendRequest(
            new Request('GET', 'http://origin.invalid/path'),
            new HttpContext(
                proxy: 'https://127.0.0.1:' . self::HTTPS_PROXY_PORT,
                proxy_ssl_cafile: self::$certFile,
            ),
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotContains('proxy-tls-failed', array_column(self::received(self::HTTPS_PROXY_PORT), 'type'));
        $this->assertNotEmpty(self::received(self::HTTPS_PROXY_PORT));
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
                    proxy: 'https://127.0.0.1:' . self::HTTPS_PROXY_PORT,
                    ssl_verify_peer: false,
                    ssl_verify_host: false,
                ),
            );
        } finally {
            $this->assertSame(
                [],
                array_diff(array_column(self::received(self::HTTPS_PROXY_PORT), 'type'), ['proxy-tls-failed']),
            );
        }
    }

    #[DataProvider('provideAdapters')]
    public function testHttpsProxy_verificationDisabled(AdapterInterface $adapter): void
    {
        $response = $adapter->sendRequest(
            new Request('GET', 'http://origin.invalid/'),
            new HttpContext(
                proxy: 'https://127.0.0.1:' . self::HTTPS_PROXY_PORT,
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
                    proxy: 'https://127.0.0.1:' . self::HTTPS_PROXY_PORT,
                    ssl_cafile: self::$certFile,
                ),
            );
        } finally {
            $this->assertSame([], self::received(self::HTTPS_PROXY_PORT));
        }
    }

    public static function provideInvalidProxies(): array
    {
        $proxies = [
            'socks5' => 'socks5://127.0.0.1:' . self::HTTP_PROXY_PORT,
            'ftp' => 'ftp://127.0.0.1:' . self::HTTP_PROXY_PORT,
            'no host' => 'http://',
            'path' => 'http://127.0.0.1:' . self::HTTP_PROXY_PORT . '/path',
            'query' => 'http://127.0.0.1:' . self::HTTP_PROXY_PORT . '?foo',
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
                new Request('GET', 'http://127.0.0.1:' . self::HTTP_PROXY_PORT . '/'),
                new HttpContext(proxy: $proxy),
            );
        } finally {
            $this->assertSame([], self::received(self::HTTP_PROXY_PORT));
        }
    }
}
