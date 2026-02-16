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

namespace Berlioz\EventManager\Tests\Subscriber;

use Berlioz\EventManager\Event\CustomEvent;
use Berlioz\EventManager\Provider\ListenerProvider;
use Berlioz\EventManager\Tests\Event\TestEvent;
use PHPUnit\Framework\TestCase;
use stdClass;

class AbstractSubscriberTest extends TestCase
{
    public function testListens()
    {
        $subscriber = new FakeSubscriber();

        $this->assertTrue($subscriber->listens(new CustomEvent('event.name')));
        $this->assertTrue($subscriber->listens('event.name'));
        $this->assertTrue($subscriber->listens(new stdClass()));

        $this->assertFalse($subscriber->listens(new CustomEvent('event.name2')));
        $this->assertFalse(
            $subscriber->listens(
                new class {
                }
            )
        );
    }

    public function testSubscribe()
    {
        $subscriber = new FakeSubscriber();
        $subscriber->subscribe($provider = new ListenerProvider());

        $result = iterator_to_array($provider->getListenersForEvent(new CustomEvent('test')), false);
        $this->assertCount(0, $result);

        $result = iterator_to_array($provider->getListenersForEvent(new TestEvent('event.name')), false);
        $this->assertCount(2, $result);

        $result = iterator_to_array($provider->getListenersForEvent(new TestEvent('event.test')), false);
        $this->assertCount(1, $result);
    }
}
