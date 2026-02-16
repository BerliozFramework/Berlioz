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

namespace Berlioz\Core\Event\Provider;

use Berlioz\EventManager\Listener\ListenerInterface;
use Berlioz\EventManager\Provider\ListenerProvider as BerliozListenerProvider;
use Berlioz\ServiceContainer\Container;
use Berlioz\ServiceContainer\Exception\ContainerException;

/**
 * Class DefaultListenerProvider.
 */
class DefaultListenerProvider extends BerliozListenerProvider
{
    public function __construct(protected Container $container)
    {
    }

    /**
     * Invoke listener.
     *
     * @throws ContainerException
     */
    protected function invokeListener(ListenerInterface $listener, object $event): mixed
    {
        return $this->container->call($listener->getCallback(), ['event' => $event]);
    }
}