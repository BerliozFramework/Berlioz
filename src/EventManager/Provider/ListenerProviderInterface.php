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

use Berlioz\EventManager\Listener\ListenerInterface;
use Closure;
use Psr\EventDispatcher\ListenerProviderInterface as PsrListenerProviderInterface;

/**
 * Interface ListenerProviderInterface.
 */
interface ListenerProviderInterface extends PsrListenerProviderInterface
{
    /**
     * Add event listener.
     *
     * @param string|object $event
     * @param Closure|array|string $callback
     * @param int $priority
     *
     * @return ListenerInterface
     */
    public function addEventListener(
        string|object $event,
        Closure|array|string $callback,
        int $priority = 0
    ): ListenerInterface;

    /**
     * Add listener.
     *
     * @param ListenerInterface ...$listener
     */
    public function addListener(ListenerInterface ...$listener): void;
}