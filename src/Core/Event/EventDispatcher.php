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

use Berlioz\Core\Debug\DebugHandler;
use Berlioz\EventManager\EventDispatcher as BerliozEventDispatcher;
use Berlioz\EventManager\Provider\ListenerProviderInterface;

/**
 * Class EventDispatcher.
 */
class EventDispatcher extends BerliozEventDispatcher
{
    public function __construct(
        protected DebugHandler $debugHandler,
        ?ListenerProviderInterface $defaultProvider = null,
    ) {
        parent::__construct([], [], $defaultProvider);
    }

    /**
     * @inheritDoc
     */
    public function dispatch(object $event): object
    {
        $debugEvent = $this->debugHandler->newEvent($event);
        $activity = $this->debugHandler->newEventActivity($debugEvent)->start();

        try {
            return parent::dispatch($event);
        } finally {
            $activity->end();
        }
    }
}