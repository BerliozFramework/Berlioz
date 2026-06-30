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

namespace Berlioz\Package\QueueManager\Tests\Metrics;

use Berlioz\Package\QueueManager\Metrics\QueueMetricsExporter;
use Berlioz\Package\QueueManager\Tests\Fake\FakeMonitorableQueue;
use Berlioz\Package\QueueManager\Tests\Fake\FakeQueue;
use Berlioz\QueueManager\QueueManager;
use PHPUnit\Framework\TestCase;

class QueueMetricsExporterTest extends TestCase
{
    private function exporter(): QueueMetricsExporter
    {
        return new QueueMetricsExporter(
            new QueueManager(
                new FakeMonitorableQueue('emails', 42, 12, 3),
                new FakeQueue('default', 7),
            )
        );
    }

    public function testCollect()
    {
        $metrics = $this->exporter()->collect();

        $this->assertSame(
            [
                'queues' => [
                    'emails' => ['size' => 42, 'waitTime' => 12, 'delayed' => 3],
                    'default' => ['size' => 7, 'waitTime' => null, 'delayed' => null],
                ],
                'total' => 49,
            ],
            $metrics,
        );
    }

    public function testPrometheus()
    {
        $output = $this->exporter()->prometheus();

        $this->assertStringContainsString('# HELP job_queue_length Number of jobs waiting in the queue.', $output);
        $this->assertStringContainsString('# TYPE job_queue_length gauge', $output);
        $this->assertStringContainsString('job_queue_length{queue_name="emails"} 42', $output);
        $this->assertStringContainsString('job_queue_length{queue_name="default"} 7', $output);
        $this->assertStringContainsString('# TYPE job_queue_wait_time_seconds gauge', $output);
        $this->assertStringContainsString('job_queue_wait_time_seconds{queue_name="emails"} 12', $output);
        $this->assertStringContainsString('# TYPE job_queue_delayed gauge', $output);
        $this->assertStringContainsString('job_queue_delayed{queue_name="emails"} 3', $output);

        // The non-monitorable queue must not emit wait time / delayed lines.
        $this->assertStringNotContainsString('job_queue_wait_time_seconds{queue_name="default"}', $output);
        $this->assertStringNotContainsString('job_queue_delayed{queue_name="default"}', $output);

        // No total by default.
        $this->assertStringNotContainsString('job_queue_length_total', $output);
        $this->assertStringEndsWith("\n", $output);
    }

    public function testPrometheus_withTotal()
    {
        $output = $this->exporter()->prometheus([], true);

        $this->assertStringContainsString('# TYPE job_queue_length_total gauge', $output);
        $this->assertStringContainsString('job_queue_length_total{} 49', $output);
    }

    public function testPrometheus_withExtraLabels()
    {
        $output = $this->exporter()->prometheus(['env' => 'prod', 'host' => 'web1'], true);

        $this->assertStringContainsString(
            'job_queue_length{queue_name="emails",env="prod",host="web1"} 42',
            $output,
        );
        $this->assertStringContainsString('job_queue_length_total{env="prod",host="web1"} 49', $output);
    }

    public function testPrometheus_escapesExtraLabelValue()
    {
        $output = $this->exporter()->prometheus(['note' => 'a"b\\c']);

        $this->assertStringContainsString('job_queue_length{queue_name="emails",note="a\\"b\\\\c"} 42', $output);
    }

    public function testPrometheus_escapesQueueNameLabelValue()
    {
        $exporter = new QueueMetricsExporter(new QueueManager(new FakeQueue('we"ird\\name', 1)));

        $output = $exporter->prometheus();

        $this->assertStringContainsString('job_queue_length{queue_name="we\\"ird\\\\name"} 1', $output);
    }

    public function testParseLabels()
    {
        $this->assertSame([], QueueMetricsExporter::parseLabels(null));
        $this->assertSame([], QueueMetricsExporter::parseLabels(''));
        $this->assertSame(
            ['env' => 'prod', 'host' => 'web1'],
            QueueMetricsExporter::parseLabels('env="prod",host="web1"'),
        );
        $this->assertSame(
            ['env' => 'prod', 'region' => 'eu'],
            QueueMetricsExporter::parseLabels(' env = "prod" , region=eu '),
        );
    }

    public function testJson_withoutTotal()
    {
        $result = $this->exporter()->json();

        $this->assertSame(
            [
                'emails' => ['size' => 42, 'waitTime' => 12, 'delayed' => 3],
                'default' => ['size' => 7, 'waitTime' => null, 'delayed' => null],
            ],
            $result,
        );
    }

    public function testJson_withTotal()
    {
        $result = $this->exporter()->json(true);

        $this->assertArrayHasKey('queues', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertSame(49, $result['total']);
    }
}
