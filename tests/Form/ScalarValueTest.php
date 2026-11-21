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

namespace Berlioz\Form\Tests;

use Berlioz\Form\Form;
use Berlioz\Form\Tests\Fake\Entity\FakePerson;
use Berlioz\Form\Tests\Fake\FakeType;
use Berlioz\Form\Type\Choice;
use Berlioz\Form\Type\Email;
use Berlioz\Form\Type\File;
use Berlioz\Form\Type\Hidden;
use Berlioz\Form\Type\Number;
use Berlioz\Form\Type\Text;
use Berlioz\Form\Validator\Constraint\ScalarConstraint;
use Berlioz\Form\Validator\ScalarValidator;
use Closure;
use PHPUnit\Framework\Attributes\DataProvider;

class ScalarValueTest extends AbstractFormTestCase
{
    /**
     * Run callback and collect user deprecations.
     *
     * @param Closure $callback
     * @param array $deprecations
     *
     * @return mixed
     */
    private function withDeprecations(Closure $callback, ?array &$deprecations): mixed
    {
        $deprecations = [];
        set_error_handler(static function (int $severity, string $message) use (&$deprecations): bool {
            if (E_USER_DEPRECATED !== $severity) {
                return false;
            }

            $deprecations[] = $message;

            return true;
        });

        try {
            return $callback();
        } finally {
            restore_error_handler();
        }
    }

    public static function provideSimpleTypes(): array
    {
        return [
            'text' => [Text::class],
            'hidden' => [Hidden::class],
            'number' => [Number::class],
            'email' => [Email::class],
            'fake' => [FakeType::class],
        ];
    }

    #[DataProvider('provideSimpleTypes')]
    public function testBuild_defaultAddsScalarValidator(string $class): void
    {
        $type = new $class(['name' => 'foo']);
        $this->withDeprecations(fn() => $type->build(), $deprecations);

        $this->assertTrue($type->hasValidator(ScalarValidator::class));
        $this->assertSame([], $deprecations);
    }

    public function testBuild_scalarTrueIsDeprecated(): void
    {
        $type = new Text(['name' => 'foo', 'scalar' => true]);
        $this->withDeprecations(fn() => $type->build(), $deprecations);

        $this->assertTrue($type->hasValidator(ScalarValidator::class));
        $this->assertCount(1, $deprecations);
        $this->assertStringContainsString('"scalar" option of form type "foo" is deprecated', $deprecations[0]);
        $this->assertStringContainsString('remove the option', $deprecations[0]);
    }

    public function testBuild_scalarFalseIsDeprecated(): void
    {
        $type = new Text(['name' => 'foo', 'scalar' => false]);
        $this->withDeprecations(fn() => $type->build(), $deprecations);

        $this->assertFalse($type->hasValidator(ScalarValidator::class));
        $this->assertCount(1, $deprecations);
        $this->assertStringContainsString('"scalar" option of form type "foo" is deprecated', $deprecations[0]);
        $this->assertStringContainsString('use a Collection or a RawType', $deprecations[0]);
    }

    public static function provideNonScalarTypes(): array
    {
        return [
            'file' => [File::class, []],
            'choice' => [Choice::class, ['choices' => ['A' => 'a', 'B' => 'b']]],
        ];
    }

    #[DataProvider('provideNonScalarTypes')]
    public function testBuild_nonScalarTypesHaveNoScalarValidator(string $class, array $options): void
    {
        $type = new $class(['name' => 'foo', ...$options]);
        $this->withDeprecations(fn() => $type->build(), $deprecations);

        $this->assertFalse($type->hasValidator(ScalarValidator::class));
        $this->assertSame([], $deprecations);
    }

    public function testHandle_nonScalarValueIsRejectedAndNotHydrated(): void
    {
        $entity = new FakePerson();
        $entity->setLastName('Giron');

        $form = new Form('person', $entity);
        $form
            ->add('last_name', Text::class)
            ->add('first_name', Text::class);

        $form->handle(
            $this->getServerRequest(
                [
                    'person' => [
                        'last_name' => ['Giron', 'Other'],
                        'first_name' => 'Ronan',
                    ],
                ]
            )
        );

        $this->assertTrue($form->isSubmitted());
        $this->assertFalse($form->isValid());
        $this->assertSame('Giron', $entity->getLastName());
        $this->assertSame('Ronan', $entity->getFirstName());

        $constraints = $form['last_name']->getConstraints();
        $this->assertCount(1, $constraints);
        $this->assertInstanceOf(ScalarConstraint::class, $constraints[0]);
        $this->assertTrue($form['first_name']->isValid());
    }

    public function testHandle_scalarFalseKeepsLegacyBehavior(): void
    {
        $form = new Form('foo');
        $form->add('tags', Text::class, ['scalar' => false]);

        $this->withDeprecations(
            fn() => $form->handle($this->getServerRequest(['foo' => ['tags' => ['a', 'b']]])),
            $deprecations,
        );

        $this->assertCount(1, $deprecations);
        $this->assertTrue($form->isValid());
        $this->assertSame(['tags' => ['a', 'b']], $form->getValue());
    }
}
