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

/**
 * Fake SMTP server for tests, accepting a single connection.
 *
 * Usage: php smtp-server.php <scenario> <certificate> <log file>
 *
 * Scenarios:
 * - plain: no STARTTLS capability
 * - starttls: STARTTLS advertised and accepted
 * - starttls-refused: STARTTLS advertised but refused (454)
 * - implicit: implicit TLS
 *
 * Writes the listening port on STDOUT, and each received command prefixed by
 * the connection state ("plain" or "tls") in the log file.
 */

[, $scenario, $certificate, $logFile] = $argv;

$context = stream_context_create([
    'ssl' => [
        'local_cert' => $certificate,
        'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_SERVER | STREAM_CRYPTO_METHOD_TLSv1_3_SERVER,
    ],
]);
$server = stream_socket_server(
    ('implicit' === $scenario ? 'tls' : 'tcp') . '://127.0.0.1:0',
    $errno,
    $errstr,
    STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
    $context,
);

if (false === $server) {
    fwrite(STDERR, $errstr . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, parse_url('tcp://' . stream_socket_get_name($server, false), PHP_URL_PORT) . PHP_EOL);
fflush(STDOUT);

$log = fopen($logFile, 'w');
$tls = 'implicit' === $scenario;

// Handshake failure of implicit TLS
if (false === ($conn = @stream_socket_accept($server, 10))) {
    exit(0);
}
stream_set_timeout($conn, 10);

$reply = fn(string ...$lines) => fwrite($conn, implode("\r\n", $lines) . "\r\n");
$reply('220 fake ESMTP');
$data = false;

while (false !== ($line = fgets($conn))) {
    $line = rtrim($line, "\r\n");

    if ($data) {
        if ('.' === $line) {
            $data = false;
            $reply('250 OK');
        }
        continue;
    }

    $command = strtoupper(strtok($line, ' ') ?: '');
    fwrite($log, ($tls ? 'tls' : 'plain') . ':' . $command . PHP_EOL);

    switch ($command) {
        case 'EHLO':
            $advertise = !$tls && in_array($scenario, ['starttls', 'starttls-refused'], true);
            $reply(...array_filter(['250-fake', $advertise ? '250-STARTTLS' : null, '250 AUTH PLAIN']));
            break;
        case 'STARTTLS':
            if ('starttls-refused' === $scenario) {
                $reply('454 TLS not available');
                break;
            }
            $reply('220 Ready to start TLS');
            if (true !== @stream_socket_enable_crypto($conn, true)) {
                fwrite($log, 'tls:FAILED' . PHP_EOL);
                exit(0);
            }
            $tls = true;
            break;
        case 'AUTH':
            $reply('235 Authenticated');
            break;
        case 'MAIL':
        case 'RCPT':
            $reply('250 OK');
            break;
        case 'DATA':
            $data = true;
            $reply('354 Go ahead');
            break;
        case 'QUIT':
            $reply('221 Bye');
            exit(0);
        default:
            $reply('500 Unknown command');
    }
}
