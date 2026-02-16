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

declare(strict_types=1);

namespace Berlioz\EventManager\Provider;

use Berlioz\EventManager\Listener\Listener;
use Berlioz\EventManager\Listener\ListenerInterface;
use Closure;
use Generator;

/**
 * Class AppListenerProvider.
 */
class ListenerProvider implements ListenerProviderInterface
{
    protected array $listeners = [];

    /**
     * @inheritDoc
     */
    public function addEventListener(
        string|object $event,
        Closure|array|string $callback,
        int $priority = 0
    ): ListenerInterface {
        $this->addListener($listener = new Listener($event, $callback, $priority));

        return $listener;
    }

    /**
     * @inheritDoc
     */
    public function addListener(ListenerInterface ...$listener): void
    {
        array_push($this->listeners, ...$listener);
        usort(
            $this->listeners,
            fn(ListenerInterface $l1, ListenerInterface $l2) => $l1->getPriority() <=> $l2->getPriority()
        );
    }

    /**
     * @inheritDoc
     */
    public function getListenersForEvent(object $event): Generator
    {
        /** @var ListenerInterface $listener */
        foreach ($this->listeners as $listener) {
            if ($listener->isListening($event)) {
                yield fn(object $event) => $this->invokeListener($listener, $event);
            }
        }
    }

    /**
     * Invoke listener.
     *
     * @param ListenerInterface $listener
     * @param object $event
     *
     * @return mixed
     */
    protected function invokeListener(ListenerInterface $listener, object $event): mixed
    {
        return ($listener->getCallback())($event);
    }
}