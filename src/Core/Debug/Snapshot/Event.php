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

use Berlioz\EventManager\Event\CustomEvent;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Class Event.
 */
class Event
{
    private string $event;
    private DateTimeImmutable $time;

    public function __construct(string|object $event, DateTimeInterface $time)
    {
        if ($event instanceof CustomEvent) {
            $event = $event->getName();
        }
        if (is_object($event)) {
            $event = get_class($event);
        }
        $this->event = $event;
        $this->time = DateTimeImmutable::createFromInterface($time);
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->event;
    }

    /**
     * Get time.
     *
     * @return DateTimeInterface
     */
    public function getTime(): DateTimeInterface
    {
        return $this->time;
    }
}