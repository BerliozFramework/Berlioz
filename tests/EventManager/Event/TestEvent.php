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

namespace Berlioz\EventManager\Tests\Event;

use Berlioz\EventManager\Event\CustomEvent;

class TestEvent extends CustomEvent
{
    protected int $counter = 0;

    public function getCounter(): int
    {
        return $this->counter;
    }

    public function increaseCounter(): static
    {
        $this->counter++;

        return $this;
    }
}