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
use Berlioz\Form\Validator\FormatValidator;
use PHPUnit\Framework\TestCase;

class FormatValidatorTest extends TestCase
{
    private function element(mixed $value): ElementInterface
    {
        $element = $this->createMock(ElementInterface::class);
        $element->method('getValue')->willReturn($value);

        return $element;
    }

    public function testValueMatchesFormat(): void
    {
        $validator = new FormatValidator('#^[0-9]+$#');
        $constraints = $validator->validate($this->element('12345'));

        $this->assertEmpty($constraints);
    }

    public function testValueDoesNotMatchFormat(): void
    {
        $validator = new FormatValidator('#^[0-9]+$#');
        $constraints = $validator->validate($this->element('abc'));

        $this->assertCount(1, $constraints);
    }

    public function testEmptyValueReturnsNoConstraints(): void
    {
        $validator = new FormatValidator('#^[0-9]+$#');
        $constraints = $validator->validate($this->element(''));

        $this->assertEmpty($constraints);
    }

    public function testArrayValueReturnsNoConstraintsWithoutWarning(): void
    {
        // A non-scalar (array) value must not trigger an "Array to string conversion"
        // warning; under failOnWarning this test would fail if the guard is missing.
        $validator = new FormatValidator('#^[0-9]+$#');
        $constraints = $validator->validate($this->element(['1', '2']));

        $this->assertEmpty($constraints);
    }
}
