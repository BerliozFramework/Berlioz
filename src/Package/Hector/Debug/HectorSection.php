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

/**
 * Class HectorSection.
 */
class HectorSection extends AbstractSection implements Countable
{
    private array $loggers;
    private array $logs = [];
    private array $interpolated = [];

    /**
     * Hector constructor.
     *
     * @param Logger ...$logger
     */
    public function __construct(Logger ...$logger)
    {
        $this->loggers = $logger;
    }

    /**
     * @inheritDoc
     */
    public function snap(DebugHandler $debug): void
    {
        $sqlFormatter = new SqlFormatter(new NullHighlighter());

        $this->logs = array_merge(...array_map(fn(Logger $logger) => $logger->getLogs(), $this->loggers));
        $this->interpolated = [];
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
                    $statement,
                    1,
                );
                continue;
            }

            $statement = preg_replace('/\?/', addcslashes($value, '\\$'), $statement, 1);
        }

        return $statement;
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
        ];
    }

    /**
     * PHP unserialize method.
     *
     * @param array $data
     */
    public function __unserialize(array $data): void
    {
        $this->loggers = [];
        $this->logs = $data['logs'] ?? [];
        $this->interpolated = $data['interpolated'] ?? [];
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
