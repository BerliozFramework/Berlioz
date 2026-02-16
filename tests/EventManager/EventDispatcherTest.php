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

namespace Berlioz\EventManager\Tests;

use Berlioz\EventManager\EventDispatcher;
use Berlioz\EventManager\Provider\ListenerProvider;
use Berlioz\EventManager\Tests\Event\TestEvent;
use Berlioz\EventManager\Tests\Provider\ListenerProviderTest;
use Berlioz\EventManager\Tests\Subscriber\FakeSubscriber;
use stdClass;

class EventDispatcherTest extends ListenerProviderTest
{
    public function getListenerProviderClass(): string
    {
        return EventDispatcher::class;
    }

    public function test__construct()
    {
        $dispatcher = new FakeEventDispatcher();

        $this->assertCount(0, $dispatcher->getDispatchers());
        $this->assertCount(0, $dispatcher->getProviders());
    }

    public function test__construct_withProviders()
    {
        $dispatcher = new FakeEventDispatcher(
            [
                $provider = new class extends ListenerProvider {
                },
                $provider2 = new class extends ListenerProvider {
                }
            ]
        );

        $this->assertCount(0, $dispatcher->getDispatchers());
        $this->assertCount(2, $dispatcher->getProviders());
        $this->assertSame([$provider, $provider2], $dispatcher->getProviders());
    }

    public function test__construct_withDispatcher()
    {
        $dispatcher = new FakeEventDispatcher(
            dispatchers: [
                $dispatcher2 = new class extends EventDispatcher {
                }
            ]
        );

        $this->assertCount(0, $dispatcher->getProviders());
        $this->assertCount(1, $dispatcher->getDispatchers());
        $this->assertSame([$dispatcher2], $dispatcher->getDispatchers());
    }

    public function testAddListenerProvider()
    {
        $dispatcher = new FakeEventDispatcher();

        $this->assertCount(0, $dispatcher->getProviders());

        $dispatcher->addListenerProvider($provider = new ListenerProvider());

        $this->assertCount(1, $dispatcher->getProviders());
        $this->assertSame($provider, $dispatcher->getProviders()[0]);
    }

    public function testGetListenersForEvent()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListenerProvider($provider = new ListenerProvider(), $provider2 = new ListenerProvider());
        $provider->addEventListener(stdClass::class, fn($event) => $event);
        $provider->addEventListener(TestEvent::class, fn($event) => $event);
        $provider2->addEventListener(stdClass::class, fn($event) => $event);

        $result = iterator_to_array($dispatcher->getListenersForEvent(new stdClass()), false);
        $this->assertCount(2, $result);
    }

    public function testAddEventDispatcher()
    {
        $dispatcher = new FakeEventDispatcher();

        $this->assertCount(0, $dispatcher->getDispatchers());

        $dispatcher->addEventDispatcher($dispatcher2 = new EventDispatcher());

        $this->assertCount(1, $dispatcher->getDispatchers());
        $this->assertSame($dispatcher2, $dispatcher->getDispatchers()[0]);
    }

    public function testAddSubscriber()
    {
        $dispatcher = new FakeEventDispatcher();

        $this->assertCount(0, $dispatcher->getSubscriberProvider()->getSubscribers());

        $dispatcher->addSubscriber($subscriber = new FakeSubscriber());

        $this->assertCount(1, $dispatcher->getSubscriberProvider()->getSubscribers());
        $this->assertSame($subscriber, $dispatcher->getSubscriberProvider()->getSubscribers()[0]);
    }

    public function testDispatch()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListenerProvider($provider = new ListenerProvider(), $provider2 = new ListenerProvider());
        $provider->addEventListener('event.name', fn(TestEvent $event) => $event->increaseCounter());
        $provider->addEventListener('event.test', fn(TestEvent $event) => $event->increaseCounter());
        $provider2->addEventListener(stdClass::class, fn($event) => $event);
        $provider2->addEventListener('event.name', fn(TestEvent $event) => $event->increaseCounter());

        $event = new TestEvent('event.name');
        $dispatcher->dispatch($event);

        $this->assertEquals(2, $event->getCounter());
    }

    public function testDispatchWithStoppedPropagation()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListenerProvider($provider = new ListenerProvider(), $provider2 = new ListenerProvider());
        $provider->addEventListener('event.name', fn(TestEvent $event) => $event->increaseCounter());
        $provider->addEventListener(
            'event.name',
            function (TestEvent $event) {
                $event->stopPropagation();
                return $event;
            }
        );
        $provider->addEventListener('event.test', fn(TestEvent $event) => $event->increaseCounter());
        $provider2->addEventListener(stdClass::class, fn($event) => $event);
        $provider2->addEventListener('event.name', fn(TestEvent $event) => $event->increaseCounter());

        $event = new TestEvent('event.name');
        $dispatcher->dispatch($event);

        $this->assertEquals(1, $event->getCounter());
    }

    public function testDispatchWithFalseEventResult()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addEventListener('event.name', fn(TestEvent $event) => $event->increaseCounter());
        $dispatcher->addEventListener(
            'event.name',
            function (TestEvent $event) {
                return false;
            }
        );
        $dispatcher->addEventListener('event.name', fn(TestEvent $event) => $event->increaseCounter());

        $event = new TestEvent('event.name');
        $dispatcher->dispatch($event);

        $this->assertEquals(1, $event->getCounter());
    }

    public function testDispatchWithNonObjectEventResult()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addEventListener('event.name', fn(TestEvent $event) => $event->increaseCounter());
        $dispatcher->addEventListener(
            'event.name',
            function (TestEvent $event) {
                return 'OK';
            }
        );
        $dispatcher->addEventListener('event.name', fn(TestEvent $event) => $event->increaseCounter());

        $event = new TestEvent('event.name');
        $dispatcher->dispatch($event);

        $this->assertEquals(2, $event->getCounter());
    }

    public function testDispatchWithAnotherEventResult()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addEventListener('event.name', fn(TestEvent $event) => $event->increaseCounter());
        $dispatcher->addEventListener(
            'event.name',
            function (TestEvent $event) {
                return new TestEvent('event.test');
            }
        );
        $dispatcher->addEventListener('event.test', fn(TestEvent $event) => $event->increaseCounter());
        $dispatcher->addEventListener('event.test', fn(TestEvent $event) => $event->increaseCounter());

        $event = new TestEvent('event.name');
        $resultEvent = $dispatcher->dispatch($event);

        $this->assertEquals(1, $event->getCounter());
        $this->assertEquals(2, $resultEvent->getCounter());
    }

    public function testTrigger()
    {
        $triggered = false;
        $dispatcher = new EventDispatcher();
        $dispatcher->addEventListener(
            'event.name',
            function ($event) use (&$triggered) {
                $triggered = true;
                return $event;
            }
        );
        $dispatcher->addEventListener(
            'event.test',
            function ($event) use (&$triggered) {
                $triggered = false;
                return $event;
            }
        );

        $dispatcher->trigger('event.name');

        $this->assertTrue($triggered);
    }
}
