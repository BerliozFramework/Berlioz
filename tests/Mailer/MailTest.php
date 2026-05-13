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

namespace Berlioz\Mailer\Tests;

use Berlioz\Mailer\Address;
use Berlioz\Mailer\Exception\InvalidArgumentException;
use Berlioz\Mailer\Mail;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class MailTest extends TestCase
{
    const TEST_HEADERS = ['Content-Type' => 'test/test', 'DKIM' => 'azerty'];
    const TEST_INVALID_HEADERS = ['Content-Type' => 'test/test', 'To' => 'ronan.giron@berlioz-framework.com'];

    public function testSetHeaders()
    {
        $mail = new Mail;
        $mail->setHeaders(self::TEST_HEADERS);

        $reflectionProp = new ReflectionProperty($mail, 'headers');
        PHP_VERSION_ID < 80100 && $reflectionProp->setAccessible(true);

        $this->assertTrue(is_array($reflectionProp->getValue($mail)['Content-Type']));
        $this->assertEquals(['test/test'], $reflectionProp->getValue($mail)['Content-Type']);
    }

    public function testAddHeaderWithoutReplacement()
    {
        $mail = new Mail;
        $mail->setHeaders(self::TEST_HEADERS);
        $mail->addHeader('Content-Type', 'test2/test2');

        $reflectionProp = new ReflectionProperty($mail, 'headers');
        PHP_VERSION_ID < 80100 && $reflectionProp->setAccessible(true);

        $this->assertTrue(is_array($reflectionProp->getValue($mail)['Content-Type']));
        $this->assertEquals(['test/test', 'test2/test2'], $reflectionProp->getValue($mail)['Content-Type']);
    }

    public function testAddHeaderWithReplacement()
    {
        $mail = new Mail;
        $mail->setHeaders(self::TEST_HEADERS);
        $mail->addHeader('Content-Type', 'test2/test2', true);

        $reflectionProp = new ReflectionProperty($mail, 'headers');
        PHP_VERSION_ID < 80100 && $reflectionProp->setAccessible(true);

        $this->assertTrue(is_array($reflectionProp->getValue($mail)['Content-Type']));
        $this->assertEquals(['test2/test2'], $reflectionProp->getValue($mail)['Content-Type']);
        $this->assertEquals(['azerty'], $reflectionProp->getValue($mail)['DKIM']);
    }

    public function testReserverdHeader()
    {
        $mail = new Mail;
        $this->expectException(InvalidArgumentException::class);
        $mail->setHeaders(self::TEST_INVALID_HEADERS);
    }

    public function testReservedHeaderCaseInsensitive()
    {
        $mail = new Mail;

        $this->expectException(InvalidArgumentException::class);

        $mail->addHeader('subject', 'bypass attempt');
    }

    public function testSetHeadersReservedCaseInsensitive()
    {
        $mail = new Mail;

        $this->expectException(InvalidArgumentException::class);

        $mail->setHeaders(['SUBJECT' => 'bypass attempt']);
    }

    public function testAddHeaderRejectsCRLFInjection()
    {
        $mail = new Mail;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must not contain CR or LF');

        $mail->addHeader('X-Custom', "value\r\nBcc: attacker@evil.com");
    }

    public function testAccessors()
    {
        $addressFrom = new Address('ronan1@berlioz-framework.com');
        $addressTo1 = new Address('ronan2@berlioz-framework.com');
        $addressTo2 = new Address('ronan3@berlioz-framework.com');
        $addressCc1 = new Address('ronan4@berlioz-framework.com');
        $addressCc2 = new Address('ronan5@berlioz-framework.com');
        $addressBcc1 = new Address('ronan6@berlioz-framework.com');

        $subject = 'My email subject';
        $text = 'Libertatis patrimonii.';
        $html = '<p>Libertatis patrimonii.</p>';

        $mail = new Mail;
        $mail->setSubject($subject);
        $mail->setFrom($addressFrom);
        $mail->setTo([$addressTo1, $addressTo2]);
        $mail->setCc([$addressCc1, $addressCc2]);
        $mail->setBcc([$addressBcc1]);
        $this->assertTrue(!$mail->hasText());
        $mail->setText($text);
        $this->assertTrue(!$mail->hasHtml());
        $mail->setHtml($html);

        $this->assertEquals($subject, $mail->getSubject());
        $this->assertTrue($mail->hasText());
        $this->assertTrue($mail->hasHtml());
        $this->assertEquals($text, $mail->getText());
        $this->assertEquals($html, $mail->getHtml());
        $this->assertEquals([$addressTo1, $addressTo2], $mail->getTo());
        $this->assertEquals([$addressCc1, $addressCc2], $mail->getCc());
        $this->assertEquals([$addressBcc1], $mail->getBcc());
    }
}
