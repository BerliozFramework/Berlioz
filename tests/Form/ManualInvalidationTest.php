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

use Berlioz\Form\Collection;
use Berlioz\Form\Form;
use Berlioz\Form\Group;
use Berlioz\Form\Type\Text;
use Berlioz\Form\Validator\Constraint\BasicConstraint;

class ManualInvalidationTest extends AbstractFormTestCase
{
    private function getForm(): Form
    {
        $address = new Group();
        $address->add('city', Text::class);

        $form = new Form('person');
        $form
            ->add('name', Text::class)
            ->add('address', $address);

        return $form;
    }

    private function getData(): array
    {
        return ['person' => ['name' => 'Ronan', 'address' => ['city' => 'Paris']]];
    }

    public function testInvalid_simpleField(): void
    {
        $field = new Text(['name' => 'name']);
        $constraint = new BasicConstraint(message: 'Name already used');

        $this->assertTrue($field->isValid());

        $field->invalid($constraint);

        $this->assertFalse($field->isValid());
        $this->assertSame([$constraint], $field->getConstraints());
    }

    public function testInvalid_repeatedChecksAreConsistent(): void
    {
        $form = $this->getForm();
        $form->handle($this->getServerRequest($this->getData()));
        $form['name']->invalid(new BasicConstraint());

        $this->assertFalse($form->isValid());
        $this->assertFalse($form->isValid());
        $this->assertFalse($form['name']->isValid());
        $this->assertCount(1, $form['name']->getConstraints());
    }

    public function testInvalid_nestedFieldInvalidatesRootForm(): void
    {
        $form = $this->getForm();
        $form->handle($this->getServerRequest($this->getData()));

        $this->assertTrue($form->isValid());

        $form['address']['city']->invalid(new BasicConstraint(message: 'Unknown city'));

        $this->assertFalse($form->isValid());
        $this->assertFalse($form['address']->isValid());
        $this->assertTrue($form['name']->isValid());
        $this->assertSame([], $form->getConstraints());
        $this->assertCount(1, $form['address']['city']->getConstraints());
    }

    public function testInvalid_rootForm(): void
    {
        $form = $this->getForm();
        $form->handle($this->getServerRequest($this->getData()));
        $form->invalid(new BasicConstraint(message: 'Global error'));

        $this->assertFalse($form->isValid());
        $this->assertTrue($form['name']->isValid());
        $this->assertCount(1, $form->getConstraints());
    }

    public function testResetConstraints_clearsManualConstraintsRecursively(): void
    {
        $form = $this->getForm();
        $form->handle($this->getServerRequest($this->getData()));
        $form->invalid(new BasicConstraint());
        $form['name']->invalid(new BasicConstraint());
        $form['address']['city']->invalid(new BasicConstraint());

        $this->assertFalse($form->isValid());

        $form->resetConstraints();

        $this->assertSame([], $form->getConstraints());
        $this->assertSame([], $form['name']->getConstraints());
        $this->assertSame([], $form['address']['city']->getConstraints());
        $this->assertTrue($form->isValid());
    }

    public function testHandle_clearsManualConstraintsOfPreviousSubmission(): void
    {
        $form = $this->getForm();
        $form->handle($this->getServerRequest($this->getData()));
        $form->invalid(new BasicConstraint());
        $form['address']['city']->invalid(new BasicConstraint());

        $this->assertFalse($form->isValid());

        $form->handle($this->getServerRequest($this->getData()));

        $this->assertTrue($form->isValid());
        $this->assertSame([], $form->getConstraints());
        $this->assertSame([], $form['address']['city']->getConstraints());
    }

    public function testClone_doesNotCopyConstraints(): void
    {
        $group = new Group(['name' => 'address']);
        $group->add('city', Text::class);
        $group->invalid(new BasicConstraint());
        $group['city']->invalid(new BasicConstraint());

        $clone = clone $group;

        $this->assertTrue($clone->isValid());
        $this->assertSame([], $clone->getConstraints());
        $this->assertSame([], $clone['city']->getConstraints());
        $this->assertFalse($group->isValid());
        $this->assertCount(1, $group['city']->getConstraints());
    }

    public function testClone_collectionPrototypeConstraintsAreNotCopied(): void
    {
        $prototype = new Text();
        $prototype->invalid(new BasicConstraint());

        $form = new Form('person');
        $form->add('hobbies', new Collection(['prototype' => $prototype]));
        $form->handle($this->getServerRequest(['person' => ['hobbies' => ['music', 'sport']]]));

        $this->assertCount(2, $form['hobbies']);
        $this->assertTrue($form->isValid());

        foreach ($form['hobbies'] as $hobby) {
            $this->assertSame([], $hobby->getConstraints());
        }
    }
}
