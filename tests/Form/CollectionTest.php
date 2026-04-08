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

use Berlioz\Form\Collection;
use Berlioz\Form\Tests\Fake\FakeForm;
use Berlioz\Form\Type\Text;
use PHPUnit\Framework\TestCase;

class CollectionTest extends TestCase
{
    public function testSetValueWithMaxElements(): void
    {
        $collection = new Collection(
            [
                'name' => 'collection',
                'min_elements' => 0,
                'max_elements' => 1,
                'prototype' => new Text(),
            ]
        );

        $collection->setValue(
            [
                0 => 'foo',
                1 => 'bar',
            ]
        );

        $this->assertCount(1, $collection);
        $this->assertSame([0 => 'foo'], $collection->getValue());
    }

    public function testSubmitValueWithMaxElements(): void
    {
        $form = new FakeForm('foo');
        $form->add(
            'collection',
            Collection::class,
            [
                'min_elements' => 0,
                'max_elements' => 1,
                'prototype' => new Text(),
            ]
        );

        $form->setSubmitted(true);
        $form->submitValue(
            [
                'collection' => [
                    0 => 'foo',
                    1 => 'bar',
                ],
            ]
        );

        $this->assertCount(1, $form['collection']);
        $this->assertSame([0 => 'foo'], $form['collection']->getValue());
    }
}
