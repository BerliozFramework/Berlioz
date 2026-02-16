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

use Berlioz\Core\Debug\Snapshot\PerformanceInfo;
use PHPUnit\Framework\TestCase;

class PerformanceInfoTest extends TestCase
{
    public function test()
    {
        $performanceInfo = new PerformanceInfo();

        $this->assertEquals(
            function_exists('sys_getloadavg') ? (sys_getloadavg() ?: null) : null,
            $performanceInfo->getLoadavg()
        );
        $this->assertEquals(memory_get_peak_usage(), $performanceInfo->getMemoryPeakUsage());
    }
}
