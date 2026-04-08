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

namespace Berlioz\Form\Tests;

use Berlioz\Form\Transformer\EnumTransformer;
use Berlioz\Form\Type\Text;
use PHPUnit\Framework\TestCase;

enum FakeBackedEnum: string
{
    case Foo = 'foo';
    case Bar = 'bar';
}

class EnumTransformerTest extends TestCase
{
    public function testFromFormWithScalar(): void
    {
        $transformer = new EnumTransformer(FakeBackedEnum::class);
        $result = $transformer->fromForm('foo', new Text(['name' => 'test']));

        $this->assertSame(FakeBackedEnum::Foo, $result);
    }

    public function testFromFormWithInvalidScalar(): void
    {
        $transformer = new EnumTransformer(FakeBackedEnum::class);
        $result = $transformer->fromForm('baz', new Text(['name' => 'test']));

        $this->assertNull($result);
    }

    public function testFromFormWithArray(): void
    {
        $transformer = new EnumTransformer(FakeBackedEnum::class);
        $result = $transformer->fromForm(['foo', 'baz', 'bar'], new Text(['name' => 'test']));

        $this->assertSame([FakeBackedEnum::Foo, FakeBackedEnum::Bar], array_values($result));
    }
}
