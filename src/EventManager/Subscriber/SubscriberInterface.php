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

use Berlioz\EventManager\Provider\ListenerProviderInterface;

/**
 * Interface SubscriberInterface.
 */
interface SubscriberInterface
{
    /**
     * Is listens?
     *
     * @param string|object $event
     *
     * @return bool
     */
    public function listens(string|object $event): bool;

    /**
     * Subscribe.
     *
     * @param ListenerProviderInterface $provider
     */
    public function subscribe(ListenerProviderInterface $provider): void;
}