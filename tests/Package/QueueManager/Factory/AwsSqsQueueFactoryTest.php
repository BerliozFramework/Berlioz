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

use Berlioz\Core\Exception\ConfigException;
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

    public function testCreateFromConfig_withoutQueueUrl(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Missing or invalid SQS queue URL');

        iterator_to_array(AwsSqsQueueFactory::createFromConfig([
            'client' => [
                'version' => 'latest',
                'region' => 'eu-west-3',
                'credentials' => [
                    'key' => 'test',
                    'secret' => 'test',
                ],
            ],
            'name' => [
                ['name' => 'queue1'],
            ],
        ]));
    }

    public function testCreateFromConfig_withMaxAttempts(): void
    {
        $queues = iterator_to_array(AwsSqsQueueFactory::createFromConfig([
            'client' => [
                'version' => 'latest',
                'region' => 'eu-west-3',
                'credentials' => [
                    'key' => 'test',
                    'secret' => 'test',
                ],
            ],
            'max_attempts' => 3,
            'name' => [
                'queue1' => 'https://sqs.eu-west-3.amazonaws.com/123456789012/queue1',
                'queue2' => [
                    'url' => 'https://sqs.eu-west-3.amazonaws.com/123456789012/queue2',
                    'max_attempts' => 7,
                ],
            ],
        ]));

        $this->assertSame(3, $queues[0]->getMaxAttempts());
        $this->assertSame(7, $queues[1]->getMaxAttempts());
    }
}
