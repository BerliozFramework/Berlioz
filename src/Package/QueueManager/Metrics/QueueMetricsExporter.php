<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2026 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\Package\QueueManager\Metrics;

use Berlioz\QueueManager\Queue\MonitorableQueueInterface;
use Berlioz\QueueManager\QueueManager;

/**
 * Class QueueMetricsExporter.
 *
 * Exports the metrics of a {@see QueueManager} (size, wait time, delayed count) into the
 * supported output formats. Shared by the `queue:size` CLI command and the HTTP metrics
 * endpoint so that both expose strictly identical metrics.
 */
class QueueMetricsExporter
{
    public function __construct(
        private readonly QueueManager $queueManager,
    ) {
    }

    /**
     * Collect raw metrics from the queue manager.
     *
     * The returned array has the following shape:
     * <code>
     * [
     *     'queues' => [
     *         'queue-name' => ['size' => int, 'waitTime' => ?int, 'delayed' => ?int],
     *         // ...
     *     ],
     *     'total' => int, // sum of every queue size
     * ]
     * </code>
     *
     * @return array{queues: array<string, array{size: int, waitTime: ?int, delayed: ?int}>, total: int}
     */
    public function collect(): array
    {
        $queues = [];

        foreach ($this->queueManager->getQueues() as $queue) {
            $queues[$queue->getName()] = [
                'size' => $queue->size(),
                'waitTime' => $queue instanceof MonitorableQueueInterface ? $queue->waitTime() : null,
                'delayed' => $queue instanceof MonitorableQueueInterface ? $queue->delayed() : null,
            ];
        }

        return [
            'queues' => $queues,
            'total' => array_sum(array_column($queues, 'size')),
        ];
    }

    /**
     * Export metrics as a Prometheus text exposition.
     *
     * Emits `# HELP` / `# TYPE` metadata lines followed by the metric samples:
     *  - `job_queue_length`            (gauge) for every queue,
     *  - `job_queue_wait_time_seconds` (gauge) when the queue is monitorable,
     *  - `job_queue_delayed`           (gauge) when the queue is monitorable,
     *  - `job_queue_length_total`      (gauge) when `$withTotal` is true.
     *
     * @param array<string, string|int|float> $labels Extra labels, e.g. `['env' => 'prod', 'host' => 'web1']`
     * @param bool $withTotal Append the aggregated total line
     *
     * @return string
     */
    public function prometheus(array $labels = [], bool $withTotal = false): string
    {
        $metrics = $this->collect();
        $queues = $metrics['queues'];

        $renderedLabels = $this->renderLabels($labels);
        $extraLabels = '' === $renderedLabels ? '' : ',' . $renderedLabels;

        $lines = [];

        $lines[] = '# HELP job_queue_length Number of jobs waiting in the queue.';
        $lines[] = '# TYPE job_queue_length gauge';

        $waitTimeHeaderEmitted = false;
        $delayedHeaderEmitted = false;
        $waitTimeLines = [];
        $delayedLines = [];

        foreach ($queues as $queueName => $queueStats) {
            $queueLabel = sprintf('queue_name="%s"', $this->escapeLabelValue((string)$queueName));

            $lines[] = sprintf('job_queue_length{%s%s} %d', $queueLabel, $extraLabels, $queueStats['size']);

            if (null !== $queueStats['waitTime']) {
                if (false === $waitTimeHeaderEmitted) {
                    $waitTimeLines[] = '# HELP job_queue_wait_time_seconds'
                        . ' Age in seconds of the oldest consumable job in the queue.';
                    $waitTimeLines[] = '# TYPE job_queue_wait_time_seconds gauge';
                    $waitTimeHeaderEmitted = true;
                }

                $waitTimeLines[] = sprintf(
                    'job_queue_wait_time_seconds{%s%s} %d',
                    $queueLabel,
                    $extraLabels,
                    $queueStats['waitTime'],
                );
            }

            if (null !== $queueStats['delayed']) {
                if (false === $delayedHeaderEmitted) {
                    $delayedLines[] = '# HELP job_queue_delayed Number of delayed jobs in the queue.';
                    $delayedLines[] = '# TYPE job_queue_delayed gauge';
                    $delayedHeaderEmitted = true;
                }

                $delayedLines[] = sprintf(
                    'job_queue_delayed{%s%s} %d',
                    $queueLabel,
                    $extraLabels,
                    $queueStats['delayed'],
                );
            }
        }

        $lines = array_merge($lines, $waitTimeLines, $delayedLines);

        if ($withTotal) {
            $lines[] = '# HELP job_queue_length_total Total number of jobs waiting across all queues.';
            $lines[] = '# TYPE job_queue_length_total gauge';
            $lines[] = sprintf('job_queue_length_total{%s} %d', $renderedLabels, $metrics['total']);
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Export metrics as a JSON-serializable structure.
     *
     * Without total: the raw per-queue stats. With total: `['queues' => ..., 'total' => ...]`.
     *
     * @param bool $withTotal Wrap the result with the aggregated total
     *
     * @return array
     */
    public function json(bool $withTotal = false): array
    {
        $metrics = $this->collect();

        if (false === $withTotal) {
            return $metrics['queues'];
        }

        return $metrics;
    }

    /**
     * Parse a raw label string into an associative array.
     *
     * Accepts the legacy CLI format `env="prod",host="web1"` (optionally quoted values) and
     * returns `['env' => 'prod', 'host' => 'web1']`. Malformed segments are ignored.
     *
     * @param string|null $labels
     *
     * @return array<string, string>
     */
    public static function parseLabels(?string $labels): array
    {
        if (null === $labels || '' === trim($labels)) {
            return [];
        }

        $parsed = [];

        foreach (explode(',', $labels) as $pair) {
            if (1 !== preg_match('/^\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*(.*?)\s*$/', $pair, $matches)) {
                continue;
            }

            $parsed[$matches[1]] = trim($matches[2], '"');
        }

        return $parsed;
    }

    /**
     * Render an associative array of labels into a Prometheus label list.
     *
     * @param array<string, string|int|float> $labels
     *
     * @return string
     */
    private function renderLabels(array $labels): string
    {
        $rendered = [];

        foreach ($labels as $name => $value) {
            $rendered[] = sprintf('%s="%s"', $name, $this->escapeLabelValue((string)$value));
        }

        return implode(',', $rendered);
    }

    /**
     * Escape a Prometheus label value.
     *
     * Per the exposition format, backslash, double-quote and line feed must be escaped.
     *
     * @param string $value
     *
     * @return string
     */
    private function escapeLabelValue(string $value): string
    {
        return str_replace(['\\', '"', "\n"], ['\\\\', '\\"', '\\n'], $value);
    }
}
