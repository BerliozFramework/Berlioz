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

use Berlioz\EventManager\Listener\Listener;
use Berlioz\EventManager\Provider\ListenerProviderInterface;
use Berlioz\EventManager\Subscriber\AbstractSubscriber;
use Berlioz\EventManager\Tests\Event\TestEvent;
use stdClass;

class FakeSubscriber extends AbstractSubscriber
{
    protected array $listens = [
        'event.name',
        stdClass::class
    ];
    public array $subscribed = [];
    public int $called = 0;

    public function subscribe(ListenerProviderInterface $provider): void
    {
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
        $provider->addEventListener($this->subscribed[] = stdClass::class, [$this, 'callEvent']);
    }

    public function callEvent(object $event): void
    {
        $this->called++;
    }
}