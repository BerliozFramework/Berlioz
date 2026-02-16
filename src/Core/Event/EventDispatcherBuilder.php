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

namespace Berlioz\Core\Event;

use Berlioz\Config\Exception\ConfigException;
use Berlioz\Core\Core;
use Berlioz\Core\Event\Provider\DefaultListenerProvider;
use Berlioz\Core\Event\Subscriber\AppSubscriber;
use Berlioz\Core\Exception\BerliozException;
use Berlioz\Core\Exception\ConfigException as BConfigException;
use Berlioz\ServiceContainer\Exception\ContainerException;

/**
 * Class EventDispatcherBuilder.
 */
class EventDispatcherBuilder
{
    protected EventDispatcher $eventDispatcher;

    /**
     * EventDispatcherBuilder constructor.
     *
     * @param Core $core
     */
    public function __construct(protected Core $core)
    {
        $this->reset();
    }

    /**
     * Reset.
     */
    public function reset(): void
    {
        $listenerProvider = new DefaultListenerProvider($this->core->getContainer());
        $this->eventDispatcher = new EventDispatcher($this->core->getDebug(), $listenerProvider);
    }

    /**
     * Get event dispatcher.
     *
     * @return EventDispatcher
     */
    public function getEventDispatcher(): EventDispatcher
    {
        return $this->eventDispatcher;
    }

    /**
     * Add default subscribers.
     *
     * @throws BerliozException
     */
    public function addDefaultSubscribers(): void
    {
        $this->eventDispatcher->addSubscriber(new AppSubscriber($this->core->getConfig()));
    }

    /**
     * Add subscribers from config.
     *
     * @throws BerliozException
     * @throws ContainerException
     */
    public function addSubscribersFromConfig(): void
    {
        try {
            foreach ((array)$this->core->getConfig()->get('events.subscribers', []) as $subscriber) {
                // Not a class
                if (!is_string($subscriber)) {
                    throw BConfigException::subscribersConfig();
                }

                $this->eventDispatcher->addSubscriber($this->core->getContainer()->call($subscriber));
            }
        } catch (ConfigException $exception) {
            throw BConfigException::subscribersConfig($exception);
        }
    }
}