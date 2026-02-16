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

namespace Berlioz\Core\Debug\Snapshot;

/**
 * Class PerformanceInfo.
 */
class PerformanceInfo
{
    private ?array $loadavg;
    private int $memoryPeakUsage;

    public function __construct()
    {
        $this->snap();
    }

    /**
     * Snap.
     */
    public function snap(): void
    {
        $this->loadavg = function_exists('sys_getloadavg') ? (sys_getloadavg() ?: null) : null;
        $this->memoryPeakUsage = memory_get_peak_usage();
    }

    /**
     * Get load average.
     *
     * @return array|null
     */
    public function getLoadavg(): ?array
    {
        return $this->loadavg;
    }

    /**
     * Get memory peak usage.
     *
     * @return int
     */
    public function getMemoryPeakUsage(): int
    {
        return $this->memoryPeakUsage;
    }
}