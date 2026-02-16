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

namespace Berlioz\Core\Debug\Snapshot;

use Countable;
use Generator;

/**
 * Class EventSet.
 */
class EventSet implements Countable
{
    public function __construct(private array $events = [])
    {
        $this->events = array_filter($this->events, fn($event) => $event instanceof Event);
        usort($this->events, fn(Event $event1, Event $event2) => $event1->getTime() <=> $event2->getTime());
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->events);
    }

    /**
     * Get events.
     *
     * @return Generator
     */
    public function getEvents(): Generator
    {
        yield from $this->events;
    }
}