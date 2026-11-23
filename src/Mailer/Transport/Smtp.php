<?php
/**
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2017 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\Mailer\Transport;

use Berlioz\Mailer\Address;
use Berlioz\Mailer\Exception\InvalidArgumentException;
use Berlioz\Mailer\Exception\MailerException;
use Berlioz\Mailer\Exception\TransportException;
use Berlioz\Mailer\Mail;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;

/**
 * Class Smtp.
 */
class Smtp extends AbstractTransport implements TransportInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private string $host;
    private int $timeout;
    private ?SmtpEncryption $encryption;
    /** @var resource Stream context */
    private $context;
    /** @var resource|null Resource */
    private $resource = null;

    /**
     * Smtp constructor.
     *
     * @param string|null $host
     * @param string|null $username
     * @param string|null $password
     * @param int $port
     * @param array $options Deprecated since 3.4 and removed in 4.0, use named argument $timeout instead
     * @param SmtpEncryption|string|null $encryption Connection encryption, null for the legacy plain connection
     * @param array|resource|null $context Stream context or stream context options
     * @param int|null $timeout Connection and read timeout in seconds (default: 10)
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        ?string $host = null,
        private ?string $username = null,
        private ?string $password = null,
        private int $port = 25,
        array $options = [],
        SmtpEncryption|string|null $encryption = null,
        mixed $context = null,
        ?int $timeout = null,
    ) {
        if ([] !== $options) {
            trigger_error(
                sprintf(
                    'Argument $options of %s() is deprecated since 3.4 and will be removed in 4.0, ' .
                    'use named argument $timeout instead',
                    __METHOD__,
                ),
                E_USER_DEPRECATED,
            );
        }

        // Defaults
        $this->host = $host ?? 'localhost';
        $this->timeout = (int)($timeout ?? $options['timeout'] ?? 10);
        $this->encryption = $this->resolveEncryption($encryption);

        if (null !== $this->encryption && str_contains($this->host, '://')) {
            throw new InvalidArgumentException(
                sprintf(
                    'Host "%s" must not contain a scheme when encryption "%s" is defined',
                    $this->host,
                    $this->encryption->value,
                )
            );
        }

        $this->context = $this->createContext($context);
    }

    /**
     * Smtp destructor.
     */
    public function __destruct()
    {
        try {
            if ($this->isConnected()) {
                $this->disconnect();
            }
        } catch (TransportException $e) {
            $this->log(LogLevel::WARNING, sprintf('Disconnection error in destructor: %s', $e->getMessage()));
        }
    }

    /**
     * __debugInfo() magic method.
     *
     * @return array
     */
    public function __debugInfo(): array
    {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'encryption' => $this->encryption?->value,
            'timeout' => $this->timeout,
            'username' => $this->username,
            'password' => '*** HIDDEN ***'
        ];
    }

    /**
     * Resolve encryption.
     *
     * @param SmtpEncryption|string|null $encryption
     *
     * @return SmtpEncryption|null
     * @throws InvalidArgumentException
     */
    private function resolveEncryption(SmtpEncryption|string|null $encryption): ?SmtpEncryption
    {
        if (!is_string($encryption)) {
            return $encryption;
        }

        return SmtpEncryption::tryFrom(strtolower($encryption)) ?? throw new InvalidArgumentException(
            sprintf(
                'Unknown SMTP encryption "%s", expected one of: %s',
                $encryption,
                implode(', ', array_map(fn(SmtpEncryption $case) => $case->value, SmtpEncryption::cases())),
            )
        );
    }

    /**
     * Create stream context.
     *
     * A copy is made of given stream context, to not alter it.
     *
     * @param mixed $context
     *
     * @return resource
     * @throws InvalidArgumentException
     */
    private function createContext(mixed $context)
    {
        $params = [];

        if (is_resource($context) && 'stream-context' === get_resource_type($context)) {
            $params = stream_context_get_params($context);
            unset($params['options']);
            $context = stream_context_get_options($context);
        }

        if (!is_array($context) && null !== $context) {
            throw new InvalidArgumentException('SMTP context must be an array of options or a stream context');
        }

        $context ??= [];

        if (null !== $this->encryption) {
            $context['ssl']['peer_name'] ??= $this->host;
            $context['ssl']['crypto_method'] ??=
                STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
        }

        return stream_context_create($context, $params);
    }

    /**
     * Connect to resource.
     *
     * @return void
     * @throws TransportException
     */
    private function connect(): void
    {
        $this->warnInsecureContext();

        $remote = sprintf(
            '%s%s:%d',
            match ($this->encryption) {
                SmtpEncryption::TLS => 'tls://',
                SmtpEncryption::STARTTLS => 'tcp://',
                null => str_contains($this->host, '://') ? '' : 'tcp://',
            },
            $this->host,
            $this->port,
        );
        $errno = 0;
        $errstr = '';

        $resource = $this->handleErrors(
            function () use ($remote, &$errno, &$errstr) {
                return stream_socket_client(
                    $remote,
                    $errno,
                    $errstr,
                    $this->timeout,
                    STREAM_CLIENT_CONNECT,
                    $this->context,
                );
            },
            $error,
        );

        if (false === $resource) {
            $message = sprintf('Unable to connect to "%s": %s', $remote, $errstr ?: $error ?: 'unknown error');
            $this->log(LogLevel::ERROR, $message);

            throw new TransportException($message);
        }

        stream_set_timeout($resource, $this->timeout);
        $this->resource = $resource;

        try {
            $this->handshake();
        } catch (TransportException $exception) {
            // Close without QUIT command, connection state is unknown
            $this->close();

            throw $exception;
        }
    }

    /**
     * Handshake: greeting, encryption and authentication.
     *
     * @return void
     * @throws TransportException
     */
    private function handshake(): void
    {
        if ($this->get($response) != '220') {
            // Log
            $this->log(LogLevel::ERROR, sprintf('Connection error: %s', $response));

            throw new TransportException(sprintf('Connection refused: %s', $response));
        }

        // Log
        $this->log(LogLevel::DEBUG, sprintf('Connection response: %s', $response));

        $capabilities = $this->ehlo();

        if (SmtpEncryption::STARTTLS === $this->encryption) {
            $this->startTls($capabilities);
            $capabilities = $this->ehlo();
        }

        if (!empty($this->username) && !empty($this->password)) {
            $this->write('AUTH PLAIN ' . base64_encode("\000" . $this->username . "\000" . $this->password));

            if ($this->get($response) != '235') {
                // Log
                $this->log(LogLevel::ERROR, sprintf('Login failed: %s', $response));

                throw new TransportException(sprintf('Login failed: %s', $response));
            }

            // Log
            $this->log(LogLevel::DEBUG, sprintf('Login success: %s', $response));
        } else {
            // Log
            $this->log(LogLevel::DEBUG, 'No login');
        }
    }

    /**
     * EHLO command.
     *
     * @return string[] Server capabilities
     * @throws TransportException
     */
    private function ehlo(): array
    {
        $this->write('EHLO ' . trim(gethostname() ?: 'localhost'));
        $lines = [];

        if ($this->get($response, $lines) != '250') {
            // Log
            $this->log(LogLevel::ERROR, sprintf('EHLO command error: %s', $response));

            throw new TransportException(sprintf('"EHLO" command failed: %s', $response));
        }

        // Log
        $this->log(LogLevel::DEBUG, sprintf('EHLO command response: %s', $response));

        // First line is the server greeting, next ones are capabilities
        return array_map(
            fn(string $line): string => strtoupper(strtok(substr($line, 4), ' ') ?: ''),
            array_slice($lines, 1),
        );
    }

    /**
     * STARTTLS command and TLS negotiation.
     *
     * @param string[] $capabilities
     *
     * @return void
     * @throws TransportException
     */
    private function startTls(array $capabilities): void
    {
        if (!in_array('STARTTLS', $capabilities, true)) {
            $this->log(LogLevel::ERROR, 'STARTTLS not supported by server');

            throw new TransportException('STARTTLS required but not supported by server');
        }

        $this->write('STARTTLS');

        if ($this->get($response) != '220') {
            // Log
            $this->log(LogLevel::ERROR, sprintf('STARTTLS command error: %s', $response));

            throw new TransportException(sprintf('"STARTTLS" command failed: %s', $response));
        }

        // Data received before TLS negotiation must not be trusted (RFC 3207)
        if (stream_get_meta_data($this->resource)['unread_bytes'] > 0) {
            $this->log(LogLevel::ERROR, 'Unexpected data received after STARTTLS reply');

            throw new TransportException('Unexpected data received after "STARTTLS" reply');
        }

        $result = $this->handleErrors(fn() => stream_socket_enable_crypto($this->resource, true), $error);

        if (true !== $result) {
            $message = sprintf('TLS negotiation failed: %s', $error ?? 'unknown error');
            $this->log(LogLevel::ERROR, $message);

            throw new TransportException($message);
        }

        // Log
        $this->log(LogLevel::DEBUG, 'TLS negotiated with STARTTLS');
    }

    /**
     * Warn if TLS certificate validation is weakened by context options.
     *
     * @return void
     */
    private function warnInsecureContext(): void
    {
        $ssl = stream_context_get_options($this->context)['ssl'] ?? [];

        if (false === ($ssl['verify_peer'] ?? true) ||
            false === ($ssl['verify_peer_name'] ?? true) ||
            true === ($ssl['allow_self_signed'] ?? false)) {
            $this->log(LogLevel::WARNING, 'TLS certificate validation is weakened by stream context options');
        }
    }

    /**
     * Call function and catch PHP errors.
     *
     * @param callable $callback
     * @param string|null $error Last error message
     *
     * @return mixed
     */
    private function handleErrors(callable $callback, ?string &$error = null): mixed
    {
        $error = null;
        set_error_handler(function (int $errno, string $errstr) use (&$error): bool {
            $error = $errstr;
            return true;
        });

        try {
            return $callback();
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Disconnect resource.
     *
     * @return void
     * @throws TransportException
     */
    private function disconnect(): void
    {
        if (!is_resource($this->resource)) {
            return;
        }

        $this->write('QUIT');

        if ($this->get($response) != '221') {
            // Log
            $this->log(LogLevel::ERROR, sprintf('QUIT command error: %s', $response));

            throw new TransportException(sprintf('Disconnection failed: %s', $response));
        }

        // Log
        $this->log(LogLevel::DEBUG, sprintf('QUIT command response: %s', $response));

        if (is_resource($this->resource) && @fclose($this->resource) === false) {
            throw new TransportException(sprintf('Unable to close resource "%s:%s"', $this->host, $this->port));
        }

        $this->resource = null;
    }

    /**
     * Close resource without QUIT command.
     *
     * @return void
     */
    private function close(): void
    {
        if (is_resource($this->resource)) {
            @fclose($this->resource);
        }

        $this->resource = null;
    }

    /**
     * Is connected ?
     *
     * @return bool
     */
    private function isConnected(): bool
    {
        return is_resource($this->resource);
    }

    /**
     * Get the resource data.
     *
     * @param mixed $response Complete response
     * @param string[] $lines All lines of response
     *
     * @return bool|string Return code of SMTP command
     * @throws TransportException
     */
    private function get(&$response = null, array &$lines = [])
    {
        if (!$this->isConnected()) {
            throw new TransportException('Not connected');
        }

        $response = '';
        $lines = [];

        do {
            if (($line = fgets($this->resource)) === false) {
                if (stream_get_meta_data($this->resource)['timed_out']) {
                    throw new TransportException('Reading timed out');
                }

                throw new TransportException('Reading failed');
            }

            $lines[] = $response = trim($line);
        } while (isset($response[3]) && $response[3] === '-');

        return substr($response, 0, 3);
    }

    /**
     * Write data to resource.
     *
     * @param $data
     *
     * @return void
     * @throws TransportException
     */
    private function write($data): void
    {
        if (!$this->isConnected()) {
            throw new TransportException('Not connected');
        }

        if (is_array($data)) {
            $data = implode($this->getLineFeed(), $data);
        }

        if (false === fwrite($this->resource, $data . $this->getLineFeed())) {
            throw new TransportException('Write failed');
        }
    }

    /**
     * @inheritDoc
     * @return void
     * @throws MailerException
     */
    public function send(Mail $mail)
    {
        if (null === $mail->getFrom()) {
            throw new TransportException('No sender address defined');
        }

        $response = '';

        if (!$this->isConnected()) {
            $this->connect();
        }

        $this->write(sprintf('MAIL FROM: <%s>', $mail->getFrom()->getMail()));

        if ($this->get($response) != '250') {
            // Log
            $this->log(LogLevel::ERROR, sprintf('MAIL FROM command error: %s', $response));

            throw new TransportException(sprintf('"MAIL FROM" command failed: %s', $response));
        }

        // Log
        $this->log(LogLevel::DEBUG, sprintf('MAIL FROM command response: %s', $response));

        // Addresses
        {
            $addresses = array_merge($mail->getTo(), $mail->getCc(), $mail->getBcc());

            if (count($addresses) == 0) {
                throw new TransportException('No recipients for the mail');
            }

            /** @var Address $address */
            foreach ($addresses as $address) {
                $this->write(sprintf('RCPT TO: <%s>', $address->getMail()));

                if ($this->get($response) != '250') {
                    // Log
                    $this->log(LogLevel::ERROR, sprintf('RCPT TO command error: %s', $response));

                    throw new TransportException(
                        sprintf('"RCPT TO" command failed for "%s" email address: %s', $address->getMail(), $response)
                    );
                }

                // Log
                $this->log(LogLevel::DEBUG, sprintf('RCPT TO command response: %s', $response));
            }
        }

        // Data
        $this->write('DATA');

        if ($this->get($response) != '354') {
            // Log
            $this->log(LogLevel::ERROR, sprintf('DATA command error: %s', $response));

            throw new TransportException(sprintf('DATA command failed: %s', $response));
        }

        // Log
        $this->log(LogLevel::DEBUG, sprintf('DATA command response: %s', $response));

        // Headers, Bcc recipients must stay in envelope only
        $this->write($this->getHeaders($mail, ['Bcc']));

        // Body
        $this->write($this->getContents($mail));

        // End of mail
        $this->write('.');

        if ($this->get($response) != '250') {
            // Log
            $this->log(LogLevel::ERROR, sprintf('End of data command error: %s', $response));

            throw new TransportException(sprintf('Write of data failed: %s', $response));
        }

        // Log
        $this->log(LogLevel::DEBUG, sprintf('End of data command response: %s', $response));
    }

    /**
     * @inheritDoc
     * @return void
     */
    public function massSend(Mail $mail, array $addresses, ?callable $callback = null): array
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        return parent::massSend($mail, $addresses, $callback);
    }

    /**
     * Logs.
     *
     * @param mixed $level
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    private function log($level, string $message, array $context = []): void
    {
        if (null === $this->logger) {
            return;
        }

        $this->logger->log(
            $level,
            sprintf('{class} / {host} / %s', $message),
            array_merge(
                $context,
                [
                    'class' => self::class,
                    'host' => $this->host
                ]
            )
        );
    }
}
