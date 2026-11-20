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
use Berlioz\Form\Validator\Constraint\ScalarConstraint;
use Berlioz\Form\Validator\ScalarValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

class ScalarValidatorTest extends TestCase
{
    private function element(mixed $value): ElementInterface
    {
        $element = $this->createMock(ElementInterface::class);
        $element->method('getValue')->willReturn($value);
        $element->method('getName')->willReturn('foo');

        return $element;
    }

    public static function provideAcceptedValues(): array
    {
        return [
            'null' => [null],
            'string' => ['bar'],
            'empty string' => [''],
            'int' => [123],
            'float' => [1.5],
            'bool' => [false],
        ];
    }

    #[DataProvider('provideAcceptedValues')]
    public function testValidate_accepted(mixed $value): void
    {
        $validator = new ScalarValidator();

        $this->assertTrue(ScalarValidator::isAccepted($value));
        $this->assertEmpty($validator->validate($this->element($value)));
    }

    public static function provideRejectedValues(): array
    {
        return [
            'array' => [['bar']],
            'empty array' => [[]],
            'nested array' => [['bar' => ['baz']]],
            'object' => [new stdClass()],
        ];
    }

    #[DataProvider('provideRejectedValues')]
    public function testValidate_rejected(mixed $value): void
    {
        $validator = new ScalarValidator();
        $constraints = $validator->validate($this->element($value));

        $this->assertFalse(ScalarValidator::isAccepted($value));
        $this->assertCount(1, $constraints);
        $this->assertInstanceOf(ScalarConstraint::class, $constraints[0]);
        $this->assertSame(['name' => 'foo'], $constraints[0]->getContext());
        $this->assertSame('The field "foo" has an invalid value.', (string)$constraints[0]);
    }
}
