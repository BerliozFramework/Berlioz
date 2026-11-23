<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2022 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\Http\Message\Tests\Stream;

use Berlioz\Http\Message\Stream\MemoryStream;
use Berlioz\Http\Message\Stream\MultipartStream;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stringable;

class MultipartStreamTest extends TestCase
{
    public static function provideValidBoundaries(): array
    {
        return [
            'alphanumeric' => ['abcXYZ0123'],
            'punctuation' => ['\'()+_,-./:=?'],
            'inner space' => ['foo bar'],
            'single char' => ['a'],
            '70 chars' => [str_repeat('a', 70)],
            'zero' => ['0'],
        ];
    }

    #[DataProvider('provideValidBoundaries')]
    public function testConstruct_validBoundary(string $boundary): void
    {
        $multipart = new MultipartStream($boundary);
        $multipart->addElement('field', 'value', ['Content-Type' => 'text/plain']);

        $this->assertSame($boundary, $multipart->getBoundary());
        $this->assertSame(
            '--' . $boundary . MultipartStream::EOL .
            'content-type: text/plain' . MultipartStream::EOL .
            'content-disposition: form-data; name="field"' . MultipartStream::EOL .
            'content-length: 5' . MultipartStream::EOL .
            MultipartStream::EOL .
            'value' . MultipartStream::EOL .
            '--' . $boundary . '--' . MultipartStream::EOL,
            $multipart->getContents(),
        );
    }

    public static function provideInvalidBoundaries(): array
    {
        return [
            'CRLF' => ["foo\r\nContent-Type: text/html"],
            'LF' => ["foo\nbar"],
            'NUL' => ["foo\0bar"],
            'trailing space' => ['foo '],
            'double quote' => ['foo"bar'],
            'semicolon' => ['foo;bar'],
            'unicode' => ['fooé'],
            '71 chars' => [str_repeat('a', 71)],
        ];
    }

    #[DataProvider('provideInvalidBoundaries')]
    public function testConstruct_invalidBoundary(string $boundary): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MultipartStream($boundary);
    }

    public static function provideInvalidHeaders(): array
    {
        return [
            'name with CRLF' => [["X-Foo\r\nX-Injected" => 'bar']],
            'name with colon' => [['X-Foo: bar' => 'baz']],
            'name with space' => [['X Foo' => 'bar']],
            'empty name' => [['' => 'bar']],
            'value with CRLF' => [['X-Foo' => "bar\r\n\r\n--boundary"]],
            'value with LF' => [['X-Foo' => "bar\nX-Injected: baz"]],
            'value with CR' => [['X-Foo' => "bar\rbaz"]],
            'value with NUL' => [['X-Foo' => "bar\0baz"]],
            'value with DEL' => [['X-Foo' => "bar\x7Fbaz"]],
            'array value' => [['X-Foo' => ['bar']]],
            'object value' => [['X-Foo' => new stdClass()]],
            'content-disposition with CRLF' => [
                ['Content-Disposition' => "form-data; name=\"foo\"\r\nContent-Type: text/html"],
            ],
            'content-type with CRLF' => [['Content-Type' => "text/plain\r\n\r\ninjected"]],
        ];
    }

    #[DataProvider('provideInvalidHeaders')]
    public function testAddElement_invalidHeaders(array $headers): void
    {
        $this->expectException(InvalidArgumentException::class);

        $multipart = new MultipartStream();
        $multipart->addElement('field', 'value', $headers);
    }

    public function testAddElement_validHeaderValues(): void
    {
        $multipart = new MultipartStream('boundary');
        $multipart->addElement('field', 'value', [
            'Content-Type' => 'text/plain',
            'X-Tab' => "foo\tbar",
            'X-Int' => 123,
            'X-Stringable' => new class implements Stringable {
                public function __toString(): string
                {
                    return 'stringable';
                }
            },
            'X-Null' => null,
        ]);

        $this->assertSame(
            '--boundary' . MultipartStream::EOL .
            'content-type: text/plain' . MultipartStream::EOL .
            "x-tab: foo\tbar" . MultipartStream::EOL .
            'x-int: 123' . MultipartStream::EOL .
            'x-stringable: stringable' . MultipartStream::EOL .
            'content-disposition: form-data; name="field"' . MultipartStream::EOL .
            'content-length: 5' . MultipartStream::EOL .
            MultipartStream::EOL .
            'value' . MultipartStream::EOL .
            '--boundary--' . MultipartStream::EOL,
            $multipart->getContents(),
        );
    }

    public static function provideFieldNames(): array
    {
        return [
            'double quote' => ['foo"bar'],
            'quote injection' => ['foo"; filename="evil.php'],
            'CRLF injection' => ["foo\"\r\nContent-Type: text/html\r\n\r\n--boundary"],
            'LF' => ["foo\nbar"],
            'CR' => ["foo\rbar"],
            'backslash' => ['foo\\bar'],
            'punctuation' => ['foo;bar=baz, qux[]\'(%)'],
            'unicode' => ['éàü 漢字 🎉'],
            'tab' => ["foo\tbar"],
            'percent' => ['100%'],
        ];
    }

    #[DataProvider('provideFieldNames')]
    public function testAddElement_fieldNameRoundTrip(string $fieldName): void
    {
        $multipart = new MultipartStream();
        $multipart->addElement($fieldName, 'value', ['Content-Type' => 'text/plain']);
        $multipart->addElement('next', 'other', ['Content-Type' => 'text/plain']);

        $parts = $this->parseMultipart((string)$multipart, $multipart->getBoundary());

        $this->assertCount(2, $parts);
        $this->assertSame(['content-type', 'content-disposition', 'content-length'], array_keys($parts[0]['headers']));
        $parameters = $this->parseDispositionParameters($parts[0]['headers']['content-disposition']);
        $this->assertSame(['name' => $this->whatwgEncode($fieldName)], $parameters);
        $this->assertSame($fieldName, $this->whatwgDecode($parameters['name']));
        $this->assertSame('value', $parts[0]['body']);
        $this->assertSame(
            ['name' => 'next'],
            $this->parseDispositionParameters($parts[1]['headers']['content-disposition']),
        );
        $this->assertSame('other', $parts[1]['body']);
    }

    #[DataProvider('provideFieldNames')]
    public function testAddStream_filenameRoundTrip(string $filename): void
    {
        $multipart = new MultipartStream();
        $multipart->addStream('file', new MemoryStream('value'), ['Content-Type' => 'text/plain'], $filename);

        $parts = $this->parseMultipart((string)$multipart, $multipart->getBoundary());

        $this->assertCount(1, $parts);
        $parameters = $this->parseDispositionParameters($parts[0]['headers']['content-disposition']);
        $this->assertSame(['name' => 'file', 'filename' => $this->whatwgEncode(basename($filename))], $parameters);
        $this->assertSame(basename($filename), $this->whatwgDecode($parameters['filename']));
        $this->assertSame('value', $parts[0]['body']);
    }

    public function testAddElement_fieldNameEncoding(): void
    {
        $multipart = new MultipartStream('boundary');
        $multipart->addElement("a\"b\r\nc", 'value', ['Content-Type' => 'text/plain']);

        $this->assertStringContainsString(
            'content-disposition: form-data; name="a%22b%0D%0Ac"' . MultipartStream::EOL,
            $multipart->getContents(),
        );
    }

    public function testAddElement_fieldNameWithNul(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $multipart = new MultipartStream();
        $multipart->addElement("foo\0bar", 'value');
    }

    public function testAddStream_filenameWithNul(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $multipart = new MultipartStream();
        $multipart->addStream('file', new MemoryStream('value'), filename: "foo\0bar.txt");
    }

    public function testRejectedPartLeavesStreamUnchanged(): void
    {
        $multipart = new MultipartStream();
        $multipart->addElement('valid', 'value', ['Content-Type' => 'text/plain']);
        $contents = $multipart->getContents();
        $size = $multipart->getSize();

        $attempts = [
            fn() => $multipart->addElement('field', 'value', ['X-Foo' => "bar\r\nbaz"]),
            fn() => $multipart->addElement("foo\0bar", 'value'),
            fn() => $multipart->addStream('field', new MemoryStream('value'), ["X\r\nFoo" => 'bar']),
            fn() => $multipart->addFile('file', __DIR__ . '/../test.txt', false, ['X-Foo' => ['bar']]),
            fn() => $multipart->addElements(
                ['first' => 'value', "second\0" => 'value'],
                ['Content-Type' => 'text/plain'],
            ),
        ];

        foreach ($attempts as $attempt) {
            try {
                $attempt();
                $this->fail('Expected InvalidArgumentException');
            } catch (InvalidArgumentException) {
            }

            $this->assertSame($contents, $multipart->getContents());
            $this->assertSame($size, $multipart->getSize());
        }
    }

    public function testAddElements_integerKeys(): void
    {
        $multipart = new MultipartStream('boundary');
        $multipart->addElements(['foo', 'bar'], ['Content-Type' => 'text/plain']);

        $parts = $this->parseMultipart($multipart->getContents(), 'boundary');

        $this->assertCount(2, $parts);
        $this->assertSame('form-data; name="0"', $parts[0]['headers']['content-disposition']);
        $this->assertSame('form-data; name="1"', $parts[1]['headers']['content-disposition']);
    }

    /**
     * Parse a multipart body, the way a strict receiver would.
     *
     * @return array<array{headers: array<string, string>, body: string}>
     */
    private function parseMultipart(string $contents, string $boundary): array
    {
        $eol = MultipartStream::EOL;
        $closing = '--' . $boundary . '--' . $eol;

        $this->assertStringEndsWith($closing, $contents);
        $contents = substr($contents, 0, -strlen($closing));

        if ('' === $contents) {
            return [];
        }

        $this->assertStringStartsWith('--' . $boundary . $eol, $contents);
        $chunks = explode($eol . '--' . $boundary . $eol, substr($contents, strlen('--' . $boundary . $eol), -2));

        $parts = [];
        foreach ($chunks as $chunk) {
            [$rawHeaders, $body] = explode($eol . $eol, $chunk, 2);
            $headers = [];
            foreach (explode($eol, $rawHeaders) as $line) {
                $this->assertMatchesRegularExpression('/\A[!#$%&\'*+\-.^_`|~0-9A-Za-z]+: [^\r\n]*\z/', $line);
                [$name, $value] = explode(': ', $line, 2);
                $this->assertArrayNotHasKey($name, $headers);
                $headers[$name] = $value;
            }
            $parts[] = ['headers' => $headers, 'body' => $body];
        }

        return $parts;
    }

    /**
     * Parse Content-Disposition parameters (quoted-string values without escapes).
     *
     * @return array<string, string>
     */
    private function parseDispositionParameters(string $value): array
    {
        $this->assertMatchesRegularExpression('/\Aform-data(; [a-z]+="[^"\r\n\0]*")*\z/', $value);
        preg_match_all('/; ([a-z]+)="([^"]*)"/', $value, $matches, PREG_SET_ORDER);

        $parameters = [];
        foreach ($matches as [, $name, $paramValue]) {
            $this->assertArrayNotHasKey($name, $parameters);
            $parameters[$name] = $paramValue;
        }

        return $parameters;
    }

    private function whatwgEncode(string $value): string
    {
        return str_replace(["\r", "\n", '"'], ['%0D', '%0A', '%22'], $value);
    }

    private function whatwgDecode(string $value): string
    {
        return str_replace(['%0D', '%0A', '%22'], ["\r", "\n", '"'], $value);
    }

    public static function provideBoundaries(): array
    {
        return [[null], [''], ['custom-boundary']];
    }

    #[DataProvider('provideBoundaries')]
    public function testBoundaryRemainsConsistentWithDelimiters(?string $provided): void
    {
        $multipart = new MultipartStream($provided);
        $boundary = $multipart->getBoundary();
        if ($provided) {
            $this->assertSame($provided, $boundary);
        } else {
            $this->assertMatchesRegularExpression('/\A[a-zA-Z0-9]{70}\z/', $boundary);
        }

        $multipart->addElement('field', 'value', ['Content-Type' => 'text/plain']);
        $contents = (string)$multipart;

        $this->assertSame($boundary, $multipart->getBoundary());
        $this->assertStringStartsWith('--' . $boundary . MultipartStream::EOL, $contents);
        $this->assertStringEndsWith('--' . $boundary . '--' . MultipartStream::EOL, $contents);
        $this->assertSame(strlen($contents), $multipart->getSize());
        $this->assertSame($contents, (string)$multipart);
    }

    public function testGetContents_empty()
    {
        $multipart = new MultipartStream();

        $this->assertEquals(
            sprintf('--%s--' . MultipartStream::EOL, $multipart->getBoundary()),
            $multipart->getContents(),
        );
    }

    public function testAddElements()
    {
        $multipart = new MultipartStream();
        $multipart->addElements([
            'foo' => 'Content of foo!',
            'bar' => 'Content of bar!',
            'baz' => 'Content of baz!',
        ]);

        $this->assertEquals(
            "--{$multipart->getBoundary()}" . MultipartStream::EOL .
            "content-disposition: form-data; name=\"foo\"" . MultipartStream::EOL .
            "content-length: 15" . MultipartStream::EOL .
            "content-type: text/plain; charset=us-ascii" . MultipartStream::EOL .
            MultipartStream::EOL .
            "Content of foo!" . MultipartStream::EOL .
            "--{$multipart->getBoundary()}" . MultipartStream::EOL .
            "content-disposition: form-data; name=\"bar\"" . MultipartStream::EOL .
            "content-length: 15" . MultipartStream::EOL .
            "content-type: text/plain; charset=us-ascii" . MultipartStream::EOL .
            MultipartStream::EOL .
            "Content of bar!" . MultipartStream::EOL .
            "--{$multipart->getBoundary()}" . MultipartStream::EOL .
            "content-disposition: form-data; name=\"baz\"" . MultipartStream::EOL .
            "content-length: 15" . MultipartStream::EOL .
            "content-type: text/plain; charset=us-ascii" . MultipartStream::EOL .
            MultipartStream::EOL .
            "Content of baz!" . MultipartStream::EOL .
            "--{$multipart->getBoundary()}--" . MultipartStream::EOL,
            $multipart->getContents(),
        );
        $this->assertEquals(679, $multipart->getSize());
    }

    public function testAddElement()
    {
        $multipart = new MultipartStream();
        $multipart->addElement('foo', 'Content of foo!');

        $this->assertEquals(
            "--{$multipart->getBoundary()}" . MultipartStream::EOL .
            "content-disposition: form-data; name=\"foo\"" . MultipartStream::EOL .
            "content-length: 15" . MultipartStream::EOL .
            "content-type: text/plain; charset=us-ascii" . MultipartStream::EOL .
            MultipartStream::EOL .
            "Content of foo!" . MultipartStream::EOL .
            "--{$multipart->getBoundary()}--" . MultipartStream::EOL,
            $multipart->getContents(),
        );
    }

    public function testAddFile_detectionBase64()
    {
        $multipart = new MultipartStream();
        $multipart->addFile('my_file', __DIR__ . '/../test.txt', null);

        $this->assertEquals(
            "--{$multipart->getBoundary()}" . MultipartStream::EOL .
            "content-disposition: form-data; name=\"my_file\"; filename=\"test.txt\"" . MultipartStream::EOL .
            "content-length: 18" . MultipartStream::EOL .
            "content-type: text/plain; charset=us-ascii" . MultipartStream::EOL .
            MultipartStream::EOL .
            "It's a plain text!" . MultipartStream::EOL .
            "--{$multipart->getBoundary()}--" . MultipartStream::EOL,
            $multipart->getContents(),
        );
    }

    public function testAddFile_withBase64()
    {
        $multipart = new MultipartStream();
        $multipart->addFile('my_file', __DIR__ . '/../test.txt', true);

        $this->assertEquals(
            "--{$multipart->getBoundary()}" . MultipartStream::EOL .
            "content-transfer-encoding: base64" . MultipartStream::EOL .
            "content-disposition: form-data; name=\"my_file\"; filename=\"test.txt\"" . MultipartStream::EOL .
            "content-length: 24" . MultipartStream::EOL .
            "content-type: text/plain; charset=us-ascii" . MultipartStream::EOL .
            MultipartStream::EOL .
            "SXQncyBhIHBsYWluIHRleHQh" . MultipartStream::EOL .
            "--{$multipart->getBoundary()}--" . MultipartStream::EOL,
            $multipart->getContents(),
        );
    }

    public function testAddFile_withoutBase64()
    {
        $multipart = new MultipartStream();
        $multipart->addFile('my_file', __DIR__ . '/../test.txt', false);

        $this->assertEquals(
            "--{$multipart->getBoundary()}" . MultipartStream::EOL .
            "content-disposition: form-data; name=\"my_file\"; filename=\"test.txt\"" . MultipartStream::EOL .
            "content-length: 18" . MultipartStream::EOL .
            "content-type: text/plain; charset=us-ascii" . MultipartStream::EOL .
            MultipartStream::EOL .
            "It's a plain text!" . MultipartStream::EOL .
            "--{$multipart->getBoundary()}--" . MultipartStream::EOL,
            $multipart->getContents(),
        );
    }

    public function testAddFile_binary()
    {
        $multipart = new MultipartStream();
        $multipart->addFile('my_file', __DIR__ . '/../test.txt.gz');

        $this->assertEquals(
            "--{$multipart->getBoundary()}" . MultipartStream::EOL .
            "content-transfer-encoding: base64" . MultipartStream::EOL .
            "content-disposition: form-data; name=\"my_file\"; filename=\"test.txt.gz\"" . MultipartStream::EOL .
            "content-length: 68" . MultipartStream::EOL .
            "content-type: application/gzip; charset=binary" . MultipartStream::EOL .
            MultipartStream::EOL .
            "H4sICIFNUF4EAHRlc3QudHh0AAESAO3/SXQncyBhIHBsYWluIHRleHQh1KPs+RIAAAA=" . MultipartStream::EOL .
            "--{$multipart->getBoundary()}--" . MultipartStream::EOL,
            $multipart->getContents(),
        );
    }

    public function testAddFile_multiple()
    {
        $multipart = new MultipartStream();
        $multipart->addFile('my_first_file', __DIR__ . '/../test.txt', false);
        $multipart->addFile('my_second_file', __DIR__ . '/../test.txt.gz');

        $this->assertEquals(
            "--{$multipart->getBoundary()}" . MultipartStream::EOL .
            "content-disposition: form-data; name=\"my_first_file\"; filename=\"test.txt\"" . MultipartStream::EOL .
            "content-length: 18" . MultipartStream::EOL .
            "content-type: text/plain; charset=us-ascii" . MultipartStream::EOL .
            MultipartStream::EOL .
            "It's a plain text!" . MultipartStream::EOL .
            "--{$multipart->getBoundary()}" . MultipartStream::EOL .
            "content-transfer-encoding: base64" . MultipartStream::EOL .
            "content-disposition: form-data; name=\"my_second_file\"; filename=\"test.txt.gz\"" . MultipartStream::EOL .
            "content-length: 68" . MultipartStream::EOL .
            "content-type: application/gzip; charset=binary" . MultipartStream::EOL .
            MultipartStream::EOL .
            "H4sICIFNUF4EAHRlc3QudHh0AAESAO3/SXQncyBhIHBsYWluIHRleHQh1KPs+RIAAAA=" . MultipartStream::EOL .
            "--{$multipart->getBoundary()}--" . MultipartStream::EOL,
            $multipart->getContents(),
        );
    }

    public function testAddFile_withHeaders()
    {
        $multipart = new MultipartStream();
        $multipart->addFile(
            'my_file',
            __DIR__ . '/../test.txt',
            headers: [
                'foo-bar' => 'qux',
                'content-length' => 'defined'
            ]
        );

        $this->assertEquals(
            "--{$multipart->getBoundary()}" . MultipartStream::EOL .
            "foo-bar: qux" . MultipartStream::EOL .
            "content-length: defined" . MultipartStream::EOL .
            "content-disposition: form-data; name=\"my_file\"; filename=\"test.txt\"" . MultipartStream::EOL .
            "content-type: text/plain; charset=us-ascii" . MultipartStream::EOL .
            MultipartStream::EOL .
            "It's a plain text!" . MultipartStream::EOL .
            "--{$multipart->getBoundary()}--" . MultipartStream::EOL,
            $multipart->getContents(),
        );
    }
}
