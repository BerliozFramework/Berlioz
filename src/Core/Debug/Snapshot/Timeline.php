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

use Countable;

/**
 * Class Timeline.
 */
class Timeline implements Countable
{
    public function __construct(private array $activities = [])
    {
        // Filter activities
        array_filter($this->activities, fn($activity) => $activity instanceof TimelineActivity);

        // Sort activities
        usort(
            $this->activities,
            fn(TimelineActivity $a1, TimelineActivity $a2) => $a1->getStartMicroTime() <=> $a2->getStartMicroTime()
        );
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->activities);
    }

    ////////////////////
    /// USER DEFINED ///
    ////////////////////

    /**
     * Get groups.
     *
     * @return string[]
     */
    public function getGroups(): array
    {
        $groups = [];

        foreach ($this->activities as $activity) {
            $groups[] = $activity->getGroup();
        }

        return array_unique($groups);
    }

    /**
     * Get activities.
     *
     * @param string|null $group Group name, null for all
     *
     * @return TimelineActivity[]
     */
    public function getActivities(?string $group = null): array
    {
        if (null === $group) {
            return $this->activities;
        }

        return array_filter($this->activities, fn(TimelineActivity $activity) => $activity->getGroup() == $group);
    }

    /**
     * Get activity.
     *
     * @param string $uniqid
     *
     * @return TimelineActivity|null
     */
    public function getActivity(string $uniqid): ?TimelineActivity
    {
        /** @var TimelineActivity $activity */
        foreach ($this->activities as $activity) {
            if ($activity->getUniqId() === $uniqid) {
                return $activity;
            }
        }

        return null;
    }

    /**
     * Get first time.
     *
     * @param string|null $group Group name, null for all
     *
     * @return float|null
     */
    public function getFirstTime(?string $group = null): ?float
    {
        $activities = $this->getActivities($group);
        $activities = array_map(
            function (TimelineActivity $activity) {
                return $activity->getStartMicroTime();
            },
            $activities
        );
        $activities = array_filter($activities);

        if (empty($activities)) {
            return null;
        }

        return min($activities);
    }

    /**
     * Get last time.
     *
     * @param string|null $group Group name, null for all
     *
     * @return float|null
     */
    public function getLastTime(?string $group = null): ?float
    {
        $activities = $this->getActivities($group);
        $activities = array_map(
            function (TimelineActivity $activity) {
                return $activity->getEndMicroTime();
            },
            $activities
        );
        $activities = array_filter($activities);

        if (empty($activities)) {
            return null;
        }

        return max($activities);
    }

    /**
     * Get duration.
     *
     * @param string|null $group Group name, null for all
     *
     * @return float|null
     */
    public function getDuration(?string $group = null): ?float
    {
        $firstTime = $this->getFirstTime($group);
        $lastTime = $this->getLastTime($group);

        if (null === $firstTime || null === $lastTime) {
            return null;
        }

        return $lastTime - $firstTime;
    }

    /**
     * Get memory usages.
     *
     * @param string|null $group Group name, null for all
     *
     * @return array[]
     */
    public function getMemoryUsages(?string $group = null): array
    {
        $memoryUsages = [];
        $firstTime = $this->getFirstTime($group);

        if (null === $firstTime) {
            return [];
        }

        $activities = $this->getActivities($group);
        $activities = array_values(
            array_filter(
                $activities,
                function (TimelineActivity $activity) {
                    return null !== $activity->getStartMicroTime() && null !== $activity->getEndMicroTime();
                }
            )
        );
        $nbActivities = count($activities);

        for ($i = 0; $i < $nbActivities; $i++) {
            $activity = $activities[$i];
            $nextActivity = $activities[$i + 1] ?? null;

            $from = $activity->getStartMicroTime() - $firstTime;
            if ($nextActivity) {
                $to = $nextActivity->getStartMicroTime() - $firstTime;

                if ($activity->getEndMicroTime() > $nextActivity->getStartMicroTime()) {
                    $memory_usage = $nextActivity->getStartMemoryUsage();
                    $memory_peak_usage = $nextActivity->getStartMemoryPeakUsage();
                } else {
                    $memory_usage = $activity->getEndMemoryUsage();
                    $memory_peak_usage = $activity->getEndMemoryPeakUsage();
                }
            } else {
                $to = $activity->getEndMicroTime() - $firstTime;
                $memory_usage = $activity->getEndMemoryUsage();
                $memory_peak_usage = $activity->getEndMemoryPeakUsage();
            }

            $memoryUsages[] = [
                'from' => $from,
                'to' => $to,
                'memory' => $memory_usage,
                'memory_peak' => $memory_peak_usage,
            ];
        }

        return $memoryUsages;
    }

    /**
     * Get memory peak usage.
     *
     * @param string|null $group Group name, null for all
     *
     * @return int|null
     */
    public function getMemoryPeakUsage(?string $group = null): ?int
    {
        $activities = $this->getActivities($group);
        $memory = array_map(fn(TimelineActivity $activity) => $activity->getEndMemoryPeakUsage(), $activities);
        $memory = array_filter($memory);

        if (empty($memory)) {
            return null;
        }

        return max($memory);
    }
}