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
use PHPUnit\Framework\TestCase;

class AddressTest extends TestCase
{
    public function testConstructorNotValidMail()
    {
        $this->expectException(InvalidArgumentException::class);
        new Address('namenotvalid.com');
    }

    public function testConstructorValidMail()
    {
        $address = new Address('name@notvalid.com');
        $this->assertInstanceOf(Address::class, $address);
        $address = new Address('ronan.giron@berlioz-framework.com');
        $this->assertInstanceOf(Address::class, $address);
        $address = new Address('alias+ronan@berlioz-framework.com');
        $this->assertInstanceOf(Address::class, $address);
    }

    public function testEmptyConstructor()
    {
        $address = new Address();
        $this->assertInstanceOf(Address::class, $address);
    }

    public function testToStringWithNoMail()
    {
        $address = new Address();
        $this->assertSame('', (string)$address);
    }

    public function testGetters()
    {
        $address = new Address('ronan.giron@berlioz-framework.com', 'Ronan Giron');
        $this->assertEquals('ronan.giron@berlioz-framework.com', $address->getMail());
        $this->assertEquals('Ronan Giron', $address->getName());

        $address = new Address('ronan.giron@berlioz-framework.com', 'Ronàn Gïron');
        $this->assertEquals(
            '=?UTF-8?Q?Ron=C3=A0n=20G=C3=AFron?= <ronan.giron@berlioz-framework.com>',
            (string)$address
        );
    }

    public function testSetters()
    {
        $address = new Address;
        $address->setName('Ronan Giron');
        $address->setMail('ronan.giron@berlioz-framework.com');

        $this->assertEquals('ronan.giron@berlioz-framework.com', $address->getMail());
        $this->assertEquals('Ronan Giron', $address->getName());
    }

    public function testSetNameRejectsCRLFInjection()
    {
        $address = new Address();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must not contain CR or LF');

        $address->setName("Ronan\r\nBcc: attacker@evil.com");
    }

    public function testConstructorRejectsCRLFInName()
    {
        $this->expectException(InvalidArgumentException::class);

        new Address('ronan.giron@berlioz-framework.com', "Ronan\r\nBcc: attacker@evil.com");
    }
}
