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
use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use stdClass;

class EventTest extends TestCase
{
    public function test()
    {
        $event = new Event('anEvent', $date = new DateTime());

        $this->assertEquals('anEvent', $event->getName());
        $this->assertEquals($date, $event->getTime());

        $event = new Event(new stdClass(), $date = new DateTimeImmutable());

        $this->assertEquals(stdClass::class, $event->getName());
        $this->assertEquals($date, $event->getTime());
    }
}
