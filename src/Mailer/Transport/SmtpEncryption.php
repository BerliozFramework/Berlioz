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

namespace Berlioz\Mailer\Transport;

/**
 * Enum SmtpEncryption.
 */
enum SmtpEncryption: string
{
    /** Plain connection upgraded with the STARTTLS command (commonly port 587). */
    case STARTTLS = 'starttls';
    /** Implicit TLS, encrypted from the first byte (commonly port 465). */
    case TLS = 'tls';
}
