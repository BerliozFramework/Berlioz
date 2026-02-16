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

namespace Berlioz\Core\Event\Subscriber;

use Berlioz\Config\Config;
use Berlioz\Config\Exception\ConfigException;
use Berlioz\Core\Exception\BerliozException;
use Berlioz\Core\Exception\ConfigException as BConfigException;
use Berlioz\EventManager\Listener\ListenerInterface;
use Berlioz\EventManager\Provider\ListenerProviderInterface;
use Berlioz\EventManager\Subscriber\AbstractSubscriber;

/**
 * Class AppSubscriber.
 */
class AppSubscriber extends AbstractSubscriber
{
    /**
     * AppSubscriber constructor.
     *
     * @param Config $config
     *
     * @throws BerliozException
     */
    public function __construct(protected Config $config)
    {
        try {
            foreach ((array)$this->config->get('events.listeners', []) as $event => $listener) {
                if (is_string($listener)) {
                    $this->listens[] = $event;
                    continue;
                }

                if (is_array($listener)) {
                    array_push(
                        $this->listens,
                        ...(array)($listener['event'] ?? throw BConfigException::listenersConfig())
                    );
                    continue;
                }

                throw BConfigException::listenersConfig();
            }
        } catch (ConfigException $exception) {
            throw BConfigException::listenersConfig($exception);
        }
    }

    /**
     * @inheritDoc
     * @throws BerliozException
     */
    public function subscribe(ListenerProviderInterface $provider): void
    {
        try {
            foreach ((array)$this->config->get('events.listeners', []) as $event => $listener) {
                if (is_string($listener)) {
                    $provider->addEventListener($event, $listener);
                    continue;
                }

                foreach ((array)$listener as $aListener) {
                    if (is_string($aListener)) {
                        $provider->addEventListener($event, $aListener);
                        continue;
                    }

                    if (!is_array($aListener)) {
                        throw BConfigException::listenersConfig();
                    }

                    $provider->addEventListener(
                        $event,
                        $aListener['callback'] ?? throw BConfigException::listenersConfig(),
                        (int)($aListener['priority'] ?? ListenerInterface::PRIORITY_NORMAL)
                    );
                }
            }
        } catch (ConfigException $exception) {
            throw BConfigException::listenersConfig($exception);
        }
    }
}