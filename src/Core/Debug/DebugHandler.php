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
use Berlioz\Config\Exception\ConfigException;
use Berlioz\Core\Core;
use Berlioz\Core\Debug\Snapshot\Event;
use Berlioz\Core\Debug\Snapshot\EventSet;
use Berlioz\Core\Debug\Snapshot\PerformanceInfo;
use Berlioz\Core\Debug\Snapshot\PhpInfo;
use Berlioz\Core\Debug\Snapshot\ProjectInfo;
use Berlioz\Core\Debug\Snapshot\Section;
use Berlioz\Core\Debug\Snapshot\SystemInfo;
use Berlioz\Core\Debug\Snapshot\Timeline;
use Berlioz\Core\Debug\Snapshot\TimelineActivity;
use Berlioz\Core\Debug\Snapshot\TimelineEvent;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Throwable;

/**
 * Class DebugHandler.
 */
class DebugHandler
{
    private string $uniqid;
    private bool $enabled = false;
    private Core $core;
    private DateTimeImmutable $dateTime;
    private array $activities;
    private array $exceptions;
    private array $events;
    private array $sections;
    private PhpErrorHandler $phpErrorHandler;
    private ?float $responseSentTime;

    public function __construct()
    {
        $this->reset();
    }

    public function reset(): void
    {
        $this->uniqid = uniqid();
        $this->enabled = false;
        $this->responseSentTime = null;
        $this->dateTime = new DateTimeImmutable();
        $this->activities = [];
        $this->exceptions = [];
        $this->events = [];
        $this->sections = [];
        $this->phpErrorHandler = new PhpErrorHandler();
    }

    /**
     * Handle.
     *
     * @param Core $core
     */
    public function handle(Core $core): void
    {
        $this->phpErrorHandler->handle();
        $this->core = $core;
    }

    /**
     * Is enabled?
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled === true;
    }

    /**
     * Set enabled.
     *
     * @param bool $enabled
     */
    public function setEnabled(bool $enabled = true): void
    {
        $this->enabled = $enabled;
    }

    /**
     * Is enabled in config?
     *
     * @param ConfigInterface $config
     *
     * @return bool
     * @throws ConfigException
     */
    public function isEnabledInConfig(ConfigInterface $config): bool
    {
        $policy = new DebugAccessPolicy($config);

        if (PHP_SAPI === 'cli' && !isset($_SERVER['REMOTE_ADDR'])) {
            return $policy->isLocalProcessAllowed();
        }

        return $policy->isClientAllowed($_SERVER);
    }

    /**
     * Add timeline activity.
     *
     * @param TimelineActivity ...$activity
     */
    public function addActivity(TimelineActivity ...$activity): void
    {
        // Ignore activity if debug is disabled
        if (!$this->isEnabled()) {
            return;
        }

        array_push($this->activities, ...$activity);
    }

    /**
     * New timeline activity.
     *
     * @param string $name
     * @param string $group
     *
     * @return TimelineActivity
     */
    public function newActivity(string $name, string $group = 'Application'): TimelineActivity
    {
        $activity = new TimelineActivity($name, $group);
        $this->addActivity($activity);

        return $activity;
    }

    /**
     * New timeline event activity.
     *
     * @param Event $event
     *
     * @return TimelineActivity
     */
    public function newEventActivity(Event $event): TimelineActivity
    {
        $activity = new TimelineEvent($event);
        $this->addActivity($activity);

        return $activity;
    }

    /**
     * Add event.
     *
     * @param Event $event
     */
    public function addEvent(Event $event): void
    {
        // Ignore if debug is disabled
        if (!$this->isEnabled()) {
            return;
        }

        $this->events[] = $event;
    }

    /**
     * New event.
     *
     * @param string|object $event
     * @param DateTimeInterface|null $time
     *
     * @return Event
     */
    public function newEvent(string|object $event, ?DateTimeInterface $time = null): Event
    {
        $event = new Event($event, $time ?? new DateTime());
        $this->addEvent($event);

        return $event;
    }

    /**
     * Add section.
     *
     * @param Section ...$section
     */
    public function addSection(Section ...$section): void
    {
        // Ignore activity if debug is disabled
        if (!$this->isEnabled()) {
            return;
        }

        array_push($this->sections, ...$section);
    }

    /**
     * Add exception.
     *
     * @param Throwable ...$exception
     */
    public function addException(Throwable ...$exception): void
    {
        // Ignore if debug is disabled
        if (!$this->isEnabled()) {
            return;
        }

        array_push($this->exceptions, ...array_map(fn(Throwable $e) => (string)$e, $exception));
    }

    /**
     * Mark the moment the response has been sent to the client.
     *
     * @param float|null $microTime
     */
    public function markResponseSent(?float $microTime = null): void
    {
        $this->responseSentTime = $microTime ?? microtime(true);
    }

    /**
     * Get the moment the response has been sent to the client.
     *
     * @return float|null
     */
    public function getResponseSentTime(): ?float
    {
        return $this->responseSentTime;
    }

    /**
     * Get response time (from the first activity to the response sent), in seconds.
     *
     * @return float|null
     */
    public function getResponseTime(): ?float
    {
        return (new Timeline($this->activities))->getDurationUntil($this->responseSentTime);
    }

    /**
     * Get unique id of current snapshot.
     *
     * @return string
     */
    public function getUniqid(): string
    {
        return $this->uniqid;
    }

    /**
     * Get snapshot.
     *
     * @return Snapshot
     */
    public function getSnapshot(): Snapshot
    {
        array_walk($this->sections, fn(Section $section) => $section->snap($this));

        return new Snapshot(
            uniqid: $this->uniqid,
            dateTime: $this->dateTime,
            performanceInfo: new PerformanceInfo(),
            phpInfo: new PhpInfo(),
            projectInfo: new ProjectInfo($this->core->getComposer()),
            systemInfo: new SystemInfo(),
            config: $this->core->getConfig(),
            timeline: new Timeline($this->activities),
            events: new EventSet($this->events),
            phpErrors: $this->phpErrorHandler->getErrors(),
            exceptions: $this->exceptions,
            sections: $this->sections,
            responseSentTime: $this->responseSentTime,
        );
    }
}