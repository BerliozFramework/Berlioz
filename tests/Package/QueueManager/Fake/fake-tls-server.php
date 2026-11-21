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

// Fake TLS server used by FakeTlsServer: prints its port, accepts one connection,
// then prints the result of the TLS handshake and the first bytes received.

[, $certFile, $keyFile] = $argv;

$server = stream_socket_server(
    'tcp://127.0.0.1:0',
    $errorCode,
    $errorMessage,
    STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
    stream_context_create(['ssl' => ['local_cert' => $certFile, 'local_pk' => $keyFile]]),
);
if (false === $server) {
    fwrite(STDERR, $errorMessage);
    exit(1);
}

echo parse_url('tcp://' . stream_socket_get_name($server, false), PHP_URL_PORT), PHP_EOL;

$client = @stream_socket_accept($server, 10);
if (false === $client) {
    echo 'NO_CONNECTION';
    exit(0);
}

stream_set_timeout($client, 5);
if (true !== @stream_socket_enable_crypto($client, true, STREAM_CRYPTO_METHOD_TLS_SERVER)) {
    echo 'TLS_HANDSHAKE_FAILED';
    exit(0);
}

echo 'TLS:', bin2hex((string)fread($client, 8));
fclose($client);
