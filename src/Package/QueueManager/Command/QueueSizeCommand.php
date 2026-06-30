<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2024 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\Package\QueueManager\Command;

use Berlioz\Cli\Core\Command\AbstractCommand;
use Berlioz\Cli\Core\Command\Argument;
use Berlioz\Cli\Core\Console\Environment;
use Berlioz\Package\QueueManager\Metrics\QueueMetricsExporter;
use Berlioz\QueueManager\QueueManager;

#[Argument('queue', prefix: 'q', longPrefix: 'queue', description: 'Queue name', castTo: 'string')]
#[Argument('format', prefix: 'f', longPrefix: 'format', description: 'Output format', castTo: 'string')]
#[Argument('total', longPrefix: 'total', description: 'Total', defaultValue: false, noValue: true, castTo: 'bool')]
#[Argument('prometheusLabels', longPrefix: 'prometheus-labels', description: 'Prometheus labels', castTo: 'string')]
class QueueSizeCommand extends AbstractCommand
{
    public function __construct(
        private readonly QueueManager $queueManager,
    ) {
    }

    /**
     * @inheritDoc
     */
    public static function getDescription(): ?string
    {
        return 'Get size of queues';
    }

    /**
     * @inheritDoc
     */
    public function run(Environment $env): int
    {
        $queueManager = $this->queueManager->filter(...$env->getArgumentMultiple('queue'));
        $exporter = new QueueMetricsExporter($queueManager);
        $metrics = $exporter->collect();
        $stats = $metrics['queues'];
        $total = $metrics['total'];
        $withTotal = (bool)$env->getArgument('total');

        switch ($env->getArgument('format')) {
            // Prometheus format
            case 'prometheus':
                $env->console()->out(
                    rtrim(
                        $exporter->prometheus(
                            QueueMetricsExporter::parseLabels($env->getArgument('prometheusLabels')),
                            $withTotal,
                        ),
                        "\n",
                    )
                );
                break;
            // JSON format
            case 'json':
                $env->console()->json($exporter->json($withTotal));
                break;
            // RAW format
            default:
                $padding = $env->console()->padding(max(array_map(strlen(...), array_keys($stats))));
                foreach ($stats as $queueName => $queueStats) {
                    $waitTime = match ($queueStats['waitTime']) {
                        null => 'n/a',
                        default => $queueStats['waitTime'] . 's',
                    };
                    $delayed = match ($queueStats['delayed']) {
                        null => 'n/a',
                        default => (string)$queueStats['delayed'],
                    };

                    $padding
                        ->label($queueName)
                        ->result(sprintf('%d (wait: %s, delayed: %s)', $queueStats['size'], $waitTime, $delayed));
                }
                if ($env->getArgument('total')) {
                    $padding->label('')->result($total);
                }
        }

        return 0;
    }
}
