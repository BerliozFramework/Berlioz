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

use Berlioz\Http\Message\Validator\HttpFieldGrammar;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HttpFieldGrammarTest extends TestCase
{
    public static function provideFieldNames(): array
    {
        return [
            'simple' => ['Content-Type', true, false],
            'all token chars' => ["!#$%&'*+-.^_`|~09AZaz", true, false],
            'pseudo' => [':status', false, true],
            'empty' => ['', false, false],
            'colon only' => [':', false, false],
            'double colon' => ['::status', false, false],
            'trailing colon' => ['X-Foo:', false, false],
            'space' => ['Bad Name', false, false],
            'trailing LF' => ["X-Foo\n", false, false],
            'pseudo trailing LF' => [":status\n", false, false],
            'CR' => ["X-Foo\rBar", false, false],
            'NUL' => ["X-Foo\0", false, false],
            'separator' => ['X(Foo)', false, false],
            'non ASCII' => ["X-F\xC3\xA9", false, false],
        ];
    }

    #[DataProvider('provideFieldNames')]
    public function testIsFieldName(string $name, bool $isFieldName, bool $isPseudoFieldName): void
    {
        $this->assertSame($isFieldName, HttpFieldGrammar::isFieldName($name));
        $this->assertSame($isPseudoFieldName, HttpFieldGrammar::isPseudoFieldName($name));
    }

    public static function provideFieldValues(): array
    {
        return [
            'empty' => ['', true],
            'simple' => ['text/html; charset=UTF-8', true],
            'inner space and tab' => ["a b\tc", true],
            'obs-text' => ["caf\xC3\xA9", true],
            'visible chars' => ['!"#$%&\'()*+,-./:;<=>?@[\]^_`{|}~', true],
            'leading space' => [' value', false],
            'trailing space' => ['value ', false],
            'leading tab' => ["\tvalue", false],
            'trailing tab' => ["value\t", false],
            'CR' => ["value\rX-Injected: evil", false],
            'LF' => ["value\nX-Injected: evil", false],
            'trailing LF' => ["value\n", false],
            'NUL' => ["value\0", false],
            'other control' => ["value\x01", false],
            'escape' => ["value\x1B", false],
            'DEL' => ["value\x7F", false],
        ];
    }

    #[DataProvider('provideFieldValues')]
    public function testIsFieldValue(string $value, bool $expected): void
    {
        $this->assertSame($expected, HttpFieldGrammar::isFieldValue($value));
    }

    public static function provideReasonPhrases(): array
    {
        return [
            'empty' => ['', true],
            'simple' => ['Not Found', true],
            'tab' => ["Not\tFound", true],
            'surrounding spaces' => [' OK ', true],
            'obs-text' => ["Requ\xEAte OK", true],
            'CRLF' => ["OK\r\nX-Injected: evil", false],
            'NUL' => ["OK\0", false],
            'other control' => ["OK\x01", false],
            'DEL' => ["OK\x7F", false],
        ];
    }

    #[DataProvider('provideReasonPhrases')]
    public function testIsReasonPhrase(string $reasonPhrase, bool $expected): void
    {
        $this->assertSame($expected, HttpFieldGrammar::isReasonPhrase($reasonPhrase));
    }
}
