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

namespace Berlioz\Core\Tests\Debug\Snapshot;

use Berlioz\Core\Debug\Snapshot\Event;
use Berlioz\Core\Debug\Snapshot\EventSet;
use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use stdClass;

class EventSetTest extends TestCase
{
    public function test()
    {
        $eventSet = new EventSet(
            [
                $event1 = new Event('anEvent', new DateTime()),
                $event2 = new Event(new stdClass(), new DateTimeImmutable()),
            ]
        );

        $this->assertSame([$event1, $event2], iterator_to_array($eventSet->getEvents(), false));
    }
}
