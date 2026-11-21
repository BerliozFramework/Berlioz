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
 * Temporary certificates: a trusted CA, a server certificate for "localhost" signed by it, and an untrusted CA.
 */
final readonly class FakeTlsCertificates
{
    private const OPENSSL_CONFIG = <<<'CNF'
        [ req ]
        distinguished_name = dn
        [ dn ]
        [ v3_ca ]
        basicConstraints = critical, CA:TRUE
        keyUsage = critical, keyCertSign, cRLSign
        subjectKeyIdentifier = hash
        [ v3_server ]
        basicConstraints = critical, CA:FALSE
        keyUsage = critical, digitalSignature, keyEncipherment
        extendedKeyUsage = serverAuth
        subjectKeyIdentifier = hash
        authorityKeyIdentifier = keyid
        subjectAltName = DNS:localhost
        CNF;

    public string $caCert;
    public string $serverCert;
    public string $serverKey;
    public string $untrustedCaCert;

    private string $directory;

    public function __construct()
    {
        $this->directory = sys_get_temp_dir() . '/berlioz-tls-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0700) || throw new RuntimeException('Unable to create certificates directory');

        $configFile = $this->directory . '/openssl.cnf';
        file_put_contents($configFile, self::OPENSSL_CONFIG);
        $options = ['config' => $configFile, 'digest_alg' => 'sha256'];

        [$caCert, $caKey] = $this->createCa('Berlioz Test CA', $options, 1);
        [$untrustedCaCert] = $this->createCa('Berlioz Untrusted CA', $options, 2);

        $serverKey = $this->createKey($options);
        $serverCsr = openssl_csr_new(['commonName' => 'localhost'], $serverKey, $options);
        $serverCert = openssl_csr_sign(
            $serverCsr,
            $caCert,
            $caKey,
            1,
            $options + ['x509_extensions' => 'v3_server'],
            3,
        );
        false !== $serverCert || throw new RuntimeException('Unable to sign server certificate');

        $this->caCert = $this->exportCert('ca.pem', $caCert);
        $this->untrustedCaCert = $this->exportCert('untrusted-ca.pem', $untrustedCaCert);
        $this->serverCert = $this->exportCert('server.pem', $serverCert);
        openssl_pkey_export_to_file($serverKey, $this->serverKey = $this->directory . '/server.key', null, $options);
    }

    public function __destruct()
    {
        array_map(unlink(...), glob($this->directory . '/*') ?: []);
        @rmdir($this->directory);
    }

    private function createCa(string $commonName, array $options, int $serial): array
    {
        $key = $this->createKey($options);
        $csr = openssl_csr_new(['commonName' => $commonName], $key, $options);
        $cert = openssl_csr_sign($csr, null, $key, 1, $options + ['x509_extensions' => 'v3_ca'], $serial);
        false !== $cert || throw new RuntimeException('Unable to sign CA certificate');

        return [$cert, $key];
    }

    private function createKey(array $options): mixed
    {
        $key = openssl_pkey_new($options + ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        false !== $key || throw new RuntimeException('Unable to create private key');

        return $key;
    }

    private function exportCert(string $filename, mixed $cert): string
    {
        openssl_x509_export_to_file($cert, $path = $this->directory . '/' . $filename);

        return $path;
    }
}
