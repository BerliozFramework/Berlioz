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

use Berlioz\Mailer\Exception\MailerException;
use Berlioz\Mailer\Exception\TransportException;
use Berlioz\Mailer\Mail;

/**
 * Class PhpMail.
 */
class PhpMail extends AbstractTransport implements TransportInterface
{
    /**
     * @inheritDoc
     * @return bool
     * @throws MailerException
     */
    public function send(Mail $mail): bool
    {
        // To
        $toAddresses = $mail->getTo();
        $to = implode(', ', $toAddresses);
        if (empty($to)) {
            $to = 'undisclosed-recipients:;';
        }

        // Headers
        $headers = $this->getHeaders($mail, ['To', 'Subject']);

        // Mail
        $result =
            @mb_send_mail(
                $to,
                $mail->getSubject(),
                implode($this->getLineFeed(), $this->getContents($mail)),
                implode($this->getLineFeed(), $headers)
            );

        if (!$result) {
            throw new TransportException('Unable to send mail with mb_send_mail() PHP function.');
        }

        return $result;
    }
}