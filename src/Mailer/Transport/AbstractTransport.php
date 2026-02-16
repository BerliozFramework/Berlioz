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
use Berlioz\Mailer\Attachment;
use Berlioz\Mailer\Exception\MailerException;
use Berlioz\Mailer\Mail;

/**
 * Class AbstractTransport.
 */
abstract class AbstractTransport implements TransportInterface
{
    /** @var string[] Boundaries */
    private $boundaries = [];

    /**
     * @inheritDoc
     * @return array
     */
    public function massSend(Mail $mail, array $addresses, callable $callback = null): array
    {
        $result = [];

        $i = 0;
        /** @var Address $address */
        foreach ($addresses as $address) {
            // Reset and set new address
            $mail->resetRecipients();
            $mail->setTo(is_array($address) ? $address : [$address]);

            // Send
            $result[] = $this->send($mail);

            if (is_callable($callback)) {
                $callback($address, $i);
            }

            $i++;
        }

        // And... reset ;)
        $mail->resetRecipients();

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function getLineFeed(): string
    {
        return "\r\n";
    }

    /**
     * Get headers.
     *
     * Note: This method is used by transport, users use getHeaders().
     *
     * @param Mail $mail Mail
     * @param array $exclude Headers to exclude
     *
     * @return string[]
     */
    protected function getHeaders(Mail $mail, array $exclude = []): array
    {
        $exclude = array_map('mb_strtolower', $exclude);
        $contents = [];

        // Get headers
        $headers = $mail->getHeaders();
        $headers['MIME-Version'] = ['1.0'];
        if (!in_array('subject', $exclude)) {
            $headers['Subject'] = [
                mb_encode_mimeheader(
                    $mail->getSubject(),
                    mb_detect_encoding($mail->getSubject()),
                    'Q'
                )
            ];
        }

        // Addresses
        if (!in_array('from', $exclude)) {
            $contents[] = sprintf('%s: %s', 'From', (string)$mail->getFrom());
        }
        if (!in_array('to', $exclude)) {
            if (count($mail->getTo()) > 0) {
                $contents[] = sprintf('%s: %s', 'To', implode(', ', $mail->getTo()));
            } else {
                $contents[] = 'To: undisclosed-recipients:;';
            }
        }
        if (!in_array('cc', $exclude) && count($mail->getCc()) > 0) {
            $contents[] = sprintf('%s: %s', 'Cc', implode(', ', $mail->getCc()));
        }
        if (!in_array('bcc', $exclude) && count($mail->getBcc()) > 0) {
            $contents[] = sprintf('%s: %s', 'Bcc', implode(', ', $mail->getBcc()));
        }

        // Complete with headers
        foreach ($headers as $name => $values) {
            if (!in_array(mb_strtolower($name), $exclude)) {
                foreach ((array)$values as $value) {
                    $contents[] = sprintf('%s: %s', $name, trim($value));
                }
            }
        }

        return $contents;
    }

    /**
     * Get contents.
     *
     * @param Mail $mail Mail
     *
     * @return string[]
     * @throws MailerException
     */
    protected function getContents(Mail $mail): array
    {
        $contents = [];

        // Attachments
        $attachments = $mail->getAttachments();
        $htmlAttachments =
            array_filter(
                $attachments,
                function ($attachment) {
                    return $attachment->hasId();
                }
            );
        $attachments =
            array_filter(
                $attachments,
                function ($attachment) {
                    return !$attachment->hasId();
                }
            );

        // Contents
        // If has $attachments
        if (count($attachments) > 0) {
            $contents[] = sprintf('Content-Type: multipart/mixed; boundary="%s"', $this->getBoundary('mixed'));
            $contents[] = '';
            $contents[] = 'This is a multi-part message in MIME format.';
            $contents[] = '';
            $contents[] = sprintf('--%s', $this->getBoundary('mixed'));
        }
        // If has text and html
        if ($mail->hasText() && $mail->hasHtml()) {
            $contents[] = sprintf(
                'Content-Type: multipart/alternative; boundary="%s"',
                $this->getBoundary('alternative')
            );
            $contents[] = '';
            $contents[] = 'This is a multi-part message in MIME format.';
            $contents[] = '';
            $contents[] = sprintf('--%s', $this->getBoundary('alternative'));
        }

        // Text
        if ($mail->hasText()) {
            $contents[] = sprintf(
                'Content-Type: text/plain; charset="%s"; format=flowed; delsp=yes',
                mb_detect_encoding($mail->getText())
            );
            $contents[] = 'Content-Transfer-Encoding: base64';
            $contents[] = '';
            $contents = array_merge($contents, str_split(base64_encode($mail->getText()), 76));
            $contents[] = '';
        }

        // Html
        if ($mail->hasHtml()) {
            if ($mail->hasText()) {
                $contents[] = '';
                $contents[] = sprintf('--%s', $this->getBoundary('alternative'));
            }

            if (count($htmlAttachments) > 0) {
                $contents[] = sprintf(
                    'Content-Type: multipart/related; boundary="%s"',
                    $this->getBoundary('related')
                );
                $contents[] = '';
                $contents[] = 'This is a multi-part message in MIME format.';
                $contents[] = '';
                $contents[] = sprintf('--%s', $this->getBoundary('related'));
            }

            $contents[] = sprintf(
                'Content-Type: text/html; charset="%s"; format=flowed; delsp=yes',
                mb_detect_encoding($mail->getHtml())
            );
            $contents[] = 'Content-Transfer-Encoding: quoted-printable';
            $contents[] = '';
            $contents = array_merge($contents, explode("\r\n", $this->quotedPrintableEncode($mail->getHtml(true))));
            $contents[] = '';

            if (count($htmlAttachments) > 0) {
                /** @var Attachment $attachment */
                foreach ($htmlAttachments as $attachment) {
                    $contents[] = sprintf('--%s', $this->getBoundary('related'));
                    $contents[] = sprintf(
                        'Content-Type: %s; name="%s"',
                        $attachment->getType(),
                        $attachment->getName()
                    );
                    $contents[] = 'Content-Transfer-Encoding: base64';
                    $contents[] = 'Content-Disposition: inline';
                    $contents[] = sprintf('Content-ID: <%s>', $attachment->getId());
                    $contents[] = '';
                    $contents = array_merge($contents, str_split(base64_encode($attachment->getContents()), 76));
                    $contents[] = '';
                }
                $contents[] = sprintf('--%s--', $this->getBoundary('related'));
            }

            if ($mail->hasText()) {
                $contents[] = sprintf('--%s--', $this->getBoundary('alternative'));
            }
        }

        // Attachments
        if (count($attachments) > 0) {
            /** @var Attachment $attachment */
            foreach ($attachments as $attachment) {
                $contents[] = sprintf('--%s', $this->getBoundary('mixed'));
                $contents[] = sprintf(
                    'Content-Type: %s; name="%s"',
                    $attachment->getType(),
                    $attachment->getName()
                );
                $contents[] = 'Content-Transfer-Encoding: base64';
                $contents[] = 'Content-Disposition: attachment;';
                $contents[] = sprintf('    filename="%s"', $attachment->getName());
                $contents[] = '';
                $contents = array_merge($contents, str_split(base64_encode($attachment->getContents()), 76));
                $contents[] = '';
            }
            $contents[] = sprintf('--%s--', $this->getBoundary('mixed'));
        }


        return $contents;
    }

    /**
     * Get boundary.
     *
     * @param string $type Boundary type (mixed, related or alternative)
     * @param string|null $prefix Prefix
     * @param int $length Length of boundary
     *
     * @return string
     */
    protected function getBoundary(string $type, string $prefix = null, int $length = 12): string
    {
        if (!empty($this->boundaries[$type])) {
            return $this->boundaries[$type];
        }

        $source = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

        $length = abs($length);
        $n = strlen($source);
        $this->boundaries[$type] = '--' . ($prefix ? $prefix . '-' : '');

        for ($i = 0; $i < ($length - 2); $i++) {
            $this->boundaries[$type] .= $source[mt_rand(1, $n) - 1];
        }

        $this->boundaries[$type] = substr($this->boundaries[$type], 0, $length);

        return $this->boundaries[$type];
    }

    /**
     * Quoted printable encode.
     *
     * Same as quoted_printable_encode() function, but dot at line first character.
     *
     * @param string $str
     *
     * @return string
     * @see https://www.php.net/manual/fr/function.quoted-printable-encode.php#115840
     */
    public function quotedPrintableEncode(string $str): string
    {
        $lp = 0;
        $ret = '';
        $hex = "0123456789ABCDEF";
        $length = strlen($str);
        $str_index = 0;

        while ($length--) {
            if ((($c = $str[$str_index++]) == "\015") && ($str[$str_index] == "\012") && $length > 0) {
                $ret .= "\015";
                $ret .= $str[$str_index++];
                $length--;
                $lp = 0;

                continue;
            }

            if (ctype_cntrl($c)
                || (ord($c) == 0x7f)
                || (ord($c) & 0x80)
                || ($c == '=')
                || (($c == ' ') && ($str[$str_index] == "\015"))) {
                if (($lp += 3) > 75) {
                    $ret .= '=';
                    $ret .= "\015";
                    $ret .= "\012";
                    $lp = 3;
                }
                $ret .= '=';
                $ret .= $hex[ord($c) >> 4];
                $ret .= $hex[ord($c) & 0xf];

                continue;
            }

            if ((++$lp) > 75) {
                $ret .= '=';
                $ret .= "\015";
                $ret .= "\012";
                $lp = 1;
            }

            $ret .= $c;

            if ($lp == 1 && $c == '.') {
                $ret .= '.';
                $lp++;
            }
        }

        return $ret;
    }
}