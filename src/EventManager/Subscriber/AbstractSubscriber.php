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

namespace Berlioz\EventManager\Subscriber;

use Berlioz\EventManager\Event\EventInterface;
use Berlioz\EventManager\Provider\ListenerProviderInterface;

/**
 * Class AbstractSubscriber.
 */
abstract class AbstractSubscriber implements SubscriberInterface
{
    protected array $listens = [];

    /**
     * @inheritDoc
     */
    public function listens(string|object $event): bool
    {
        if ($event instanceof EventInterface) {
            return in_array($event->getName(), $this->listens);
        }

        if (is_string($event) && in_array($event, $this->listens)) {
            return true;
        }

        foreach ($this->listens as $listen) {
            if ($event instanceof $listen) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function subscribe(ListenerProviderInterface $provider): void
    {
    }
}