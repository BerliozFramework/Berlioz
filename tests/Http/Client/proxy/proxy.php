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

/*
 * Fake forward proxy for tests, never forwards anything to the network.
 *
 * Usage: php proxy.php <log file> <certificate file> [tls]
 *
 * - Listens on an ephemeral port, written on STDOUT.
 * - Every received request head is appended to the log file (one JSON line).
 * - Absolute-form requests are answered by the proxy itself ("proxied").
 * - CONNECT to "denied.invalid" is refused (403).
 * - CONNECT to "forged.invalid" is accepted, with a forged plaintext response sent together with the CONNECT response.
 * - Other CONNECT are accepted; when the target port is 443, the proxy then acts as the TLS origin with the given
 *   certificate, and answers the tunneled request ("tunneled").
 * - With the "tls" flag, clients must first establish TLS with the proxy.
 */

[, $logFile, $certFile] = $argv;
$tls = ($argv[3] ?? null) === 'tls';

$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
if (false === $server) {
    fwrite(STDERR, $errstr);
    exit(1);
}
fwrite(STDOUT, parse_url('tcp://' . stream_socket_get_name($server, false), PHP_URL_PORT) . PHP_EOL);
fflush(STDOUT);

$log = function (string $type, string $head) use ($logFile): void {
    file_put_contents($logFile, json_encode(['type' => $type, 'head' => $head]) . "\n", FILE_APPEND | LOCK_EX);
};
$readHead = function ($conn): string {
    $head = '';
    while (false !== ($line = fgets($conn))) {
        if ($line === "\r\n") {
            break;
        }
        $head .= $line;
    }
    return $head;
};
$respond = function ($conn, string $body, int $status = 200): void {
    fwrite(
        $conn,
        sprintf("HTTP/1.1 %d X\r\nContent-Length: %d\r\nConnection: close\r\n\r\n%s", $status, strlen($body), $body)
    );
};
$enableTls = function ($conn) use ($certFile): bool {
    stream_context_set_option($conn, 'ssl', 'local_cert', $certFile);
    stream_context_set_option($conn, 'ssl', 'verify_peer', false);

    return true === @stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_SERVER);
};

while (true) {
    $conn = @stream_socket_accept($server, -1);
    if (false === $conn) {
        continue;
    }
    stream_set_timeout($conn, 5);

    try {
        if ($tls && !$enableTls($conn)) {
            $log('proxy-tls-failed', '');
            continue;
        }

        $head = $readHead($conn);
        if ('' === $head) {
            continue;
        }

        if (!str_starts_with($head, 'CONNECT ')) {
            $log('request', $head);
            $respond($conn, 'proxied');
            continue;
        }

        $log('connect', $head);
        $target = explode(' ', $head)[1];
        if (str_starts_with($target, 'denied.invalid:')) {
            $respond($conn, 'denied', 403);
            continue;
        }

        $established = "HTTP/1.1 200 Connection established\r\n\r\n";
        if (str_starts_with($target, 'forged.invalid:')) {
            $established .= "HTTP/1.1 200 OK\r\nContent-Length: 6\r\nConnection: close\r\n\r\nFORGED";
        }
        fwrite($conn, $established);

        if (str_ends_with($target, ':443') && !$enableTls($conn)) {
            $log('origin-tls-failed', '');
            continue;
        }

        $log('tunneled', $readHead($conn));
        $respond($conn, 'tunneled');
    } finally {
        @fclose($conn);
    }
}
