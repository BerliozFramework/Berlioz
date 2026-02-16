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
        $sizes = iterator_to_array($queueManager->stats());
        $total = array_sum($sizes);

        switch ($env->getArgument('format')) {
            // Prometheus format
            case 'prometheus':
                $labels = $env->getArgument('prometheusLabels') ?? '';
                $labels = trim($labels, ' ,');
                !empty($labels) && $labels = ',' . $labels;

                foreach ($sizes as $queueName => $size) {
                    $env->console()->out(sprintf('job_queue_length{queue_name="%s"%s} %d', $queueName, $labels, $size));
                }
                if ($env->getArgument('total')) {
                    $env->console()->out(sprintf('job_queue_length_total{%s} %d', trim($labels, ','), $total));
                }
                break;
            // JSON format
            case 'json':
                $env->console()->json(match ($env->getArgument('total')) {
                    false => $sizes,
                    true => ['queues' => $sizes, 'total' => $total],
                });
                break;
            // RAW format
            default:
                $padding = $env->console()->padding(max(array_map(fn($v) => strlen($v), array_keys($sizes))));
                foreach ($sizes as $queueName => $size) {
                    $padding->label($queueName)->result($size);
                }
                if ($env->getArgument('total')) {
                    $padding->label('')->result($total);
                }
        }

        return 0;
    }
}
