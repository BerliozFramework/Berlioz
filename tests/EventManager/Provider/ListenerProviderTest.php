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

namespace Berlioz\EventManager\Tests\Provider;

use Berlioz\EventManager\Event\CustomEvent;
use Berlioz\EventManager\Listener\Listener;
use Berlioz\EventManager\Listener\ListenerInterface;
use Berlioz\EventManager\Provider\ListenerProvider;
use Berlioz\EventManager\Tests\Event\TestEvent;
use Closure;
use PHPUnit\Framework\TestCase;
use stdClass;

class ListenerProviderTest extends TestCase
{
    public function getListenerProviderClass(): string
    {
        return ListenerProvider::class;
    }

    public function testAddEventListener()
    {
        $provider = new ($this->getListenerProviderClass())();
        $provider->addEventListener(
            'event.name',
            fn(TestEvent $event) => $event->increaseCounter(),
            10
        );
        $provider->addEventListener(
            'event.test',
            fn(TestEvent $event) => $event->increaseCounter(),
            10
        );

        $result = iterator_to_array($provider->getListenersForEvent(new TestEvent('event.name')), false);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(Closure::class, $result[0]);
    }

    public function testAddListener()
    {
        $provider = new ($this->getListenerProviderClass())();
        $provider->addListener(
            new Listener(
                'event.name',
                fn(TestEvent $event) => $event->increaseCounter(),
                10
            ),
            new Listener(
                'event.test',
                fn(TestEvent $event) => $event->increaseCounter(),
                10
            )
        );

        $result = iterator_to_array($provider->getListenersForEvent(new TestEvent('event.name')), false);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(Closure::class, $result[0]);
    }

    public function testGetListenersForEvent()
    {
        $provider = new ($this->getListenerProviderClass())();
        $provider->addListener(
            new Listener(
                'event.name',
                fn(TestEvent $event) => $event->increaseCounter(),
                10
            ),
            new Listener(
                'event.name',
                fn(TestEvent $event) => $event->increaseCounter(),
                8
            ),
            new Listener(
                'event.test',
                fn(TestEvent $event) => $event->increaseCounter(),
                10
            )
        );

        $result = iterator_to_array($provider->getListenersForEvent(new CustomEvent('test')), false);
        $this->assertCount(0, $result);

        $result = iterator_to_array($provider->getListenersForEvent(new TestEvent('event.name')), false);
        $this->assertCount(2, $result);

        $result = iterator_to_array($provider->getListenersForEvent(new TestEvent('event.test')), false);
        $this->assertCount(1, $result);
    }

    public function testListenersPriorityOrder()
    {
        $executionOrder = [];

        $provider = new ($this->getListenerProviderClass())();
        $provider->addListener(
            new Listener(
                'event.name',
                function () use (&$executionOrder) {
                    $executionOrder[] = 'low';
                },
                ListenerInterface::PRIORITY_LOW,
            ),
            new Listener(
                'event.name',
                function () use (&$executionOrder) {
                    $executionOrder[] = 'high';
                },
                ListenerInterface::PRIORITY_HIGH,
            ),
            new Listener(
                'event.name',
                function () use (&$executionOrder) {
                    $executionOrder[] = 'normal';
                },
                ListenerInterface::PRIORITY_NORMAL,
            ),
        );

        $event = new TestEvent('event.name');
        $listeners = iterator_to_array($provider->getListenersForEvent($event), false);

        foreach ($listeners as $listener) {
            $listener($event);
        }

        $this->assertSame(['high', 'normal', 'low'], $executionOrder);
    }

    public function testAddEventListenerWithObject()
    {
        $called = false;

        $provider = new ($this->getListenerProviderClass())();
        $provider->addEventListener(
            new stdClass(),
            function () use (&$called) {
                $called = true;
            },
        );

        $listeners = iterator_to_array($provider->getListenersForEvent(new stdClass()), false);
        $this->assertCount(1, $listeners);

        $listeners[0](new stdClass());
        $this->assertTrue($called);
    }

    public function testAddEventListenerWithEventInterfaceObject()
    {
        $called = false;

        $provider = new ($this->getListenerProviderClass())();
        $provider->addEventListener(
            new TestEvent('my.event'),
            function () use (&$called) {
                $called = true;
            },
        );

        $listeners = iterator_to_array($provider->getListenersForEvent(new TestEvent('my.event')), false);
        $this->assertCount(1, $listeners);

        $listeners[0](new TestEvent('my.event'));
        $this->assertTrue($called);
    }
}
