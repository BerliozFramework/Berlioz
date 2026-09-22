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

namespace Berlioz\HtmlSelector\Tests;

use Berlioz\HtmlSelector\Exception\SelectorException;
use Berlioz\HtmlSelector\Extension\CssExtension;
use Berlioz\HtmlSelector\Extension\QueryExtension;
use Berlioz\HtmlSelector\HtmlSelector;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class XpathEscapingTest extends TestCase
{
    public static function provideValues(): array
    {
        return [
            'plain' => ['value'],
            'apostrophe' => ["it's"],
            'double quote' => ['a"b'],
            'both quotes' => ['a"b\'c'],
            'adjacent quotes' => ['"\'"'],
            'backslash' => ['a\\b'],
            'predicate injection' => ['" or @id="other'],
            'union injection' => ['"] | //* | //*[@id="'],
        ];
    }

    public static function provideAttributes(): iterable
    {
        foreach (self::provideValues() as $name => [$value]) {
            foreach (['=', '!=', '^=', '$=', '*=', '~=', '|='] as $operator) {
                yield $name . ' ' . $operator => [$value, $operator];
            }
        }
        yield 'empty value' => ['', '='];
    }

    #[DataProvider('provideAttributes')]
    public function testAttributeValuesRemainLiteral(string $value, string $operator): void
    {
        $document = $this->createDocument($value);
        $selector = sprintf('item[data-value%s"%s"]', $operator, addcslashes($value, "\\\""));
        $xpath = (new HtmlSelector())->solveXpath($selector);

        $this->assertSelectedId($document, $xpath, $operator === '!=' ? 'other' : 'target');
    }

    #[DataProvider('provideValues')]
    public function testContainsArgumentRemainsLiteral(string $value): void
    {
        $extension = new QueryExtension(new HtmlSelector());

        $this->assertSelectedId($this->createDocument($value), $extension->contains('//item', $value), 'target');
    }

    #[DataProvider('provideValues')]
    public function testLangArgumentRemainsLiteral(string $value): void
    {
        $extension = new CssExtension(new HtmlSelector());

        $this->assertSelectedId($this->createDocument($value), $extension->lang('//item', $value), 'target');
    }

    public static function provideInvalidNames(): array
    {
        return [
            ['123'],
            ['item[123]'],
            ['item[-invalid="value"]'],
        ];
    }

    #[DataProvider('provideInvalidNames')]
    public function testInvalidNamesAreRejected(string $selector): void
    {
        $this->expectException(SelectorException::class);

        (new HtmlSelector())->solveXpath($selector);
    }

    private function createDocument(string $value): DOMDocument
    {
        $document = new DOMDocument();
        $root = $document->appendChild($document->createElement('root'));
        foreach (['target' => $value, 'other' => 'unrelated'] as $id => $contents) {
            $item = $document->createElement('item');
            $item->setAttribute('id', $id);
            $item->setAttribute('data-value', $contents);
            $item->setAttribute('lang', $contents);
            $item->appendChild($document->createTextNode($contents));
            $root->appendChild($item);
        }

        return $document;
    }

    private function assertSelectedId(DOMDocument $document, string $xpath, string $id): void
    {
        $nodes = (new DOMXPath($document))->query($xpath);

        $this->assertNotFalse($nodes);
        $this->assertCount(1, $nodes);
        $this->assertSame($id, $nodes->item(0)->getAttribute('id'));
    }
}
