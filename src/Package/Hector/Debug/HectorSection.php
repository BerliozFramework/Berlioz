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
use Hector\Connection\Log\LogEntry;
use Hector\Connection\Log\Logger;

/**
 * Class HectorSection.
 */
class HectorSection extends AbstractSection implements Countable
{
    private array $loggers;
    private array $logs = [];

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
        $this->logs = array_map(
            function (LogEntry $entry) use ($sqlFormatter) {
                if ($entry->getType() !== LogEntry::TYPE_QUERY) {
                    return $entry;
                }

                return $entry->withStatement($sqlFormatter->format($entry->getStatement()));
            },
            $this->logs
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
        return ['logs' => $this->logs];
    }

    /**
     * PHP unserialize method.
     *
     * @param array $data
     */
    public function __unserialize(array $data): void
    {
        $this->logger = [];
        $this->logs = $data['logs'] ?? [];
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