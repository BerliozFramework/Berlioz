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
use Berlioz\EventManager\Provider\ListenerProvider;
use Berlioz\EventManager\Provider\ListenerProviderInterface;
use Berlioz\EventManager\Provider\SubscriberProvider;
use Berlioz\EventManager\Subscriber\AbstractSubscriber;
use Berlioz\EventManager\Tests\Event\TestEvent;
use Berlioz\EventManager\Tests\Subscriber\FakeSubscriber;
use PHPUnit\Framework\TestCase;

class SubscriberProviderTest extends TestCase
{
    public function testAddSubscriber()
    {
        $provider = new FakeSubscriberProvider(new ListenerProvider());
        $provider->addSubscriber($subscriber = new FakeSubscriber());

        $this->assertSame([$subscriber], $provider->getSubscribers());
    }

    public function testGetListenersForEvent()
    {
        $provider = new SubscriberProvider($defaultProvider = new ListenerProvider());
        $provider->addSubscriber(new FakeSubscriber());

        $this->assertEmpty($provider->getListenersForEvent(new CustomEvent('test')));
        $this->assertEmpty($provider->getListenersForEvent(new CustomEvent('test')));

        $result = iterator_to_array($defaultProvider->getListenersForEvent(new CustomEvent('test')), false);
        $this->assertCount(0, $result);

        $this->assertEmpty($provider->getListenersForEvent(new TestEvent('event.name')));
        $this->assertEmpty($provider->getListenersForEvent(new TestEvent('event.name')));

        $result = iterator_to_array($defaultProvider->getListenersForEvent(new TestEvent('event.name')), false);
        $this->assertCount(2, $result);

        $this->assertEmpty($provider->getListenersForEvent(new TestEvent('event.test')));
        $this->assertEmpty($provider->getListenersForEvent(new TestEvent('event.test')));

        $result = iterator_to_array($defaultProvider->getListenersForEvent(new TestEvent('event.test')), false);
        $this->assertCount(1, $result);
    }

    public function testGetListenersForEvent_multipleSubscribersAcrossDispatches()
    {
        $subscriberA = new class extends AbstractSubscriber {
            protected array $listens = ['event.foo'];

            public function subscribe(ListenerProviderInterface $provider): void
            {
                $provider->addEventListener('event.foo', fn() => null);
            }
        };
        $subscriberB = new class extends AbstractSubscriber {
            protected array $listens = ['event.bar'];

            public function subscribe(ListenerProviderInterface $provider): void
            {
                $provider->addEventListener('event.bar', fn() => null);
            }
        };
        $subscriberC = new class extends AbstractSubscriber {
            protected array $listens = ['event.baz'];

            public function subscribe(ListenerProviderInterface $provider): void
            {
                $provider->addEventListener('event.baz', fn() => null);
            }
        };

        $provider = new FakeSubscriberProvider(new ListenerProvider());
        $provider->addSubscriber($subscriberA, $subscriberB, $subscriberC);

        // First dispatch: A is matched and moved to subscribed
        $provider->getListenersForEvent(new CustomEvent('event.foo'));
        $this->assertSame([$subscriberA], $provider->getSubscribed());
        $this->assertCount(2, $provider->getSubscribers());

        // Second dispatch: B is matched and moved to subscribed
        $provider->getListenersForEvent(new CustomEvent('event.bar'));
        $this->assertSame([$subscriberA, $subscriberB], $provider->getSubscribed());
        $this->assertCount(1, $provider->getSubscribers());

        // Third dispatch: C is matched and moved to subscribed
        $provider->getListenersForEvent(new CustomEvent('event.baz'));
        $this->assertSame([$subscriberA, $subscriberB, $subscriberC], $provider->getSubscribed());
        $this->assertCount(0, $provider->getSubscribers());
    }
}
