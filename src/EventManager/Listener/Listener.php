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

namespace Berlioz\EventManager\Listener;

use Berlioz\EventManager\Event\EventInterface;
use Closure;

/**
 * Class Listener.
 */
class Listener implements ListenerInterface
{
    public function __construct(
        protected string $event,
        protected Closure|array|string $callback,
        protected int $priority = ListenerInterface::PRIORITY_NORMAL
    ) {
    }

    /**
     * Get event name.
     *
     * @return string
     */
    public function getEvent(): string
    {
        return $this->event;
    }

    /**
     * @inheritDoc
     */
    public function getCallback(): Closure|array|string
    {
        return $this->callback;
    }

    /**
     * @inheritDoc
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * @inheritDoc
     */
    public function isListening(object|string $event): bool
    {
        if ($event instanceof EventInterface) {
            return $event->getName() === $this->event;
        }

        if (is_string($event) && $this->event === $event) {
            return true;
        }

        return $event instanceof $this->event;
    }
}