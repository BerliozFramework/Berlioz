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

namespace Berlioz\Mailer;

use Berlioz\Mailer\Exception\InvalidArgumentException;
use Berlioz\Mailer\Exception\TransportException;
use Berlioz\Mailer\Transport\PhpMail;
use Berlioz\Mailer\Transport\Smtp;
use Berlioz\Mailer\Transport\TransportInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionException;

class Mailer implements LoggerAwareInterface
{
    /** Default transports into the package. */
    public const DEFAULT_TRANSPORTS = [
        'smtp' => Smtp::class,
        'mail' => PhpMail::class,
    ];
    /** @var Transport\TransportInterface Transport */
    private $transport;
    /** @var LoggerInterface The logger instance. */
    private $logger;

    /**
     * Mailer constructor.
     *
     * @param array $options
     *
     * @throws InvalidArgumentException
     * @throws ReflectionException
     */
    public function __construct(array $options = [])
    {
        // Transport
        if (!empty($options['transport'])) {
            $classArgs = [];

            if (is_array($options['transport'])) {
                if (!empty($options['transport']['name'])) {
                    if (empty(self::DEFAULT_TRANSPORTS[$options['transport']['name']])) {
                        throw new InvalidArgumentException(
                            sprintf('Unknown "%s" default transport', $options['transport']['name'])
                        );
                    }

                    $className = self::DEFAULT_TRANSPORTS[$options['transport']['name']];
                } else {
                    if (empty($options['transport']['class'])) {
                        throw new InvalidArgumentException('Missing class name in "transport" options');
                    }

                    $className = $options['transport']['class'];
                }

                if (!empty($options['transport']['arguments'])) {
                    if (!is_array($options['transport']['arguments'])) {
                        throw new InvalidArgumentException('Class arguments of "transport" options must be an array');
                    }

                    $classArgs = $options['transport']['arguments'];
                }
            } else {
                if (!is_string($options['transport'])) {
                    throw new InvalidArgumentException('"transport" options must be an array or a valid class name');
                }

                $className = $options['transport'];
            }

            if (!class_exists($className)) {
                throw new InvalidArgumentException(sprintf('Class "%s" doesn\'t exists', $className));
            }

            if (!is_a($className, TransportInterface::class, true)) {
                throw new InvalidArgumentException(
                    sprintf('Transport class must be an instance of %s interface', TransportInterface::class)
                );
            }

            $class = new ReflectionClass($className);
            $object = $class->newInstanceArgs($classArgs);

            $this->setTransport($object);
        }
    }

    /**
     * Sets a logger.
     *
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;

        if (null !== $this->transport && $this->transport instanceof LoggerAwareInterface) {
            /** @var LoggerAwareInterface $transport */
            $transport = $this->transport;
            $transport->setLogger($this->logger);
        }
    }

    /**
     * Get transport.
     *
     * @return Transport\TransportInterface
     */
    public function getTransport(): TransportInterface
    {
        if (null === $this->transport) {
            $this->transport = new PhpMail();
        }

        return $this->transport;
    }

    /**
     * Set transport.
     *
     * @param Transport\TransportInterface $transport
     *
     * @return static
     */
    public function setTransport(TransportInterface $transport): Mailer
    {
        // Logger
        if ($transport instanceof LoggerAwareInterface && null !== $this->logger) {
            $transport->setLogger($this->logger);
        }

        $this->transport = $transport;

        return $this;
    }

    /**
     * Sending of email.
     *
     * @param Mail $mail Mail
     *
     * @return mixed Depends of transport
     * @throws TransportException if an error occurred during sending of mail.
     */
    public function send(Mail $mail)
    {
        return $this->getTransport()->send($mail);
    }

    /**
     * Mass sending of email.
     *
     * @param Mail $mail Mail
     * @param Address[] $addresses Address list
     * @param callable|null $callback Callback called after each email sent
     *
     * @return mixed Depends of transport
     * @throws TransportException if an error occurred during sending of mail.
     */
    public function massSend(Mail $mail, array $addresses, ?callable $callback = null)
    {
        return $this->getTransport()->massSend($mail, $addresses, $callback);
    }
}
