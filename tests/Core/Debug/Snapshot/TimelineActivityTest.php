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

use Berlioz\Core\Debug\Snapshot\TimelineActivity;
use PHPUnit\Framework\TestCase;

class TimelineActivityTest extends TestCase
{
    public function test__construct()
    {
        $activity = new TimelineActivity('Foo', 'Bar');

        $this->assertInstanceOf(TimelineActivity::class, $activity);
        $this->assertEquals('Foo', $activity->getName());
        $this->assertEquals('Bar', $activity->getGroup());
        $this->assertEquals('Bar / Foo', $activity->getDescription());
        $this->assertNotNull($activity->getUniqId());
        $this->assertNull($activity->getDetail());
        $this->assertNull($activity->getStartMicroTime());
        $this->assertNull($activity->getEndMicroTime());
        $this->assertNull($activity->getStartMemoryUsage());
        $this->assertNull($activity->getEndMemoryUsage());
        $this->assertNull($activity->getStartMemoryPeakUsage());
        $this->assertNull($activity->getEndMemoryPeakUsage());
        $this->assertNull($activity->duration());
    }

    public function testTimer()
    {
        $activity = new TimelineActivity('Foo', 'Bar');
        $activity->start();

        $this->assertNotNull($activity->getStartMicroTime());
        $this->assertNull($activity->getEndMicroTime());

        $activity->end();

        $this->assertNotNull($activity->getEndMicroTime());
        $this->assertEquals($activity->getEndMicroTime() - $activity->getStartMicroTime(), $activity->duration());
    }

    public function testDescription()
    {
        $activity = new TimelineActivity('Foo', 'Bar');
        $this->assertEquals('Bar / Foo', $activity->getDescription());

        $activity->setDescription('Qux quux');
        $this->assertEquals('Qux quux', $activity->getDescription());
    }

    public function testDetail()
    {
        $activity = new TimelineActivity('Foo', 'Bar');
        $this->assertNull($activity->getDetail());

        $activity->setDetail('Quux');
        $this->assertEquals('Quux', $activity->getDetail());
    }

    public function testResult()
    {
        $activity = new TimelineActivity('Foo', 'Bar');
        $this->assertNull($activity->getResult());

        $activity->setResult($result = ['Qux', 'Quux']);
        $this->assertEquals($result, $activity->getResult());
    }
}
