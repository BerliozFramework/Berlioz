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

namespace Berlioz\Package\QueueManager\Tests\Factory;

use Berlioz\Package\QueueManager\Factory\AwsSqsQueueFactory;
use Berlioz\QueueManager\Queue\AwsSqsQueue;
use PHPUnit\Framework\TestCase;

class AwsSqsQueueFactoryTest extends TestCase
{
    public function testCreateFromConfig_withoutNameInArrayQueue(): void
    {
        $queues = AwsSqsQueueFactory::createFromConfig([
            'client' => [
                'version' => 'latest',
                'region' => 'eu-west-3',
                'credentials' => [
                    'key' => 'test',
                    'secret' => 'test',
                ],
            ],
            'name' => [
                [
                    'url' => 'https://sqs.eu-west-3.amazonaws.com/123456789012/queue1',
                ],
            ],
        ]);

        $queues = iterator_to_array($queues);
        /** @var AwsSqsQueue $queue */
        $queue = $queues[0];

        $this->assertCount(1, $queues);
        $this->assertSame('https://sqs.eu-west-3.amazonaws.com/123456789012/queue1', $queue->getName());
    }
}
