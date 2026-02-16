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
use Berlioz\EventManager\Provider\ListenerProvider;
use Berlioz\EventManager\Tests\Event\TestEvent;
use Closure;
use PHPUnit\Framework\TestCase;

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
}
