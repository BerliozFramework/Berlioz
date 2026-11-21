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

namespace Berlioz\Core\Tests\Debug\Snapshot;

use Berlioz\Core\Debug\Snapshot\PerformanceInfo;
use PHPUnit\Framework\TestCase;

class PerformanceInfoTest extends TestCase
{
    public function test()
    {
        $peakBefore = memory_get_peak_usage();
        $performanceInfo = new PerformanceInfo();
        $peakAfter = memory_get_peak_usage();
        $snapshotPeak = $performanceInfo->getMemoryPeakUsage();

        // The object captures the peak at snap time, not after PHPUnit has allocated assertion objects.
        $this->assertGreaterThanOrEqual($peakBefore, $snapshotPeak);
        $this->assertLessThanOrEqual($peakAfter, $snapshotPeak);
        $this->assertSame($snapshotPeak, $performanceInfo->getMemoryPeakUsage());

        if (function_exists('sys_getloadavg') && false !== sys_getloadavg()) {
            $this->assertCount(3, $performanceInfo->getLoadavg());
            foreach ($performanceInfo->getLoadavg() as $load) {
                $this->assertIsFloat($load);
            }
        } else {
            $this->assertNull($performanceInfo->getLoadavg());
        }
    }
}
