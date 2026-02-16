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

namespace Berlioz\Core\Debug;

use Berlioz\Config\ConfigInterface;
use Berlioz\Core\Debug\Snapshot\EventSet;
use Berlioz\Core\Debug\Snapshot\PerformanceInfo;
use Berlioz\Core\Debug\Snapshot\PhpErrorSet;
use Berlioz\Core\Debug\Snapshot\PhpInfo;
use Berlioz\Core\Debug\Snapshot\ProjectInfo;
use Berlioz\Core\Debug\Snapshot\Section;
use Berlioz\Core\Debug\Snapshot\SystemInfo;
use Berlioz\Core\Debug\Snapshot\Timeline;
use DateTimeImmutable;

/**
 * Class Snapshot.
 */
class Snapshot
{
    public function __construct(
        private string $uniqid,
        private DateTimeImmutable $dateTime,
        private PerformanceInfo $performanceInfo,
        private PhpInfo $phpInfo,
        private ProjectInfo $projectInfo,
        private SystemInfo $systemInfo,
        private ConfigInterface $config,
        private Timeline $timeline,
        private EventSet $events,
        private PhpErrorSet $phpErrors,
        private array $exceptions,
        private array $sections,
    ) {
        array_filter($this->sections, fn($section) => $section instanceof Section);
    }

    /**
     * Get uniqid.
     *
     * @return string
     */
    public function getUniqid(): string
    {
        return $this->uniqid;
    }

    /**
     * Get date/time of report.
     *
     * @return DateTimeImmutable
     */
    public function getDateTime(): DateTimeImmutable
    {
        return $this->dateTime;
    }

    /**
     * Get performance info.
     *
     * @return PerformanceInfo
     */
    public function getPerformanceInfo(): PerformanceInfo
    {
        return $this->performanceInfo;
    }

    /**
     * Get PHP info.
     *
     * @return PhpInfo
     */
    public function getPhpInfo(): PhpInfo
    {
        return $this->phpInfo;
    }

    /**
     * Get project info.
     *
     * @return ProjectInfo
     */
    public function getProjectInfo(): ProjectInfo
    {
        return $this->projectInfo;
    }

    /**
     * Get system info.
     *
     * @return SystemInfo
     */
    public function getSystemInfo(): SystemInfo
    {
        return $this->systemInfo;
    }

    /**
     * Get configuration.
     *
     * @return ConfigInterface
     */
    public function getConfig(): ConfigInterface
    {
        return $this->config;
    }

    /**
     * Get timeline.
     *
     * @return Timeline
     */
    public function getTimeline(): Timeline
    {
        return $this->timeline;
    }

    /**
     * Get events.
     *
     * @return EventSet
     */
    public function getEvents(): EventSet
    {
        return $this->events;
    }

    /**
     * Get PHP errors.
     *
     * @return PhpErrorSet
     */
    public function getPhpErrors(): PhpErrorSet
    {
        return $this->phpErrors;
    }

    /**
     * Get exceptions.
     *
     * @return array
     */
    public function getExceptions(): array
    {
        return $this->exceptions;
    }

    /**
     * Get sections.
     *
     * @return Section[]
     */
    public function getSections(): array
    {
        return $this->sections;
    }

    /**
     * Get section.
     *
     * @param string $id
     *
     * @return Section|null
     */
    public function getSection(string $id): ?Section
    {
        /** @var Section $section */
        foreach ($this->sections as $section) {
            if ($section->getSectionId() === $id) {
                return $section;
            }
        }

        return null;
    }
}