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

namespace Berlioz\Package\Hector\Event;

use Berlioz\EventManager\Provider\ListenerProviderInterface;
use Berlioz\EventManager\Subscriber\AbstractSubscriber;
use Berlioz\ServiceContainer\Container;
use Berlioz\ServiceContainer\Exception\ContainerException;
use Hector\Orm\Event\EntityAfterDeleteEvent;
use Hector\Orm\Event\EntityAfterSaveEvent;
use Hector\Orm\Event\EntityBeforeDeleteEvent;
use Hector\Orm\Event\EntityBeforeSaveEvent;
use Hector\Orm\Event\EntityEvent;

/**
 * Class EventSubscriber.
 */
class EventSubscriber extends AbstractSubscriber
{
    protected array $listens = [
        EntityEvent::class
    ];

    public function __construct(protected Container $container)
    {
    }

    /**
     * @inheritDoc
     */
    public function subscribe(ListenerProviderInterface $provider): void
    {
        $provider->addEventListener(
            EntityEvent::class,
            fn($event) => $this->onEntityEvent($event)
        );
    }

    /**
     * On entity event.
     *
     * @param EntityEvent $event
     *
     * @throws ContainerException
     */
    protected function onEntityEvent(EntityEvent $event): void
    {
        $methods = [];

        switch ($event::class) {
            case EntityBeforeSaveEvent::class:
                $methods[] = 'onBeforeSave';
                /** @var EntityBeforeSaveEvent $event */
                if ($event->isUpdate()) {
                    $methods[] = 'onBeforeUpdate';
                    break;
                }
                $methods[] = 'onBeforeInsert';
                break;
            case EntityAfterSaveEvent::class:
                $methods[] = 'onAfterSave';
                $methods[] = 'onSave';
                /** @var EntityAfterSaveEvent $event */
                if ($event->isUpdate()) {
                    $methods[] = 'onAfterUpdate';
                    $methods[] = 'onUpdate';
                    break;
                }
                $methods[] = 'onAfterInsert';
                $methods[] = 'onInsert';
                break;
            case EntityBeforeDeleteEvent::class:
                $methods[] = 'onBeforeDelete';
                break;
            case EntityAfterDeleteEvent::class:
                $methods[] = 'onAfterDelete';
                $methods[] = 'onDelete';
                break;
        }

        $this->callMethods($event, $methods);
    }

    /**
     * Call event methods.
     *
     * @param EntityEvent $event
     * @param array $methods
     *
     * @throws ContainerException
     */
    protected function callMethods(EntityEvent $event, array $methods): void
    {
        $entity = $event->getEntity();

        foreach ($methods as $method) {
            if (false === method_exists($entity, $method)) {
                continue;
            }

            $this->container->call([$entity, $method], ['event' => $event]);
        }
    }
}
