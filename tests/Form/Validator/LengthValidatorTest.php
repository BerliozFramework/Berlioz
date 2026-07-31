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

namespace Berlioz\Form\Tests\Validator;

use Berlioz\Form\Element\ElementInterface;
use Berlioz\Form\Validator\LengthValidator;
use PHPUnit\Framework\TestCase;

class LengthValidatorTest extends TestCase
{
    private function element(mixed $value, array $attributes = []): ElementInterface
    {
        $element = $this->createMock(ElementInterface::class);
        $element->method('getValue')->willReturn($value);
        $element->method('getOption')
            ->with('attributes', [])
            ->willReturn($attributes);

        return $element;
    }

    public function testValueTooShort(): void
    {
        $validator = new LengthValidator();
        $constraints = $validator->validate($this->element('ab', ['minlength' => 3]));

        $this->assertCount(1, $constraints);
    }

    public function testValueTooLong(): void
    {
        $validator = new LengthValidator();
        $constraints = $validator->validate($this->element('abcdef', ['maxlength' => 3]));

        $this->assertCount(1, $constraints);
    }

    public function testValidLength(): void
    {
        $validator = new LengthValidator();
        $constraints = $validator->validate(
            $this->element('abc', ['minlength' => 2, 'maxlength' => 4])
        );

        $this->assertEmpty($constraints);
    }

    public function testEmptyValueReturnsNoConstraints(): void
    {
        $validator = new LengthValidator();
        $constraints = $validator->validate($this->element('', ['minlength' => 3]));

        $this->assertEmpty($constraints);
    }

    public function testNullValueReturnsNoConstraints(): void
    {
        $validator = new LengthValidator();
        $constraints = $validator->validate($this->element(null, ['minlength' => 3]));

        $this->assertEmpty($constraints);
    }

    public function testArrayValueReturnsNoConstraintsWithoutWarning(): void
    {
        // A non-scalar (array) value must not trigger an "Array to string conversion"
        // warning; under failOnWarning this test would fail if the guard is missing.
        $validator = new LengthValidator();
        $constraints = $validator->validate(
            $this->element(['a', 'b'], ['minlength' => 3, 'maxlength' => 10])
        );

        $this->assertEmpty($constraints);
    }
}
