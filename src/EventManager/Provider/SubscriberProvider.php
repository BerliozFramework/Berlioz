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

use Berlioz\EventManager\Subscriber\SubscriberInterface;
use Psr\EventDispatcher\ListenerProviderInterface as PsrListenerProviderInterface;

/**
 * Class SubscriberProvider.
 */
class SubscriberProvider implements PsrListenerProviderInterface
{
    /** @var SubscriberInterface[] */
    protected array $subscribers = [];
    protected array $subscribed = [];

    public function __construct(protected ListenerProviderInterface $listenerProvider)
    {
    }

    /**
     * Add subscriber.
     *
     * @param SubscriberInterface ...$subscriber
     */
    public function addSubscriber(SubscriberInterface ...$subscriber): void
    {
        array_push($this->subscribers, ...$subscriber);
    }

    /**
     * @inheritDoc
     */
    public function getListenersForEvent(object $event): array
    {
        foreach ($this->subscribers as $iSubscriber => $subscriber) {
            if (false === $subscriber->listens($event)) {
                continue;
            }

            array_push($this->subscribed, ...array_slice($this->subscribers, $iSubscriber, 1));
            $this->subscribers[$iSubscriber] = null;
            $subscriber->subscribe($this->listenerProvider);
        }

        $this->subscribers = array_filter($this->subscribers);

        return [];
    }
}