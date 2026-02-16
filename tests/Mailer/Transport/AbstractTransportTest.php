<?php

namespace Berlioz\Mailer\Tests\Transport;

use Berlioz\Mailer\Attachment;
use Berlioz\Mailer\Mail;
use PHPUnit\Framework\TestCase;

class AbstractTransportTest extends TestCase
{
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
}
