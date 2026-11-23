<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2021 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Berlioz\Http\Message\Tests;

use Berlioz\Http\Message\Response;
use Berlioz\Http\Message\Stream;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase
{
    public function testConstructAndGetters()
    {
        $body = new Stream();
        $body->write('{"foo": "bar"}');

        $response = new Response($body, 300, $headers = ['Content-Type' => ['application/json']]);

        $this->assertEquals(300, $response->getStatusCode());
        $this->assertEquals($headers, $response->getHeaders());
        $this->assertEquals($body, $response->getBody());
    }

    public function testWithStatus()
    {
        $response = new Response(null, 200);
        $response2 = $response->withStatus(Response::HTTP_STATUS_NOT_FOUND, $reason = 'Foo not found');

        $this->assertNotEquals($response, $response2);
        $this->assertEquals(Response::HTTP_STATUS_OK, $response->getStatusCode());
        $this->assertEquals(Response::HTTP_STATUS_NOT_FOUND, $response2->getStatusCode());
        $this->assertEquals($reason, $response2->getReasonPhrase());
    }

    public function testConstructRejectsCRLFInReasonPhrase()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Reason phrase must not contain control characters');

        new Response(null, 200, [], "OK\r\nInjected: evil");
    }

    public function testConstructRejectsControlCharacterInReasonPhrase()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Reason phrase must not contain control characters');

        new Response(null, 200, [], "OK\x01");
    }

    public function testConstructAcceptsTabInReasonPhrase()
    {
        $this->assertSame("Very\tOK", (new Response(null, 200, [], "Very\tOK"))->getReasonPhrase());
    }

    public static function provideInvalidStatusCodes(): array
    {
        return [[0], [99], [600], [999], [-200]];
    }

    #[DataProvider('provideInvalidStatusCodes')]
    public function testConstructRejectsInvalidStatusCode(int $statusCode)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Status code must be between 100 and 599');

        new Response(null, $statusCode);
    }

    #[DataProvider('provideInvalidStatusCodes')]
    public function testWithStatusRejectsInvalidStatusCode(int $statusCode)
    {
        $response = new Response(null, 200);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Status code must be between 100 and 599');

        $response->withStatus($statusCode);
    }

    public function testStatusCodeBounds()
    {
        $this->assertSame(100, (new Response(null, 100))->getStatusCode());
        $this->assertSame(599, (new Response(null, 200))->withStatus(599)->getStatusCode());
    }

    public function testWithStatusRejectsCRLFInReasonPhrase()
    {
        $response = new Response(null, 200);

        $this->expectException(InvalidArgumentException::class);

        $response->withStatus(404, "Not Found\r\nInjected: evil");
    }
}
