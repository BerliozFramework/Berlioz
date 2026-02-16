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
 * Class TimelineActivity.
 */
class TimelineActivity
{
    public const BERLIOZ_GROUP = 'Berlioz';

    protected string $uniqid;
    protected ?string $description = null;
    protected mixed $detail = null;
    protected mixed $result = null;
    protected ?float $startMicroTime = null;
    protected ?int $startMemoryUsage = null;
    protected ?int $startMemoryPeakUsage = null;
    protected ?float $endMicroTime = null;
    protected ?int $endMemoryUsage = null;
    protected ?int $endMemoryPeakUsage = null;

    /**
     * Activity constructor.
     *
     * @param string $name
     * @param string|null $group
     */
    public function __construct(
        private string $name,
        private ?string $group = 'Application'
    ) {
        $this->uniqid = uniqid();
    }

    /**
     * Get unique ID.
     *
     * @return string
     */
    public function getUniqId(): string
    {
        return $this->uniqid;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get group.
     *
     * @return string|null
     */
    public function getGroup(): ?string
    {
        return $this->group;
    }

    /**
     * Get description.
     *
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description ?: sprintf('%s / %s', $this->getGroup(), $this->getName());
    }

    /**
     * Set description.
     *
     * @param string|null $description
     *
     * @return static
     */
    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get detail.
     *
     * @return mixed
     */
    public function getDetail(): mixed
    {
        return $this->detail;
    }

    /**
     * Set detail.
     *
     * @param mixed $detail
     *
     * @return static
     */
    public function setDetail(mixed $detail): static
    {
        $this->detail = $detail;

        return $this;
    }

    /**
     * Get result.
     *
     * @return mixed
     */
    public function getResult(): mixed
    {
        return $this->result;
    }

    /**
     * Set result.
     *
     * @param mixed|null $result
     *
     * @return static
     */
    public function setResult(mixed $result = null): static
    {
        $this->result = $result;

        return $this;
    }

    /**
     * Get start micro time.
     *
     * @return float|null
     */
    public function getStartMicroTime(): ?float
    {
        return $this->startMicroTime;
    }

    /**
     * Get start memory usage.
     *
     * @return int|null
     */
    public function getStartMemoryUsage(): ?int
    {
        return $this->startMemoryUsage;
    }

    /**
     * Get start memory peak usage.
     *
     * @return int|null
     */
    public function getStartMemoryPeakUsage(): ?int
    {
        return $this->startMemoryPeakUsage;
    }

    /**
     * Get end micro time.
     *
     * @return float|null
     */
    public function getEndMicroTime(): ?float
    {
        return $this->endMicroTime;
    }

    /**
     * Get end memory usage.
     *
     * @return int|null
     */
    public function getEndMemoryUsage(): ?int
    {
        return $this->endMemoryUsage;
    }

    /**
     * Get end memory peak usage.
     *
     * @return int|null
     */
    public function getEndMemoryPeakUsage(): ?int
    {
        return $this->endMemoryPeakUsage;
    }

    /**
     * Get memory usage.
     *
     * @return int|null
     */
    public function getMemoryUsage(): ?int
    {
        if (null === $this->endMemoryUsage || null === $this->startMemoryUsage) {
            return null;
        }

        return $this->endMemoryUsage - $this->startMemoryUsage;
    }

    /**
     * Get memory peak usage.
     *
     * @return int|null
     */
    public function getMemoryPeakUsage(): ?int
    {
        if (null === $this->endMemoryPeakUsage || null === $this->startMemoryPeakUsage) {
            return null;
        }

        return $this->endMemoryPeakUsage - $this->startMemoryPeakUsage;
    }

    ////////////////////////////////
    /// TimeLine Getters/Setters ///
    ////////////////////////////////

    /**
     * Start activity.
     *
     * @param float|null $microTime
     * @param int|null $memoryUsage Memory usage, default: memory_get_usage()
     * @param int|null $memoryPeakUsage Memory peak usage, default: memory_get_peak_usage()
     *
     * @return static
     */
    public function start(?float $microTime = null, ?int $memoryUsage = null, ?int $memoryPeakUsage = null): static
    {
        $this->startMicroTime = $microTime ?? microtime(true);
        $this->startMemoryUsage = $memoryUsage ?? memory_get_usage();
        $this->startMemoryPeakUsage = $memoryPeakUsage ?? memory_get_peak_usage();

        return $this;
    }

    /**
     * End activity.
     *
     * @param float|null $microTime
     * @param int|null $memoryUsage Memory usage, default: memory_get_usage()
     * @param int|null $memoryPeakUsage Memory peak usage, default: memory_get_peak_usage()
     *
     * @return static
     */
    public function end(?float $microTime = null, ?int $memoryUsage = null, ?int $memoryPeakUsage = null): static
    {
        $this->endMicroTime = $microTime ?? microtime(true);
        $this->endMemoryUsage = $memoryUsage ?? memory_get_usage();
        $this->endMemoryPeakUsage = $memoryPeakUsage ?? memory_get_peak_usage();

        return $this;
    }

    /**
     * Get duration of activity.
     *
     * @return float|null
     */
    public function duration(): ?float
    {
        if (null === $this->startMicroTime || null === $this->endMicroTime) {
            return null;
        }

        return $this->endMicroTime - $this->startMicroTime;
    }
}