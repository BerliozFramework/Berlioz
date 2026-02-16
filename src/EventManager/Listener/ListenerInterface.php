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

use Closure;

/**
 * Interface ListenerInterface.
 */
interface ListenerInterface
{
    public const PRIORITY_HIGH = 100;
    public const PRIORITY_NORMAL = 0;
    public const PRIORITY_LOW = -100;

    /**
     * Get callback.
     *
     * @return Closure|array|string
     */
    public function getCallback(): Closure|array|string;

    /**
     * Get priority.
     *
     * @return int
     */
    public function getPriority(): int;

    /**
     * Is listening event?
     *
     * @param object|string $event
     *
     * @return bool
     */
    public function isListening(object|string $event): bool;
}