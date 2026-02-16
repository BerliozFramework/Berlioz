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

/**
 * Class Address.
 */
class Address
{
    /** @var string Name */
    private $name;
    /** @var string Mail */
    private $mail;

    /**
     * Address constructor.
     *
     * @param string|null $mail
     * @param string|null $name
     *
     * @throws InvalidArgumentException if email address isn\'t valid.
     */
    public function __construct(string $mail = null, string $name = null)
    {
        if (null !== $mail) {
            $this->setMail($mail);
        }
        if (null !== $name) {
            $this->setName($name);
        }
    }

    /**
     * __toString() magic method.
     *
     * @return string
     */
    public function __toString(): string
    {
        // E-mail
        if (null !== $this->name && mb_strlen($this->name) > 0) {
            return sprintf(
                '%s <%s>',
                mb_encode_mimeheader($this->name, mb_detect_encoding($this->name), 'Q'),
                $this->mail
            );
        }

        return $this->mail;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Set name.
     *
     * @param string $name
     *
     * @return static
     */
    public function setName(string $name): Address
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get mail.
     *
     * @return string
     */
    public function getMail(): ?string
    {
        return $this->mail;
    }

    /**
     * Set mail.
     *
     * @param string $mail
     *
     * @return static
     * @throws InvalidArgumentException if email address isn\'t valid.
     */
    public function setMail(string $mail): Address
    {
        if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException(sprintf('"%s" isn\'t a valid email address', $mail));
        }

        $this->mail = $mail;

        return $this;
    }
}