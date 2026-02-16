<?php
/**
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2017 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Berlioz\FlashBag\Tests;

use Berlioz\FlashBag\FlashBag;
use PHPUnit\Framework\TestCase;

class FlashBagTest extends TestCase
{
    public function testCount()
    {
        $flashBag = new FlashBag;
        $flashBag
            ->add('success', 'Test success message 1')
            ->add('success', 'Test success message 2')
            ->add('success', 'Test success message 3')
            ->add('success', 'Test success message 4')
            ->add('warning', 'Test warning message 1')
            ->add('warning', 'Test warning message 2')
            ->add('error', 'Test error message');
        $this->assertCount(7, $flashBag);
        $flashBag->clear();
    }

    public function testClear()
    {
        $flashBag = new FlashBag;
        $flashBag
            ->add('success', 'Test success message')
            ->add('warning', 'Test warning message')
            ->add('error', 'Test error message');
        $this->assertCount(3, $flashBag);
        $flashBag->clear();
        $this->assertCount(0, $flashBag);
    }

    public function testGet()
    {
        $flashBag = new FlashBag;
        $flashBag
            ->add('success', 'Test success message')
            ->add('warning', 'Test warning message')
            ->add('error', 'Test error message');
        $this->assertCount(3, $flashBag);
        $this->assertEquals(['Test success message'], $flashBag->get('success'));
        $this->assertEquals([], $flashBag->get('success'));
        $this->assertCount(2, $flashBag);
        $flashBag->clear();
    }

    public function testAdd()
    {
        $flashBag = new FlashBag;
        $flashBag->add('success', 'Test success message');
        $this->assertEquals(['Test success message'], $flashBag->get('success'));
        $flashBag->add('warning', 'Test %s message', 'warning');
        $this->assertEquals(['Test warning message'], $flashBag->get('warning'));
    }

    public function testAll()
    {
        $flashBag = new FlashBag;
        $flashBag->add('success', 'Test success message')
            ->add('warning', 'Test warning message 1')
            ->add('warning', 'Test warning message 2')
            ->add('error', 'Test error message');
        $this->assertCount(4, $flashBag);
        $this->assertEquals(
            [
                'success' => ['Test success message'],
                'warning' => [
                    'Test warning message 1',
                    'Test warning message 2'
                ],
                'error' => ['Test error message']
            ],
            $flashBag->all()
        );
        $this->assertCount(0, $flashBag);
    }
}
