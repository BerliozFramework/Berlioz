<?php
/**
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2017 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Berlioz\Core\Services\Events;


use Berlioz\Core\App\AppAwareInterface;
use Berlioz\Core\App\AppAwareTrait;
use Psr\EventManager\EventInterface;
use Psr\EventManager\EventManagerInterface;

class EventManager implements AppAwareInterface, EventManagerInterface
{
    use AppAwareTrait;
    /** @var array Events */
    private $listeners;

    /**
     * EventManager constructor.
     */
    public function __construct()
    {
        $this->listeners = [];
    }

    /**
     * @inheritdoc
     */
    public function attach($event, $callback, $priority = 0)
    {
        if (Event::validEventName($event) && is_callable($callback)) {
            // Detach existing event
            $this->detach($event, $callback);

            // Attach new event
            $this->listeners[$event][] = ['callback' => $callback, 'priority' => $priority];

            return true;
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function detach($event, $callback)
    {
        if (Event::validEventName($event) && is_callable($callback)) {
            if (isset($this->listeners[$event])) {
                $callbackKeys = array_column($this->listeners[$event], 'callback');

                foreach ((array) array_search($callback, $callbackKeys, true) as $key) {
                    unset($this->listeners[$event][$key]);
                }
            }

            return true;
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function clearListeners($event)
    {
        unset($this->listeners[$event]);
    }

    /**
     * Trigger an event
     *
     * Can accept an EventInterface or will create one if not passed
     *
     * @param string|EventInterface $event
     * @param object|string         $target
     * @param array|object          $argv
     *
     * @return mixed
     */
    public function trigger($event, $target = null, $argv = [])
    {
        try {
            $result = false;

            // Create event or complete
            if ($event instanceof EventInterface) {
                if (!is_null($target)) {
                    $event->setTarget($target);
                }
                if (!empty($argv)) {
                    $event->setParams($argv);
                }
            } else {
                $event = new Event($event, $target, $argv);
            }

            // Call listeners
            if (Event::validEventName($event->getName()) && isset($this->listeners[$event->getName()])) {
                // Order by priority
                usort(
                    $this->listeners[$event->getName()],
                    function ($a, $b) {
                        if ($a['priority'] == $b['priority']) {
                            return 0;
                        }

                        return ($a['priority'] < $b['priority']) ? -1 : 1;
                    });

                // Call callbacks
                foreach ($this->listeners[$event->getName()] as $listener) {
                    if (!$event->isPropagationStopped()) {
                        $result = call_user_func($listener['callback'], $event);
                    }
                }
            }

            return $result;
        } catch (\Exception $e) {
            return false;
        }
    }
}