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

use Berlioz\Form\Collector\FormCollector;
use Berlioz\Form\Exception\CollectorException;
use Berlioz\Form\FormMapping;
use Berlioz\Form\Hydrator\FormHydrator;
use Berlioz\Form\Tests\Fake\Entity\FakeJob;
use Berlioz\Form\Tests\Fake\Entity\FakePerson;
use Berlioz\Form\Tests\Fake\FakeForm;
use Berlioz\Form\Type\Text;

class MappingTest extends AbstractFormTestCase
{
    /////////////////////////
    /// getMapping() (unit) ///
    /////////////////////////

    public function testGetMappingDefaultUsesName(): void
    {
        $form = new FakeForm('test');
        $form->add('last_name', Text::class);

        $mapping = $form['last_name']->getMapping();
        $this->assertInstanceOf(FormMapping::class, $mapping);

        $person = (new FakePerson())->setLastName('Giron');
        $this->assertSame('Giron', $mapping->get($person));
    }

    public function testGetMappingStringRenaming(): void
    {
        $form = new FakeForm('test');
        $form->add('public_name', Text::class, ['mapped' => 'last_name']);

        $mapping = $form['public_name']->getMapping();
        $this->assertInstanceOf(FormMapping::class, $mapping);

        $person = (new FakePerson())->setLastName('Giron');
        $this->assertSame('Giron', $mapping->get($person));

        $this->assertTrue($mapping->set($person, 'Berlioz'));
        $this->assertSame('Berlioz', $person->getLastName());
    }

    public function testGetMappingFalseReturnsNull(): void
    {
        $form = new FakeForm('test');
        $form->add('nick_name', Text::class, ['mapped' => false]);

        $this->assertNull($form['nick_name']->getMapping());
    }

    public function testGetMappingReturnsCustomInstance(): void
    {
        $custom = new FormMapping(
            get: fn(object $mapped) => 'foo',
            set: fn(object $mapped, mixed $value) => true,
        );

        $form = new FakeForm('test');
        $form->add('whatever', Text::class, ['mapped' => $custom]);

        $this->assertSame($custom, $form['whatever']->getMapping());
    }

    ///////////////////////////////////////
    /// String renaming (hydrate/collect) ///
    ///////////////////////////////////////

    public function testHydrateWithStringRenaming(): void
    {
        $person = new FakePerson();

        $form = new FakeForm('person', $person);
        $form->add('public_name', Text::class, ['mapped' => 'last_name']);
        $form->setSubmitted(true);
        $form->submitValue(['public_name' => 'Giron']);

        (new FormHydrator($form))->hydrate($person);

        $this->assertSame('Giron', $person->getLastName());
    }

    public function testCollectWithStringRenaming(): void
    {
        $person = (new FakePerson())->setLastName('Giron');

        $form = new FakeForm('person', $person);
        $form->add('public_name', Text::class, ['mapped' => 'last_name']);

        $collected = (new FormCollector($form))->collect($person);

        $this->assertSame(['public_name' => 'Giron'], $collected);
    }

    //////////////////////////////////////////
    /// FormMapping custom, nested (cas 1)   ///
    //////////////////////////////////////////

    public function testHydrateWithCustomNestedMapping(): void
    {
        $person = (new FakePerson())->setJob(new FakeJob());

        $mapping = new FormMapping(
            get: fn(FakePerson $p) => $p->getJob()?->getTitle(),
            set: fn(FakePerson $p, mixed $value) => (bool)$p->getJob()?->setTitle($value),
        );

        $form = new FakeForm('person', $person);
        $form->add('job_title', Text::class, ['mapped' => $mapping]);
        $form->setSubmitted(true);
        $form->submitValue(['job_title' => 'Developer']);

        (new FormHydrator($form))->hydrate($person);

        $this->assertSame('Developer', $person->getJob()->getTitle());
    }

    public function testCollectWithCustomNestedMapping(): void
    {
        $person = (new FakePerson())->setJob((new FakeJob())->setTitle('Developer'));

        $mapping = new FormMapping(
            get: fn(FakePerson $p) => $p->getJob()?->getTitle(),
            set: fn(FakePerson $p, mixed $value) => (bool)$p->getJob()?->setTitle($value),
        );

        $form = new FakeForm('person', $person);
        $form->add('job_title', Text::class, ['mapped' => $mapping]);

        $collected = (new FormCollector($form))->collect($person);

        $this->assertSame(['job_title' => 'Developer'], $collected);
    }

    public function testCollectWithNullIntermediateLink(): void
    {
        // No job set: the ?-> operator inside the closure must yield null without error.
        $person = new FakePerson();

        $mapping = new FormMapping(
            get: fn(FakePerson $p) => $p->getJob()?->getTitle(),
            set: fn(FakePerson $p, mixed $value) => (bool)$p->getJob()?->setTitle($value),
        );

        $form = new FakeForm('person', $person);
        $form->add('job_title', Text::class, ['mapped' => $mapping]);

        $collected = (new FormCollector($form))->collect($person);

        $this->assertSame(['job_title' => null], $collected);
    }

    ////////////////////////////////////////////
    /// FormMapping custom, transform on fly   ///
    ////////////////////////////////////////////

    public function testCustomMappingTransformsValue(): void
    {
        $person = new FakePerson();

        // Store upper-cased, read lower-cased.
        $mapping = new FormMapping(
            get: fn(FakePerson $p) => strtolower((string)$p->getLastName()),
            set: fn(FakePerson $p, mixed $value) => (bool)$p->setLastName(strtoupper((string)$value)),
        );

        $form = new FakeForm('person', $person);
        $form->add('name', Text::class, ['mapped' => $mapping]);
        $form->setSubmitted(true);
        $form->submitValue(['name' => 'Giron']);

        (new FormHydrator($form))->hydrate($person);
        $this->assertSame('GIRON', $person->getLastName());

        $collected = (new FormCollector($form))->collect($person);
        $this->assertSame(['name' => 'giron'], $collected);
    }

    /////////////////////////////////
    /// Error detection (fabrics)   ///
    /////////////////////////////////

    public function testCollectUnknownPropertyThrows(): void
    {
        $this->expectException(CollectorException::class);

        $person = new FakePerson();

        $form = new FakeForm('person', $person);
        $form->add('unknown', Text::class);

        (new FormCollector($form))->collect($person);
    }

    ////////////////////////////////////////
    /// getMapped() (render) consistency   ///
    ////////////////////////////////////////

    public function testGetMappedUsesMappingForRender(): void
    {
        $person = (new FakePerson())->setLastName('Giron');

        $form = new FakeForm('person', $person);
        $form->add('public_name', Text::class, ['mapped' => 'last_name']);

        $this->assertSame('Giron', $form['public_name']->getMapped());
    }
}
