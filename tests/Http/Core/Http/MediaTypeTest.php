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

namespace Berlioz\Http\Core\Tests\Http;

use Berlioz\Http\Core\Http\MediaType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MediaTypeTest extends TestCase
{
    public static function provideValid(): array
    {
        return [
            'simple' => ['application/json', 'application', 'json', []],
            'case' => ['Application/JSON; Charset=UTF-8', 'application', 'json', ['charset' => 'UTF-8']],
            'whitespaces' => [" text/plain ; a=1 ;\tb=2 ", 'text', 'plain', ['a' => '1', 'b' => '2']],
            'empty parameters' => ['text/plain;;a=1;', 'text', 'plain', ['a' => '1']],
            'quoted semicolon' => ['text/plain; p="x;y=z"', 'text', 'plain', ['p' => 'x;y=z']],
            'quoted pairs' => ['text/plain; p="a\\"b\\\\c\\0"', 'text', 'plain', ['p' => 'a"b\\c0']],
            'empty quoted' => ['text/plain; p=""', 'text', 'plain', ['p' => '']],
            'quoted space' => ['text/plain; p="a b"', 'text', 'plain', ['p' => 'a b']],
            'leading digit' => ['application/3gpp-ims+xml', 'application', '3gpp-ims+xml', []],
        ];
    }

    #[DataProvider('provideValid')]
    public function testParse(string $value, string $type, string $subtype, array $parameters): void
    {
        $mediaType = MediaType::parse($value);

        $this->assertNotNull($mediaType);
        $this->assertSame($type, $mediaType->type);
        $this->assertSame($subtype, $mediaType->subtype);
        $this->assertSame($parameters, $mediaType->parameters);
    }

    public static function provideInvalid(): array
    {
        return [
            'empty' => [''],
            'no subtype' => ['application'],
            'empty subtype' => ['application/'],
            'invalid token' => ['application/(json)'],
            'space in type' => ['appli cation/json'],
            'missing separator' => ['application/json charset=utf-8'],
            'missing parameter value' => ['application/json; charset'],
            'empty parameter value' => ['application/json; charset='],
            'unquoted space' => ['application/json; profile=a b'],
            'unclosed quoted string' => ['application/json; profile="broken'],
            'trailing quoted pair' => ['application/json; profile="a\\'],
            'control char' => ["application/json; profile=\"a\x01b\""],
            'list' => ['application/json, text/plain'],
            'full wildcard' => ['*/*'],
            'subtype wildcard' => ['application/*'],
        ];
    }

    #[DataProvider('provideInvalid')]
    public function testParse_invalid(string $value): void
    {
        $this->assertNull(MediaType::parse($value));
    }

    public static function provideWildcards(): array
    {
        return [
            'full' => ['*/*', true],
            'subtype' => ['text/*', true],
            'type only' => ['*/json', false],
            'partial subtype' => ['application/j*', false],
            'partial type' => ['app*/*', false],
        ];
    }

    #[DataProvider('provideWildcards')]
    public function testParse_wildcards(string $value, bool $valid): void
    {
        $this->assertSame($valid, null !== MediaType::parse($value, allowWildcard: true));
    }

    public static function provideMatches(): array
    {
        return [
            'exact' => ['application/json', 'application/json', true],
            'parameters ignored' => ['application/json; charset=utf-8', 'application/json; charset=latin1', true],
            'other subtype' => ['application/json', 'application/xml', false],
            'suffix' => ['application/json', 'application/vnd.api+json', false],
            'subtype wildcard' => ['application/*', 'application/sql', true],
            'other type' => ['text/*', 'application/json', false],
            'full wildcard' => ['*/*', 'image/png', true],
        ];
    }

    #[DataProvider('provideMatches')]
    public function testMatches(string $range, string $mediaType, bool $expected): void
    {
        $this->assertSame(
            $expected,
            MediaType::parse($range, allowWildcard: true)->matches(MediaType::parse($mediaType))
        );
    }

    public function testGetMediaType(): void
    {
        $this->assertSame('text/plain', MediaType::parse('Text/Plain; charset=utf-8')->getMediaType());
    }
}
