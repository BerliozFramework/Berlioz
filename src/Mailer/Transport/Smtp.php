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

    /** @var string Host */
    private $host;
    /** @var int Port */
    private $port;
    /** @var int Timeout */
    private $timeout;
    /** @var string Username */
    private $username;
    /** @var string Password */
    private $password;
    /** @var resource|false Resource */
    private $resource;

    /**
     * Smtp constructor.
     *
     * @param string|null $host
     * @param string|null $username
     * @param string|null $password
     * @param int $port
     * @param array $options
     */
    public function __construct(
        string $host = null,
        string $username = null,
        string $password = null,
        int $port = 25,
        array $options = []
    ) {
        // Defaults
        $this->host = $host ?? 'localhost';
        $this->port = $port ?? 25;
        $this->username = $username;
        $this->password = $password;
        $this->timeout = $options['timeout'] ?? 10;
    }

    /**
     * Smtp destructor.
     *
     * @throws TransportException if disconnection throw exception.
     */
    public function __destruct()
    {
        if ($this->isConnected()) {
            $this->disconnect();
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
            'timeout' => $this->timeout,
            'username' => $this->username,
            'password' => '*** HIDDEN ***'
        ];
    }

    /**
     * Connect to resource.
     *
     * @return void
     * @throws TransportException
     */
    private function connect(): void
    {
        $errno = null;
        $errstr = null;

        $this->resource = fsockopen(
            $this->host,
            $this->port,
            $errno,
            $errstr,
            $this->timeout
        );

        if (false === $this->resource) {
            throw new TransportException('Connection timeout');
        }

        if ($this->get($response) != '220') {
            // Log
            $this->log(LogLevel::ERROR, sprintf('Connection error: %s', $response));

            throw new TransportException(sprintf('Connection refused: %s', $response));
        }

        // Log
        $this->log(LogLevel::DEBUG, sprintf('Connection response: %s', $response));

        $this->write("HELO " . trim(gethostname()));

        if ($this->get($response) != "250") {
            // Log
            $this->log(LogLevel::ERROR, sprintf('HELO command error: %s', $response));

            throw new TransportException(sprintf('"HELO" command failed: %s', $response));
        }

        // Log
        $this->log(LogLevel::DEBUG, sprintf('HELO command response: %s', $response));

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

        $this->resource = false;
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
     *
     * @return bool|string Return code of SMTP command
     * @throws TransportException
     */
    private function get(&$response = null)
    {
        if (!$this->isConnected()) {
            throw new TransportException('Not connected');
        }

        if (($response = fgets($this->resource)) === false) {
            throw new TransportException('Reading failed');
        }

        $response = trim($response);

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

        if (!fwrite($this->resource, $data . $this->getLineFeed())) {
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

        // Headers
        $this->write($this->getHeaders($mail));

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
    public function massSend(Mail $mail, array $addresses, callable $callback = null): array
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
                    'class' => __CLASS__,
                    'host' => $this->host
                ]
            )
        );
    }
}