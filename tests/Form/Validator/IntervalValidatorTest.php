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
use Berlioz\Form\Validator\IntervalValidator;
use PHPUnit\Framework\TestCase;

class IntervalValidatorTest extends TestCase
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

    public function testValueBelowMin(): void
    {
        $validator = new IntervalValidator();
        $constraints = $validator->validate($this->element('3', ['min' => '5']));

        $this->assertCount(1, $constraints);
    }

    public function testValueAboveMax(): void
    {
        $validator = new IntervalValidator();
        $constraints = $validator->validate($this->element('9', ['max' => '5']));

        $this->assertCount(1, $constraints);
    }

    public function testValueInInterval(): void
    {
        $validator = new IntervalValidator();
        $constraints = $validator->validate(
            $this->element('5', ['min' => '1', 'max' => '9'])
        );

        $this->assertEmpty($constraints);
    }

    public function testEmptyValueReturnsNoConstraints(): void
    {
        $validator = new IntervalValidator();
        $constraints = $validator->validate($this->element('', ['min' => '5']));

        $this->assertEmpty($constraints);
    }

    public function testArrayValueReturnsNoConstraintsWithoutWarning(): void
    {
        // A non-scalar (array) value must not trigger an "Array to string conversion"
        // warning; under failOnWarning this test would fail if the guard is missing.
        $validator = new IntervalValidator();
        $constraints = $validator->validate(
            $this->element(['1', '2'], ['min' => '1', 'max' => '9'])
        );

        $this->assertEmpty($constraints);
    }
}
