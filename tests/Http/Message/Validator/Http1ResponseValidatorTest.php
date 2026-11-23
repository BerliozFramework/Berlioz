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

namespace Berlioz\Http\Message\Tests\Validator;

use Berlioz\Http\Message\Exception\InvalidResponseException;
use Berlioz\Http\Message\Response;
use Berlioz\Http\Message\Validator\Http1ResponseValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

class Http1ResponseValidatorTest extends TestCase
{
    private function createResponse(
        string $protocolVersion = '1.1',
        int $statusCode = 200,
        string $reasonPhrase = 'OK',
        array $headers = [],
    ): ResponseInterface {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getProtocolVersion')->willReturn($protocolVersion);
        $response->method('getStatusCode')->willReturn($statusCode);
        $response->method('getReasonPhrase')->willReturn($reasonPhrase);
        $response->method('getHeaders')->willReturn($headers);
        $response->expects($this->never())->method('getBody');

        return $response;
    }

    public function testValidate_nativeResponse(): void
    {
        $response = new Response('BODY', 201, [
            'Content-Type' => 'text/plain',
            'Set-Cookie' => ['a=1; Path=/', 'b=2; HttpOnly'],
        ]);

        $head = (new Http1ResponseValidator())->validate($response);

        $this->assertSame('HTTP/1.1 201 Created', $head->statusLine);
        $this->assertSame(201, $head->statusCode);
        $this->assertSame(
            [
                'Content-Type' => ['text/plain'],
                'Set-Cookie' => ['a=1; Path=/', 'b=2; HttpOnly'],
            ],
            $head->headers,
        );
    }

    public function testValidate_thirdPartyResponse(): void
    {
        $response = $this->createResponse(
            protocolVersion: '2',
            statusCode: 302,
            reasonPhrase: "Found\tit",
            headers: [
                'location' => ['/next'],
                'set-cookie' => ['a=1', 'b=2', ''],
                'X-Obs' => ["caf\xC3\xA9"],
            ],
        );

        $head = (new Http1ResponseValidator())->validate($response);

        $this->assertSame("HTTP/2 302 Found\tit", $head->statusLine);
        $this->assertSame(302, $head->statusCode);
        $this->assertSame(
            [
                'location' => ['/next'],
                'set-cookie' => ['a=1', 'b=2', ''],
                'X-Obs' => ["caf\xC3\xA9"],
            ],
            $head->headers,
        );
    }

    public function testValidate_emptyReasonPhrase(): void
    {
        $head = (new Http1ResponseValidator())->validate($this->createResponse(statusCode: 599, reasonPhrase: ''));

        $this->assertSame('HTTP/1.1 599 ', $head->statusLine);
    }

    public static function provideValidProtocolVersions(): array
    {
        return [['1.0'], ['1.1'], ['2'], ['2.0'], ['3']];
    }

    #[DataProvider('provideValidProtocolVersions')]
    public function testValidate_validProtocolVersion(string $version): void
    {
        $head = (new Http1ResponseValidator())->validate($this->createResponse(protocolVersion: $version));

        $this->assertSame(sprintf('HTTP/%s 200 OK', $version), $head->statusLine);
    }

    public static function provideInvalidResponses(): array
    {
        return [
            'empty protocol' => [['protocolVersion' => ''], 'not a valid HTTP protocol version'],
            'two digits protocol' => [['protocolVersion' => '10.1'], 'not a valid HTTP protocol version'],
            'prefixed protocol' => [['protocolVersion' => 'HTTP/1.1'], 'not a valid HTTP protocol version'],
            'protocol trailing LF' => [['protocolVersion' => "1.1\n"], 'not a valid HTTP protocol version'],
            'protocol injection' => [
                ['protocolVersion' => "1.1 200 OK\r\nX-Injected: evil\r\n"],
                'not a valid HTTP protocol version',
            ],
            'status 99' => [['statusCode' => 99], 'not a valid HTTP status code'],
            'status 600' => [['statusCode' => 600], 'not a valid HTTP status code'],
            'status 0' => [['statusCode' => 0], 'not a valid HTTP status code'],
            'reason CRLF' => [['reasonPhrase' => "OK\r\nX-Injected: evil"], 'Reason phrase'],
            'reason NUL' => [['reasonPhrase' => "OK\0"], 'Reason phrase'],
            'reason DEL' => [['reasonPhrase' => "OK\x7F"], 'Reason phrase'],
            'pseudo header' => [['headers' => [':status' => ['200']]], 'not a valid HTTP/1 header name'],
            'header name space' => [['headers' => ['Bad Name' => ['x']]], 'not a valid HTTP/1 header name'],
            'header name LF' => [['headers' => ["X-Foo\n" => ['x']]], 'not a valid HTTP/1 header name'],
            'header name empty' => [['headers' => ['' => ['x']]], 'not a valid HTTP/1 header name'],
            'header value CRLF' => [
                ['headers' => ['X-Foo' => ["x\r\nX-Injected: evil"]]],
                'Value of header "X-Foo"',
            ],
            'header value leading space' => [['headers' => ['X-Foo' => [' x']]], 'Value of header "X-Foo"'],
            'header value trailing tab' => [['headers' => ['X-Foo' => ["x\t"]]], 'Value of header "X-Foo"'],
            'header value non string' => [['headers' => ['X-Foo' => [1]]], 'Value of header "X-Foo"'],
            'last header invalid' => [
                ['headers' => ['Content-Type' => ['text/plain'], 'Set-Cookie' => ['a=1', "b=2\n"]]],
                'Value of header "Set-Cookie"',
            ],
        ];
    }

    #[DataProvider('provideInvalidResponses')]
    public function testValidate_invalidResponse(array $fields, string $message): void
    {
        $response = $this->createResponse(...$fields);

        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage($message);

        (new Http1ResponseValidator())->validate($response);
    }
}
