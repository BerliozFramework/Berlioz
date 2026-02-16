<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2021 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Berlioz\EventManager\Tests\Event;

use Berlioz\EventManager\Event\CustomEvent;
use PHPUnit\Framework\TestCase;

class CustomEventTest extends TestCase
{
    public function testGetName()
    {
        $event = new CustomEvent('event.name');

        $this->assertEquals('event.name', $event->getName());
    }

    public function testGetData_default()
    {
        $event = new CustomEvent('event.name');

        $this->assertEquals([], $event->getData());
    }

    public function testGetData()
    {
        $event = new CustomEvent('event.name', $data = ['foo' => 'bar', 'baz' => ['qux', 'quux']]);

        $this->assertEquals($data, $event->getData());
    }

    public function testStopPropagation()
    {
        $event = new CustomEvent('event.name');

        $this->assertFalse($event->isPropagationStopped());

        $event->stopPropagation();

        $this->assertTrue($event->isPropagationStopped());
    }
}
