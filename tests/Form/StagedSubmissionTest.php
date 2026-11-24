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

use ArrayObject;
use Berlioz\Form\Collection;
use Berlioz\Form\Element\ElementInterface;
use Berlioz\Form\Exception\FormException;
use Berlioz\Form\Form;
use Berlioz\Form\FormMapping;
use Berlioz\Form\Group;
use Berlioz\Form\Tests\Fake\Entity\FakeAddress;
use Berlioz\Form\Tests\Fake\Entity\FakeJob;
use Berlioz\Form\Tests\Fake\Entity\FakePerson;
use Berlioz\Form\Transformer\CallbackTransformer;
use Berlioz\Form\Type\Text;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

class StagedSubmissionTest extends AbstractFormTestCase
{
    /** @var array<string, array> */
    private array $callbacks = [];

    protected function setUp(): void
    {
        $this->callbacks = ['add' => [], 'remove' => []];
    }

    private function getPerson(): FakePerson
    {
        $person = new FakePerson();
        $person
            ->setLastName('Giron')
            ->setFirstName('Ronan')
            ->setAddresses([
                (new FakeAddress())->setCity('Paris'),
                (new FakeAddress())->setCity('Marseille'),
            ])
            ->setHobbies(new ArrayObject(['pony', 'tennis']))
            ->setJob(
                (new FakeJob())
                    ->setTitle('Developer')
                    ->setAddress((new FakeAddress())->setCity('Bordeaux'))
            );

        return $person;
    }

    private function getForm(FakePerson $person, array $firstNameOptions = []): Form
    {
        $address = new Group(['data_type' => FakeAddress::class]);
        $address->add('city', Text::class);

        $job = new Group(['data_type' => FakeJob::class]);
        $job
            ->add('title', Text::class)
            ->add('address', clone $address);

        $form = new Form('person', $person);
        $form
            ->add('last_name', Text::class)
            ->add('first_name', Text::class, $firstNameOptions)
            ->add('job', $job)
            ->add(
                'addresses',
                new Collection([
                    'prototype' => clone $address,
                    'callbacks' => [
                        'add' => function (Collection $collection, ElementInterface $element): void {
                            $this->callbacks['add'][] = $element->getValue();
                        },
                        'remove' => function (Collection $collection, ElementInterface $element): void {
                            $this->callbacks['remove'][] = $element->getValue();
                        },
                    ],
                ])
            )
            ->add(
                'hobbies',
                new Collection([
                    'data_type' => ArrayObject::class,
                    'prototype' => new Text(),
                ])
            );

        $form['last_name']->addValidator(
            fn(ElementInterface $element): bool|string => 'invalid' !== $element->getValue() ?: 'Invalid name'
        );

        return $form;
    }

    private function getSubmission(string $lastName = 'Doe'): array
    {
        return [
            'person' => [
                'last_name' => $lastName,
                'first_name' => 'John',
                'job' => [
                    'title' => 'Manager',
                    'address' => ['city' => 'Lille'],
                ],
                'addresses' => [
                    0 => ['city' => 'Lyon'],
                    2 => ['city' => 'Nantes'],
                ],
                'hobbies' => ['pony', 'chess'],
            ],
        ];
    }

    private function snapshot(FakePerson $person): array
    {
        return [
            'serialized' => serialize($person),
            'job' => $person->getJob(),
            'job_address' => $person->getJob()->getAddress(),
            'addresses' => $person->getAddresses(),
            'hobbies' => $person->getHobbies(),
        ];
    }

    private function assertGraphUnchanged(array $expected, FakePerson $person): void
    {
        $this->assertSame($expected['serialized'], serialize($person));
        $this->assertSame($expected['job'], $person->getJob());
        $this->assertSame($expected['job_address'], $person->getJob()->getAddress());
        $this->assertSame($expected['addresses'], $person->getAddresses());
        $this->assertSame($expected['hobbies'], $person->getHobbies());
    }

    public function testHandle_stagedInvalidSubmissionLeavesGraphUnchanged(): void
    {
        $person = $this->getPerson();
        $snapshot = $this->snapshot($person);

        $form = $this->getForm($person);
        $form->handle($this->getServerRequest($this->getSubmission('invalid')), apply: false);

        $this->assertTrue($form->isSubmitted());
        $this->assertFalse($form->isValid());
        $this->assertGraphUnchanged($snapshot, $person);
        $this->assertSame(['add' => [], 'remove' => []], $this->callbacks);
    }

    public function testApply_validSubmission(): void
    {
        $person = $this->getPerson();
        $job = $person->getJob();
        $hobbies = $person->getHobbies();
        [$address0] = $person->getAddresses();

        $form = $this->getForm($person);
        $form->handle($this->getServerRequest($this->getSubmission()), apply: false);

        $this->assertTrue($form->isValid());
        $this->assertSame('Giron', $person->getLastName());
        $this->assertSame(['add' => [], 'remove' => []], $this->callbacks);

        $form->apply();

        $this->assertSame('Doe', $person->getLastName());
        $this->assertSame('John', $person->getFirstName());
        $this->assertSame($job, $person->getJob());
        $this->assertSame('Manager', $job->getTitle());
        $this->assertSame('Lille', $job->getAddress()->getCity());
        $this->assertSame([0, 2], array_keys($person->getAddresses()));
        $this->assertSame($address0, $person->getAddresses()[0]);
        $this->assertSame('Lyon', $person->getAddresses()[0]->getCity());
        $this->assertInstanceOf(FakeAddress::class, $person->getAddresses()[2]);
        $this->assertSame('Nantes', $person->getAddresses()[2]->getCity());
        $this->assertSame($hobbies, $person->getHobbies());
        $this->assertSame(['pony', 'chess'], $hobbies->getArrayCopy());
        $this->assertSame(
            [
                'add' => [['city' => 'Nantes']],
                'remove' => [['city' => 'Marseille']],
            ],
            $this->callbacks
        );
    }

    public function testApply_createsMissingNestedObjects(): void
    {
        $person = new FakePerson();

        $form = $this->getForm($person);
        $form->handle($this->getServerRequest($this->getSubmission('invalid')), apply: false);

        $this->assertFalse($form->isValid());
        $this->assertNull($person->getJob());
        $this->assertNull($person->getHobbies());
        $this->assertSame([], $person->getAddresses());

        $form->apply();

        $this->assertInstanceOf(FakeJob::class, $person->getJob());
        $this->assertSame('Lille', $person->getJob()->getAddress()->getCity());
        $this->assertSame(['pony', 'chess'], $person->getHobbies()->getArrayCopy());
        $this->assertSame('Nantes', $person->getAddresses()[2]->getCity());
    }

    public static function provideApplyModes(): array
    {
        return [
            'default' => [true],
            'staged' => [false],
        ];
    }

    #[DataProvider('provideApplyModes')]
    public function testApply_conversionFailureLeavesGraphUnchanged(bool $apply): void
    {
        $person = $this->getPerson();
        $snapshot = $this->snapshot($person);

        $form = $this->getForm(
            $person,
            [
                'transformer' => new CallbackTransformer(
                    fromFormCallback: fn(mixed $data): never => throw new RuntimeException('Conversion failed'),
                ),
            ]
        );

        try {
            $form->handle($this->getServerRequest($this->getSubmission()), apply: $apply);
            $form->apply();
            $this->fail('Conversion failure expected');
        } catch (RuntimeException $exception) {
            $this->assertSame('Conversion failed', $exception->getMessage());
        }

        $this->assertGraphUnchanged($snapshot, $person);

        if (false === $apply) {
            $this->assertSame(['add' => [], 'remove' => []], $this->callbacks);
        }
    }

    public function testApply_writesOnce(): void
    {
        $person = $this->getPerson();
        $writes = 0;

        $form = $this->getForm(
            $person,
            [
                'mapped' => new FormMapping(
                    get: fn(FakePerson $mapped): ?string => $mapped->getFirstName(),
                    set: function (FakePerson $mapped, ?string $value) use (&$writes): bool {
                        $writes++;
                        $mapped->setFirstName($value);
                        return true;
                    },
                ),
            ]
        );
        $form->handle($this->getServerRequest($this->getSubmission()), apply: false);

        $this->assertSame(0, $writes);

        $form->apply();

        $this->assertSame(1, $writes);
        $this->assertSame('John', $person->getFirstName());

        try {
            $form->apply();
            $this->fail('Second apply expected to fail');
        } catch (FormException $exception) {
            $this->assertSame('Form submission already applied', $exception->getMessage());
        }

        $this->assertSame(1, $writes);
        $this->assertCount(1, $this->callbacks['add']);
        $this->assertCount(1, $this->callbacks['remove']);
    }

    public function testApply_notSubmitted(): void
    {
        $form = $this->getForm($this->getPerson());

        $this->expectException(FormException::class);
        $this->expectExceptionMessage('Unable to apply a form not submitted');

        $form->apply();
    }

    public function testHandle_defaultAppliesImmediately(): void
    {
        $person = $this->getPerson();

        $form = $this->getForm($person);
        $form->handle($this->getServerRequest($this->getSubmission('invalid')));

        $this->assertFalse($form->isValid());
        $this->assertSame('invalid', $person->getLastName());
        $this->assertSame('Lille', $person->getJob()->getAddress()->getCity());
        $this->assertSame(
            [
                'add' => [['city' => 'Nantes']],
                'remove' => [['city' => 'Marseille']],
            ],
            $this->callbacks
        );

        $this->expectException(FormException::class);
        $this->expectExceptionMessage('Form submission already applied');

        $form->apply();
    }
}
