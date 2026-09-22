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

namespace Berlioz\Mailer\Tests\Transport;

use Berlioz\Mailer\Attachment;
use Berlioz\Mailer\Exception\TransportException;
use Berlioz\Mailer\Mail;
use Berlioz\Mailer\Transport\PhpMail;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AbstractTransportTest extends TestCase
{
    public static function provideBoundaries(): array
    {
        return [
            [null, 12, 12],
            ['mixed', 35, 35],
            ['prefix', 17, 17],
            [null, -13, 13],
            ['prefix', 3, 7],
        ];
    }

    #[DataProvider('provideBoundaries')]
    public function testBoundaryRetainsFormatAndIsCached(?string $prefix, int $length, int $expectedLength): void
    {
        $transport = new FakeTransport();
        $boundary = $transport->getBoundary('mixed', $prefix, $length);
        $expectedPrefix = null === $prefix ? '' : $prefix . '-';

        $this->assertSame($expectedLength, strlen($boundary));
        $this->assertMatchesRegularExpression('/\A' . preg_quote($expectedPrefix, '/') . '[A-Z0-9]*\z/', $boundary);
        $this->assertSame($boundary, $transport->getBoundary('mixed', 'ignored', 50));
    }

    public function testGetContents()
    {
        $transport = new FakeTransport();

        $mail = new Mail();
        $mail
            ->setText('TEXT')
            ->setHtml('HTML')
            ->addAttachment($attachment = new Attachment(__DIR__ . '/attachment.txt'))
            ->addAttachment(new Attachment(__DIR__ . '/attachment2.txt'));
        $attachment->getId();
        $boundaryMixed = $transport->getBoundary('mixed');
        $boundaryAlternative = $transport->getBoundary('alternative');
        $boundaryRelated = $transport->getBoundary('related');
        $contents = $transport->getContents($mail);

        $this->assertEquals(
            [
                sprintf("Content-Type: multipart/mixed; boundary=\"%s\"", $boundaryMixed),
                "",
                "This is a multi-part message in MIME format.",
                "",
                sprintf("--%s", $boundaryMixed),
                sprintf("Content-Type: multipart/alternative; boundary=\"%s\"", $boundaryAlternative),
                "",
                "This is a multi-part message in MIME format.",
                "",
                sprintf("--%s", $boundaryAlternative),
                "Content-Type: text/plain; charset=\"ASCII\"; format=flowed; delsp=yes",
                "Content-Transfer-Encoding: base64",
                "",
                "VEVYVA==",
                "",
                "",
                sprintf("--%s", $boundaryAlternative),
                sprintf("Content-Type: multipart/related; boundary=\"%s\"", $boundaryRelated),
                "",
                "This is a multi-part message in MIME format.",
                "",
                sprintf("--%s", $boundaryRelated),
                "Content-Type: text/html; charset=\"ASCII\"; format=flowed; delsp=yes",
                "Content-Transfer-Encoding: quoted-printable",
                "",
                "HTML",
                "",
                sprintf("--%s", $boundaryRelated),
                "Content-Type: text/plain; name=\"attachment.txt\"",
                "Content-Transfer-Encoding: base64",
                "Content-Disposition: inline",
                sprintf("Content-ID: <%s>", $attachment->getId()),
                "",
                "QVRUQUNITUVOVA==",
                "",
                sprintf("--%s--", $boundaryRelated),
                sprintf("--%s--", $boundaryAlternative),
                sprintf("--%s", $boundaryMixed),
                "Content-Type: text/plain; name=\"attachment2.txt\"",
                "Content-Transfer-Encoding: base64",
                "Content-Disposition: attachment;",
                "    filename=\"attachment2.txt\"",
                "",
                "QVRUQUNITUVOVDI=",
                "",
                sprintf("--%s--", $boundaryMixed),
            ],
            $contents
        );
    }

    public function testSendThrowsWhenNoFromAddress()
    {
        $transport = new PhpMail();
        $mail = new Mail();
        $mail->setText('Hello');

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('No sender address defined');

        $transport->send($mail);
    }
}
