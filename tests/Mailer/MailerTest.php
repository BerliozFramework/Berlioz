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

use Berlioz\Mailer\Exception\InvalidArgumentException;
use Berlioz\Mailer\Mailer;
use Berlioz\Mailer\Transport\Smtp;
use PHPUnit\Framework\TestCase;

class MailerTest extends TestCase
{
    public function testConstructorExceptionTransportOptionsArgumentsArray()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class arguments of "transport" options must be an array');

        new Mailer([
            'transport' => [
                'class' => '\Berlioz\Mailer\Transport\Smtp',
                'arguments' => 'Test'
            ]
        ]);
    }

    public function testConstructorExceptionTransportOptionsClassMissing()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing class name in "transport" options');

        new Mailer([
            'transport' => [
                'classtest' => '\Berlioz\Mailer\Transport\Smtp',
                'arguments' => ['Test']
            ]
        ]);
    }

    public function testConstructorExceptionTransportOptionsNotStringOrArray()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"transport" options must be an array or a valid class name');

        new Mailer(['transport' => new Smtp]);
    }

    public function testConstructorExceptionClassDoesNotExists()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class "InvalidClass" doesn\'t exists');

        new Mailer(['transport' => ['class' => 'InvalidClass']]);
    }

    public function testConstructorExceptionInvalidClass()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Transport class must be an instance of Berlioz\Mailer\Transport\TransportInterface interface');

        new Mailer(['transport' => ['class' => '\Berlioz\Mailer\Tests\MailerTest']]);
    }
}
