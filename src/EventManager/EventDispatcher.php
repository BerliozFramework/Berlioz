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

namespace Berlioz\EventManager;

use Berlioz\EventManager\Event\CustomEvent;
use Berlioz\EventManager\Event\EventInterface;
use Berlioz\EventManager\Listener\ListenerInterface;
use Berlioz\EventManager\Provider\ListenerProvider;
use Berlioz\EventManager\Provider\ListenerProviderInterface;
use Berlioz\EventManager\Provider\SubscriberProvider;
use Berlioz\EventManager\Subscriber\SubscriberInterface;
use Closure;
use Generator;
use Psr\EventDispatcher as Psr;

/**
 * Class EventDispatcher.
 */
class EventDispatcher implements Psr\EventDispatcherInterface, ListenerProviderInterface
{
    protected ListenerProviderInterface $defaultProvider;
    protected SubscriberProvider $subscriberProvider;
    protected array $providers = [];
    protected array $dispatchers = [];

    public function __construct(
        array $providers = [],
        array $dispatchers = [],
        ?ListenerProviderInterface $defaultProvider = null
    ) {
        $this->defaultProvider = $defaultProvider ?? new ListenerProvider();
        $this->subscriberProvider = new SubscriberProvider($this->defaultProvider);
        $this->addListenerProvider(...$providers);
        $this->addEventDispatcher(...$dispatchers);
    }

    /**
     * Add listener provider.
     *
     * @param Psr\ListenerProviderInterface ...$provider
     */
    public function addListenerProvider(Psr\ListenerProviderInterface ...$provider): void
    {
        array_push($this->providers, ...$provider);
    }

    /**
     * Get listener providers.
     *
     * @return Generator
     */
    protected function getListenerProviders(): Generator
    {
        yield $this->subscriberProvider;
        yield $this->defaultProvider;
        yield from $this->providers;
    }

    /**
     * @inheritDoc
     */
    public function getListenersForEvent(object $event): Generator
    {
        /** @var Psr\ListenerProviderInterface $provider */
        foreach ($this->getListenerProviders() as $provider) {
            yield from $provider->getListenersForEvent($event);
        }
    }

    /**
     * Delegate event dispatcher.
     *
     * Only call if event is not stopped.
     *
     * @param Psr\EventDispatcherInterface ...$dispatcher
     */
    public function addEventDispatcher(Psr\EventDispatcherInterface ...$dispatcher): void
    {
        array_push($this->dispatchers, ...$dispatcher);
    }

    /**
     * @inheritDoc
     */
    public function dispatch(object $event): object
    {
        foreach ($this->getListenersForEvent($event) as $listener) {
            // It's a stoppable event
            if ($event instanceof Psr\StoppableEventInterface) {
                if ($event->isPropagationStopped()) {
                    return $event;
                }
            }

            $result = $listener($event);

            // Stop propagation
            if (false === $result) {
                return $event;
            }

            // Not an object so continue
            if (!is_object($result)) {
                continue;
            }

            // Another instance of event
            if (!$result instanceof $event ||
                ($event instanceof EventInterface &&
                    $event->getName() !== $result->getName())) {
                return $this->dispatch($result);
            }

            $event = $result;
        }

        // Delegate
        array_walk($this->dispatchers, fn(Psr\EventDispatcherInterface $dispatcher) => $dispatcher->dispatch($event));

        return $event;
    }

    /**
     * Trigger event.
     *
     * @param string $name
     * @param array $data
     *
     * @return object
     */
    public function trigger(string $name, array $data = []): object
    {
        return $this->dispatch(new CustomEvent($name, $data));
    }

    /**
     * @inheritDoc
     */
    public function addEventListener(
        object|string $event,
        Closure|array|string $callback,
        int $priority = 0
    ): ListenerInterface {
        return $this->defaultProvider->addEventListener($event, $callback, $priority);
    }

    /**
     * @inheritDoc
     */
    public function addListener(ListenerInterface ...$listener): void
    {
        $this->defaultProvider->addListener(...$listener);
    }

    /**
     * Add subscriber.
     *
     * @param SubscriberInterface ...$subscriber
     */
    public function addSubscriber(SubscriberInterface ...$subscriber): void
    {
        $this->subscriberProvider->addSubscriber(...$subscriber);
    }
}