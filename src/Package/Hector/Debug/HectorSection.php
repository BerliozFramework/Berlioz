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

namespace Berlioz\Package\Hector\Debug;

use Berlioz\Core\Debug\AbstractSection;
use Berlioz\Core\Debug\DebugHandler;
use Countable;
use Doctrine\SqlFormatter\NullHighlighter;
use Doctrine\SqlFormatter\SqlFormatter;
use Hector\Connection\Bind\BindParam;
use Hector\Connection\Log\LogEntry;
use Hector\Connection\Log\Logger;
use PDO;
use Stringable;

/**
 * Class HectorSection.
 */
class HectorSection extends AbstractSection implements Countable, Stringable
{
    private array $logs = [];
    private array $interpolated = [];
    private array $duplicates = [];
    private int $redundant = 0;
    private float $slowThreshold = 0.05;
    private float $verySlowThreshold = 0.1;
    private int $duplicateThreshold = 2;

    /**
     * Hector constructor.
     *
     * @param Logger|null $logger
     */
    public function __construct(private ?Logger $logger = null)
    {
    }

    /**
     * Set slow-query thresholds.
     *
     * @param float $slowMs Threshold in milliseconds for a slow query (warning).
     * @param float $verySlowMs Threshold in milliseconds for a very slow query (danger).
     *
     * @return static
     */
    public function setThresholds(float $slowMs, float $verySlowMs): static
    {
        $this->slowThreshold = $slowMs / 1000;
        $this->verySlowThreshold = $verySlowMs / 1000;

        return $this;
    }

    /**
     * Set the duplicate threshold.
     *
     * @param int $threshold Number of identical executions from which a query is reported.
     *
     * @return static
     */
    public function setDuplicateThreshold(int $threshold): static
    {
        $this->duplicateThreshold = max(2, $threshold);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function snap(DebugHandler $debug): void
    {
        $sqlFormatter = new SqlFormatter(new NullHighlighter());

        $this->logs = array_values($this->logger?->getLogs() ?? []);
        $this->interpolated = [];
        $this->duplicates = [];
        $this->redundant = 0;

        // Compute duplicate occurrences from the raw entries (before SQL formatting).
        $this->computeDuplicates($this->logs);

        $this->logs = array_map(
            function (LogEntry $entry, int $index) use ($sqlFormatter) {
                if ($entry->getType() !== LogEntry::TYPE_QUERY) {
                    return $entry;
                }

                $this->interpolated[$index] = $sqlFormatter->format(
                    $this->interpolateStatement($entry->getStatement(), $entry->getParameters())
                );

                return $entry->withStatement($sqlFormatter->format($entry->getStatement()));
            },
            $this->logs,
            array_keys($this->logs),
        );

        // Add queries to the timeline
        foreach ($this->logs as $logEntry) {
            $activity = $debug->newActivity('Query', $this->getSectionName());
            $activity
                ->start($logEntry->getStart())
                ->end($logEntry->getEnd())
                ->setDetail($logEntry->getStatement())
                ->setResult($logEntry->getTrace());
        }
    }

    /**
     * Interpolate statement with its bound parameters.
     *
     * The result is intended for debugging and copy/paste only, never for
     * re-execution: values are quoted for readability, not for security.
     *
     * @param string $statement
     * @param iterable $parameters
     *
     * @return string
     */
    private function interpolateStatement(string $statement, iterable $parameters): string
    {
        foreach ($parameters as $parameter) {
            if (!$parameter instanceof BindParam) {
                continue;
            }

            $name = $parameter->getName();
            $value = $this->quoteValue($parameter->getValue(), $parameter->getDataType());

            // Named placeholder (e.g. ":_h_0") or positional placeholder ("?").
            if (is_string($name)) {
                $placeholder = ':' . ltrim($name, ':');
                $statement = preg_replace(
                    '/' . preg_quote($placeholder, '/') . '\b/',
                    addcslashes($value, '\\$'),
                    (string)$statement,
                    1,
                );
                continue;
            }

            $statement = preg_replace('/\?/', addcslashes($value, '\\$'), (string)$statement, 1);
        }

        return $statement;
    }

    /**
     * Compute duplicate occurrences from the given log entries.
     *
     * Two queries are considered duplicates when they share the same connection,
     * the same raw statement and the same bound parameter values. Each duplicated
     * log index is mapped to the total number of times the query was executed.
     * Unique queries are not stored.
     *
     * @param LogEntry[] $logs
     */
    private function computeDuplicates(array $logs): void
    {
        $signatures = [];

        // First pass: gather log indexes sharing the same signature.
        foreach ($logs as $index => $entry) {
            if ($entry->getType() !== LogEntry::TYPE_QUERY) {
                continue;
            }

            $signatures[$this->signature($entry)][] = $index;
        }

        // Second pass: map each duplicated index to its number of occurrences.
        foreach ($signatures as $indexes) {
            $count = count($indexes);
            if ($count < $this->duplicateThreshold) {
                continue;
            }

            // For a group of N identical queries, N-1 are redundant.
            $this->redundant += $count - 1;

            foreach ($indexes as $index) {
                $this->duplicates[$index] = $count;
            }
        }
    }

    /**
     * Compute the duplicate signature of a log entry.
     *
     * @param LogEntry $entry
     *
     * @return string
     */
    private function signature(LogEntry $entry): string
    {
        $values = [];

        foreach ($entry->getParameters() as $parameter) {
            if (!$parameter instanceof BindParam) {
                continue;
            }

            $values[] = $parameter->getName()
                . '=' . $parameter->getDataType()
                . ':' . $this->quoteValue($parameter->getValue(), $parameter->getDataType());
        }

        return hash('xxh128', $entry->getConnection() . "\0" . $entry->getStatement() . "\0" . implode('|', $values));
    }

    /**
     * Quote value according to its PDO data type.
     *
     * @param mixed $value
     * @param int $dataType
     *
     * @return string
     */
    private function quoteValue(mixed $value, int $dataType): string
    {
        return match ($dataType) {
            PDO::PARAM_NULL => 'NULL',
            PDO::PARAM_INT => (string)(int)$value,
            PDO::PARAM_BOOL => $value ? '1' : '0',
            default => null === $value
                ? 'NULL'
                : "'" . str_replace("'", "''", (string)$value) . "'",
        };
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return var_export($this, true);
    }

    /**
     * PHP serialize method.
     *
     * @return array
     */
    public function __serialize(): array
    {
        return [
            'logs' => $this->logs,
            'interpolated' => $this->interpolated,
            'duplicates' => $this->duplicates,
            'redundant' => $this->redundant,
            'slowThreshold' => $this->slowThreshold,
            'verySlowThreshold' => $this->verySlowThreshold,
            'duplicateThreshold' => $this->duplicateThreshold,
        ];
    }

    /**
     * PHP unserialize method.
     *
     * @param array $data
     */
    public function __unserialize(array $data): void
    {
        $this->logger = null;
        $this->logs = $data['logs'] ?? [];
        $this->interpolated = $data['interpolated'] ?? [];
        $this->duplicates = $data['duplicates'] ?? [];
        $this->redundant = $data['redundant'] ?? 0;
        $this->slowThreshold = $data['slowThreshold'] ?? 0.05;
        $this->verySlowThreshold = $data['verySlowThreshold'] ?? 0.1;
        $this->duplicateThreshold = $data['duplicateThreshold'] ?? 2;
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->logs);
    }

    /**
     * Get section name.
     *
     * @return string
     */
    public function getSectionName(): string
    {
        return 'Hector ORM';
    }

    /**
     * Get template name.
     */
    public function getTemplateName(): string
    {
        return '@Berlioz-HectorPackage/Twig/Debug/hector.html.twig';
    }

    /**
     * Get logs.
     *
     * @return array
     */
    public function getLogs(): array
    {
        return $this->logs;
    }

    /**
     * Get interpolated statement for the given log index (values replaced).
     *
     * @param int $index
     *
     * @return string|null
     */
    public function getInterpolatedStatement(int $index): ?string
    {
        return $this->interpolated[$index] ?? null;
    }

    /**
     * Get the number of occurrences of the query at the given log index.
     *
     * Returns how many times an identical query (same connection, statement and
     * parameter values) was executed, or null if the query is unique.
     *
     * @param int $index
     *
     * @return int|null
     */
    public function getDuplicateCount(int $index): ?int
    {
        return $this->duplicates[$index] ?? null;
    }

    /**
     * Count redundant query executions.
     *
     * Returns the number of queries that could have been saved, i.e. the
     * occurrences beyond the first one of each duplicated query.
     *
     * @return int
     */
    public function countDuplicates(): int
    {
        return $this->redundant;
    }

    /**
     * Get the severity of a log entry based on its duration.
     *
     * Returns 'danger' for a very slow query, 'warning' for a slow query, or
     * null when the query is within acceptable bounds. The thresholds are
     * absolute and independent of the other queries of the request.
     *
     * @param LogEntry $entry
     *
     * @return string|null
     */
    public function getSeverity(LogEntry $entry): ?string
    {
        $duration = $entry->getDuration();

        if (null === $duration) {
            return null;
        }

        return match (true) {
            $duration >= $this->verySlowThreshold => 'danger',
            $duration >= $this->slowThreshold => 'warning',
            default => null,
        };
    }

    /**
     * Get the slow-query threshold in milliseconds.
     *
     * @return float
     */
    public function getSlowThreshold(): float
    {
        return $this->slowThreshold * 1000;
    }

    /**
     * Get the very-slow-query threshold in milliseconds.
     *
     * @return float
     */
    public function getVerySlowThreshold(): float
    {
        return $this->verySlowThreshold * 1000;
    }

    /**
     * Get total duration.
     *
     * @return float
     */
    public function getDuration(): float
    {
        if (empty($this->logs)) {
            return 0;
        }

        $duration = array_reduce($this->logs, fn($time, LogEntry $logEntry) => $time + $logEntry->getDuration());

        return floatval($duration);
    }
}
