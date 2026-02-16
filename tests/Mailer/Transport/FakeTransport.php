<?php

declare(strict_types=1);

namespace Berlioz\Mailer\Tests\Transport;

use Berlioz\Mailer\Mail;
use Berlioz\Mailer\Transport\AbstractTransport;

class FakeTransport extends AbstractTransport
{
    /**
     * @inheritDoc
     */
    public function getContents(Mail $mail): array
    {
        return parent::getContents($mail);
    }

    public function getBoundary(string $type, string $prefix = null, int $length = 12): string
    {
        return parent::getBoundary($type, $prefix, $length);
    }

    public function send(Mail $mail)
    {
        // TODO: Implement send() method.
    }
}