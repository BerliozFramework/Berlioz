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

namespace Berlioz\Mailer\Tests\Transport;

use Berlioz\Mailer\Address;
use Berlioz\Mailer\Exception\InvalidArgumentException;
use Berlioz\Mailer\Exception\TransportException;
use Berlioz\Mailer\Mail;
use Berlioz\Mailer\Mailer;
use Berlioz\Mailer\Transport\Smtp;
use Berlioz\Mailer\Transport\SmtpEncryption;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Stringable;

#[RequiresPhpExtension('openssl')]
class SmtpTest extends TestCase
{
    private static string $directory;
    private static string $certificate;
    /** @var resource|null */
    private $process = null;
    private ?string $logFile = null;
    private ?string $captureFile = null;

    public static function setUpBeforeClass(): void
    {
        self::$directory = sys_get_temp_dir() . '/berlioz-mailer-smtp-' . bin2hex(random_bytes(4));
        mkdir(self::$directory);

        $config = self::$directory . '/openssl.cnf';
        file_put_contents(
            $config,
            <<<'CNF'
            [req]
            distinguished_name = dn
            prompt = no
            [dn]
            CN = localhost
            [v3]
            subjectAltName = DNS:localhost,IP:127.0.0.1
            basicConstraints = critical,CA:TRUE
            keyUsage = digitalSignature,keyCertSign
            extendedKeyUsage = serverAuth
            CNF
        );
        $options = ['config' => $config, 'digest_alg' => 'sha256', 'x509_extensions' => 'v3'];

        $key = openssl_pkey_new([
            'config' => $config,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);
        $csr = openssl_csr_new(['commonName' => 'localhost'], $key, $options);
        $cert = openssl_csr_sign($csr, null, $key, 1, $options);

        openssl_x509_export($cert, $certPem);
        openssl_pkey_export($key, $keyPem, null, ['config' => $config]);

        self::$certificate = self::$directory . '/server.pem';
        file_put_contents(self::$certificate, $certPem . $keyPem);
        file_put_contents(self::$directory . '/ca.pem', $certPem);
    }

    public static function tearDownAfterClass(): void
    {
        array_map(unlink(...), glob(self::$directory . '/*'));
        rmdir(self::$directory);
    }

    protected function tearDown(): void
    {
        if (null !== $this->process) {
            proc_terminate($this->process);
            proc_close($this->process);
            $this->process = null;
        }
    }

    /**
     * Start fake SMTP server.
     *
     * @param string $scenario
     * @param bool $capture Capture envelope recipients and message data
     *
     * @return int Port
     */
    private function startServer(string $scenario, bool $capture = false): int
    {
        $this->logFile = self::$directory . '/' . $scenario . '-' . bin2hex(random_bytes(4)) . '.log';
        $this->captureFile = $capture ? $this->logFile . '.capture' : null;
        $this->process = proc_open(
            array_filter(
                [
                    PHP_BINARY,
                    __DIR__ . '/Fake/smtp-server.php',
                    $scenario,
                    self::$certificate,
                    $this->logFile,
                    $this->captureFile,
                ],
            ),
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        $port = (int)fgets($pipes[1]);

        if (0 === $port) {
            $this->fail('Unable to start fake SMTP server: ' . stream_get_contents($pipes[2]));
        }

        return $port;
    }

    /**
     * Wait end of fake SMTP server and get received commands.
     *
     * @return string[]
     */
    private function stopServer(): array
    {
        proc_close($this->process);
        $this->process = null;

        return file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }

    /**
     * Get captured envelope recipients and message data, after server stop.
     *
     * @return array{rcpt: string[], data: string[]}
     */
    private function getCapture(): array
    {
        $capture = ['rcpt' => [], 'data' => []];

        foreach (file($this->captureFile, FILE_IGNORE_NEW_LINES) as $line) {
            [$type, $value] = explode(':', $line, 2);
            $capture[$type][] = $value;
        }

        return $capture;
    }

    /**
     * Get headers of captured message data.
     *
     * @param string[] $data
     *
     * @return string[]
     */
    private function getCapturedHeaders(array $data): array
    {
        $end = array_search('', $data, true);

        return false === $end ? $data : array_slice($data, 0, $end);
    }

    private function createMail(): Mail
    {
        return (new Mail())
            ->setFrom(new Address('sender@getberlioz.com'))
            ->setTo([new Address('recipient@getberlioz.com')])
            ->setSubject('Test')
            ->setText('Test');
    }

    private function createLogger(): AbstractLogger
    {
        return new class extends AbstractLogger {
            public array $logs = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->logs[] = [$level, (string)$message];
            }
        };
    }

    public function testSend_plain(): void
    {
        $port = $this->startServer('plain');

        $smtp = new Smtp('127.0.0.1', 'user', 'pass', $port, timeout: 5);
        $smtp->send($this->createMail());
        unset($smtp);

        $this->assertSame(
            ['plain:EHLO', 'plain:AUTH', 'plain:MAIL', 'plain:RCPT', 'plain:DATA', 'plain:QUIT'],
            $this->stopServer(),
        );
    }

    public function testSend_withBcc(): void
    {
        $port = $this->startServer('plain', capture: true);

        $mail = $this->createMail()
            ->setCc([new Address('copy@getberlioz.com')])
            ->setBcc([new Address('blind1@getberlioz.com', 'Blind'), new Address('blind2@getberlioz.com')]);

        $smtp = new Smtp('127.0.0.1', port: $port, timeout: 5);
        $smtp->send($mail);
        unset($smtp);
        $this->stopServer();
        $capture = $this->getCapture();
        $headers = $this->getCapturedHeaders($capture['data']);

        $this->assertSame(
            [
                '<recipient@getberlioz.com>',
                '<copy@getberlioz.com>',
                '<blind1@getberlioz.com>',
                '<blind2@getberlioz.com>',
            ],
            $capture['rcpt'],
        );
        $this->assertContains('To: recipient@getberlioz.com', $headers);
        $this->assertContains('Cc: copy@getberlioz.com', $headers);
        $this->assertEmpty(preg_grep('/^bcc:/i', $headers));
        $this->assertStringNotContainsString('blind', implode("\n", $capture['data']));
    }

    public function testSend_bccOnly(): void
    {
        $port = $this->startServer('plain', capture: true);

        $mail = $this->createMail()
            ->setTo([])
            ->setBcc([new Address('blind@getberlioz.com')]);

        $smtp = new Smtp('127.0.0.1', port: $port, timeout: 5);
        $smtp->send($mail);
        unset($smtp);
        $this->stopServer();
        $capture = $this->getCapture();
        $headers = $this->getCapturedHeaders($capture['data']);

        $this->assertSame(['<blind@getberlioz.com>'], $capture['rcpt']);
        $this->assertContains('To: undisclosed-recipients:;', $headers);
        $this->assertEmpty(preg_grep('/^bcc:/i', $headers));
        $this->assertStringNotContainsString('blind', implode("\n", $capture['data']));
    }

    public function testSend_starttls(): void
    {
        $port = $this->startServer('starttls');

        $smtp = new Smtp(
            '127.0.0.1',
            'user',
            'pass',
            $port,
            encryption: SmtpEncryption::STARTTLS,
            context: ['ssl' => ['cafile' => self::$directory . '/ca.pem']],
            timeout: 5,
        );
        $smtp->send($this->createMail());
        unset($smtp);

        $this->assertSame(
            [
                'plain:EHLO',
                'plain:STARTTLS',
                'tls:EHLO',
                'tls:AUTH',
                'tls:MAIL',
                'tls:RCPT',
                'tls:DATA',
                'tls:QUIT',
            ],
            $this->stopServer(),
        );
    }

    public function testSend_starttlsWithStreamContextAndPeerName(): void
    {
        $port = $this->startServer('starttls');
        $context = stream_context_create(['ssl' => ['cafile' => self::$directory . '/ca.pem']]);

        $smtp = new Smtp(
            'localhost',
            port: $port,
            encryption: 'starttls',
            context: $context,
            timeout: 5,
        );
        $smtp->send($this->createMail());
        unset($smtp);

        $this->assertSame(
            ['plain:EHLO', 'plain:STARTTLS', 'tls:EHLO', 'tls:MAIL'],
            array_slice($this->stopServer(), 0, 4),
        );
        $this->assertArrayNotHasKey('peer_name', stream_context_get_options($context)['ssl']);
    }

    public function testSend_starttlsNotSupported(): void
    {
        $port = $this->startServer('plain');

        $smtp = new Smtp('127.0.0.1', 'user', 'pass', $port, encryption: 'starttls', timeout: 5);

        try {
            $smtp->send($this->createMail());
            $this->fail('Exception expected');
        } catch (TransportException $exception) {
            $this->assertSame('STARTTLS required but not supported by server', $exception->getMessage());
        }

        unset($smtp);
        $this->assertSame(['plain:EHLO'], $this->stopServer());
    }

    public function testSend_starttlsRefused(): void
    {
        $port = $this->startServer('starttls-refused');

        $smtp = new Smtp('127.0.0.1', 'user', 'pass', $port, encryption: 'starttls', timeout: 5);

        try {
            $smtp->send($this->createMail());
            $this->fail('Exception expected');
        } catch (TransportException $exception) {
            $this->assertSame('"STARTTLS" command failed: 454 TLS not available', $exception->getMessage());
        }

        unset($smtp);
        $this->assertSame(['plain:EHLO', 'plain:STARTTLS'], $this->stopServer());
    }

    public function testSend_starttlsInjectedReplies(): void
    {
        $port = $this->startServer('starttls-injected');

        $smtp = new Smtp(
            '127.0.0.1',
            port: $port,
            encryption: SmtpEncryption::STARTTLS,
            context: ['ssl' => ['cafile' => self::$directory . '/ca.pem']],
            timeout: 5,
        );

        try {
            $smtp->send($this->createMail());
            $this->fail('Exception expected');
        } catch (TransportException $exception) {
            $this->assertSame('Unexpected data received after "STARTTLS" reply', $exception->getMessage());
        }

        unset($smtp);
        $this->assertSame(['plain:EHLO', 'plain:STARTTLS', 'tls:FAILED'], $this->stopServer());
    }

    public function testSend_starttlsUntrustedCertificate(): void
    {
        $port = $this->startServer('starttls');

        $smtp = new Smtp('127.0.0.1', 'user', 'pass', $port, encryption: 'starttls', timeout: 5);

        try {
            $smtp->send($this->createMail());
            $this->fail('Exception expected');
        } catch (TransportException $exception) {
            $this->assertStringStartsWith('TLS negotiation failed: ', $exception->getMessage());
        }

        unset($smtp);
        $this->assertSame(['plain:EHLO', 'plain:STARTTLS', 'tls:FAILED'], $this->stopServer());
    }

    public function testSend_starttlsHostnameMismatch(): void
    {
        $port = $this->startServer('starttls');

        $smtp = new Smtp(
            '127.0.0.1',
            port: $port,
            encryption: 'starttls',
            context: ['ssl' => ['cafile' => self::$directory . '/ca.pem', 'peer_name' => 'smtp.getberlioz.com']],
            timeout: 5,
        );

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('TLS negotiation failed: ');

        $smtp->send($this->createMail());
    }

    public function testSend_implicitTls(): void
    {
        $port = $this->startServer('implicit');

        $smtp = new Smtp(
            '127.0.0.1',
            'user',
            'pass',
            $port,
            encryption: SmtpEncryption::TLS,
            context: ['ssl' => ['cafile' => self::$directory . '/ca.pem']],
            timeout: 5,
        );
        $smtp->send($this->createMail());
        unset($smtp);

        $this->assertSame(
            ['tls:EHLO', 'tls:AUTH', 'tls:MAIL', 'tls:RCPT', 'tls:DATA', 'tls:QUIT'],
            $this->stopServer(),
        );
    }

    public function testSend_implicitTlsUntrustedCertificate(): void
    {
        $port = $this->startServer('implicit');

        $smtp = new Smtp('127.0.0.1', port: $port, encryption: SmtpEncryption::TLS, timeout: 5);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Unable to connect to "tls://127.0.0.1:' . $port . '"');

        $smtp->send($this->createMail());
    }

    public function testSend_legacyHostScheme(): void
    {
        $port = $this->startServer('implicit');

        $smtp = new Smtp(
            'tls://127.0.0.1',
            port: $port,
            context: ['ssl' => ['cafile' => self::$directory . '/ca.pem']],
            timeout: 5,
        );
        $smtp->send($this->createMail());
        unset($smtp);

        $this->assertSame(['tls:EHLO', 'tls:MAIL'], array_slice($this->stopServer(), 0, 2));
    }

    public function testSend_warnsInsecureContext(): void
    {
        $port = $this->startServer('starttls');

        $smtp = new Smtp(
            '127.0.0.1',
            port: $port,
            encryption: 'starttls',
            context: ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]],
            timeout: 5,
        );
        $smtp->setLogger($logger = $this->createLogger());
        $smtp->send($this->createMail());
        unset($smtp);

        $this->assertContains(
            [
                LogLevel::WARNING,
                '{class} / {host} / TLS certificate validation is weakened by stream context options',
            ],
            $logger->logs,
        );
        $this->assertSame(['plain:EHLO', 'plain:STARTTLS', 'tls:EHLO'], array_slice($this->stopServer(), 0, 3));
    }

    public function testSend_connectionFailure(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int)parse_url('tcp://' . stream_socket_get_name($server, false), PHP_URL_PORT);
        fclose($server);

        $smtp = new Smtp('127.0.0.1', port: $port, timeout: 1);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Unable to connect to "tcp://127.0.0.1:' . $port . '"');

        $smtp->send($this->createMail());
    }

    public static function provideEncryptions(): array
    {
        return [
            [null, null],
            [SmtpEncryption::STARTTLS, 'starttls'],
            [SmtpEncryption::TLS, 'tls'],
            ['starttls', 'starttls'],
            ['STARTTLS', 'starttls'],
            ['tls', 'tls'],
        ];
    }

    #[DataProvider('provideEncryptions')]
    public function testConstruct_encryption(SmtpEncryption|string|null $encryption, ?string $expected): void
    {
        $smtp = new Smtp('smtp.getberlioz.com', encryption: $encryption);

        $this->assertSame($expected, $smtp->__debugInfo()['encryption']);
    }

    public function testConstruct_invalidEncryption(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown SMTP encryption "ssl", expected one of: starttls, tls');

        new Smtp('smtp.getberlioz.com', encryption: 'ssl');
    }

    public static function provideHostsWithScheme(): array
    {
        return [
            ['ssl://smtp.getberlioz.com', 'starttls'],
            ['tls://smtp.getberlioz.com', 'tls'],
            ['tcp://smtp.getberlioz.com', 'starttls'],
        ];
    }

    #[DataProvider('provideHostsWithScheme')]
    public function testConstruct_hostWithSchemeAndEncryption(string $host, string $encryption): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            sprintf('Host "%s" must not contain a scheme when encryption "%s" is defined', $host, $encryption)
        );

        new Smtp($host, encryption: $encryption);
    }

    public function testConstruct_invalidContext(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SMTP context must be an array of options or a stream context');

        new Smtp('smtp.getberlioz.com', context: 'foo');
    }

    public function testConstruct_timeout(): void
    {
        $this->assertSame(10, (new Smtp())->__debugInfo()['timeout']);
        $this->assertSame(3, (new Smtp(timeout: 3))->__debugInfo()['timeout']);
    }

    public function testConstruct_deprecatedOptions(): void
    {
        $deprecations = [];
        set_error_handler(static function (int $severity, string $message) use (&$deprecations): bool {
            if (E_USER_DEPRECATED !== $severity) {
                return false;
            }

            $deprecations[] = $message;

            return true;
        });

        try {
            $legacy = new Smtp('smtp.getberlioz.com', null, null, 25, ['timeout' => 5]);
            $overridden = new Smtp('smtp.getberlioz.com', options: ['timeout' => 5], timeout: 3);
            new Smtp('smtp.getberlioz.com', null, null, 25, []);
        } finally {
            restore_error_handler();
        }

        $this->assertSame(5, $legacy->__debugInfo()['timeout']);
        $this->assertSame(3, $overridden->__debugInfo()['timeout']);
        $this->assertSame(
            array_fill(
                0,
                2,
                'Argument $options of Berlioz\Mailer\Transport\Smtp::__construct() is deprecated since 3.4 ' .
                'and will be removed in 4.0, use named argument $timeout instead',
            ),
            $deprecations,
        );
    }

    public function testMailerConfiguration(): void
    {
        $mailer = new Mailer([
            'transport' => [
                'name' => 'smtp',
                'arguments' => [
                    'host' => 'smtp.getberlioz.com',
                    'username' => 'user',
                    'password' => 'pass',
                    'port' => 587,
                    'encryption' => 'starttls',
                    'context' => ['ssl' => ['cafile' => '/path/to/ca.pem']],
                    'timeout' => 5,
                ],
            ],
        ]);

        $this->assertSame(
            [
                'host' => 'smtp.getberlioz.com',
                'port' => 587,
                'encryption' => 'starttls',
                'timeout' => 5,
                'username' => 'user',
                'password' => '*** HIDDEN ***',
            ],
            $mailer->getTransport()->__debugInfo(),
        );
    }
}
