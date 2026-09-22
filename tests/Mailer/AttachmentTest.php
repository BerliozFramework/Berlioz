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

namespace Berlioz\Mailer\Tests;

use Berlioz\Mailer\Attachment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AttachmentTest extends TestCase
{
    public static function provideDomains(): array
    {
        return [
            [null, 'berlioz'],
            ['example.com', 'example.com'],
        ];
    }

    #[DataProvider('provideDomains')]
    public function testContentIdRetainsFormatAndSurvivesSerialization(?string $domain, string $expectedDomain): void
    {
        $attachment = new Attachment(__DIR__ . '/Transport/attachment.txt');
        $this->assertFalse($attachment->hasId());

        $id = $attachment->getId($domain);

        $this->assertMatchesRegularExpression(
            '/\Apart1\.[0-9]{8}\.[0-9]{8}@' . preg_quote($expectedDomain, '/') . '\z/',
            $id,
        );
        $this->assertTrue($attachment->hasId());
        $this->assertSame($id, $attachment->getId('another.example'));

        $restored = unserialize(serialize($attachment));
        $this->assertInstanceOf(Attachment::class, $restored);
        $this->assertTrue($restored->hasId());
        $this->assertSame($id, $restored->getId());
        $this->assertSame($attachment->getContents(), $restored->getContents());
    }
}
