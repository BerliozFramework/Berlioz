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
use Berlioz\EventManager\Provider\SubscriberProvider;
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
}
