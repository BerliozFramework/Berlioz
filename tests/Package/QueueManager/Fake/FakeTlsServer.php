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

namespace Berlioz\Package\QueueManager\Tests\Fake;

use RuntimeException;

/**
 * TLS server in a child process, accepting one connection and reporting what it received.
 *
 * It is not an AMQP broker: a client completing the TLS handshake sends the AMQP protocol header,
 * then fails since no response is sent.
 */
final class FakeTlsServer
{
    public const REPORT_AMQP_OVER_TLS = 'TLS:414d515000000901';
    /** With TLS 1.3, the server completes the handshake before the client verifies the certificate. */
    public const REPORTS_WITHOUT_DATA = ['TLS_HANDSHAKE_FAILED', 'TLS:'];

    public readonly int $port;

    /** @var resource */
    private $process;
    private array $pipes = [];

    public function __construct(string $certFile, string $keyFile)
    {
        $process = proc_open(
            [PHP_BINARY, __DIR__ . '/fake-tls-server.php', $certFile, $keyFile],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $this->pipes,
        );
        false !== $process || throw new RuntimeException('Unable to start fake TLS server');
        $this->process = $process;

        $port = (int)fgets($this->pipes[1]);
        $port > 0 || throw new RuntimeException('Fake TLS server failed: ' . stream_get_contents($this->pipes[2]));
        $this->port = $port;
    }

    public function __destruct()
    {
        is_resource($this->process) && proc_terminate($this->process);
    }

    /**
     * Wait for the end of the server and get its report.
     *
     * @return string "NO_CONNECTION", "TLS_HANDSHAKE_FAILED" or "TLS:" followed by the received bytes (hex)
     */
    public function getReport(): string
    {
        $report = trim((string)stream_get_contents($this->pipes[1]));
        array_map(fclose(...), $this->pipes);
        proc_close($this->process);

        return $report;
    }
}
