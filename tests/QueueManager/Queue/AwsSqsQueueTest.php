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

namespace Berlioz\QueueManager\Tests\Queue;

use Aws\Result;
use Aws\CloudWatch\CloudWatchClient;
use Aws\Sqs\SqsClient;
use Berlioz\QueueManager\Exception\JobException;
use Berlioz\QueueManager\Exception\QueueException;
use Berlioz\QueueManager\Job\JobDescriptorInterface;
use Berlioz\QueueManager\Job\SqsJob;
use Berlioz\QueueManager\Queue\AwsSqsQueue;
use Berlioz\QueueManager\Queue\MonitorableQueueInterface;
use PHPUnit\Framework\TestCase;

class AwsSqsQueueTest extends TestCase
{
    private SqsClient $sqsClientMock;
    private AwsSqsQueue $queue;

    protected function setUp(): void
    {
        $this->sqsClientMock = $this->createMock(SqsClient::class);
        $this->queue = new AwsSqsQueue(
            $this->sqsClientMock,
            'https://sqs.us-east-1.amazonaws.com/123456789012/testQueue',
            'testQueue',
        );
    }

    public function testGetName(): void
    {
        $this->assertSame('testQueue', $this->queue->getName());
    }

    public function testSize(): void
    {
        $this->sqsClientMock
            ->method('__call')
            ->with(
                'getQueueAttributes',
                [
                    [
                        'QueueUrl' => 'https://sqs.us-east-1.amazonaws.com/123456789012/testQueue',
                        'AttributeNames' => ['ApproximateNumberOfMessagesVisible'],
                    ]
                ]
            )
            ->willReturn(new Result(['Attributes' => ['ApproximateNumberOfMessagesVisible' => '5']]));

        $this->assertSame(5, $this->queue->size());
    }

    public function testMonitorableWithoutCloudWatchClient(): void
    {
        $this->assertInstanceOf(MonitorableQueueInterface::class, $this->queue);

        $this->sqsClientMock
            ->method('__call')
            ->with(
                'getQueueAttributes',
                [
                    [
                        'QueueUrl' => 'https://sqs.us-east-1.amazonaws.com/123456789012/testQueue',
                        'AttributeNames' => ['ApproximateNumberOfMessagesVisible'],
                    ]
                ]
            )
            ->willReturn(new Result(['Attributes' => ['ApproximateNumberOfMessagesVisible' => '3']]));

        $this->assertNull($this->queue->waitTime());
    }

    public function testWaitTimeWithCloudWatchClient(): void
    {
        $sqsClientMock = $this->createMock(SqsClient::class);
        $cloudWatchClientMock = $this->createMock(CloudWatchClient::class);
        $queue = new AwsSqsQueue(
            $sqsClientMock,
            'https://sqs.us-east-1.amazonaws.com/123456789012/testQueue',
            'testQueue',
            cloudWatchClient: $cloudWatchClientMock,
        );

        $sqsClientMock
            ->method('__call')
            ->with(
                'getQueueAttributes',
                [
                    [
                        'QueueUrl' => 'https://sqs.us-east-1.amazonaws.com/123456789012/testQueue',
                        'AttributeNames' => ['ApproximateNumberOfMessagesVisible'],
                    ]
                ]
            )
            ->willReturn(new Result(['Attributes' => ['ApproximateNumberOfMessagesVisible' => '7']]));

        $cloudWatchClientMock
            ->method('__call')
            ->with('getMetricStatistics', $this->isType('array'))
            ->willReturn(new Result([
                'Datapoints' => [
                    ['Maximum' => 42, 'Timestamp' => '2026-01-01T12:00:00Z'],
                ],
            ]));

        $this->assertSame(42, $queue->waitTime());
    }

    public function testDelayed(): void
    {
        $this->sqsClientMock
            ->method('__call')
            ->with(
                'getQueueAttributes',
                [
                    [
                        'QueueUrl' => 'https://sqs.us-east-1.amazonaws.com/123456789012/testQueue',
                        'AttributeNames' => ['ApproximateNumberOfMessagesDelayed'],
                    ]
                ]
            )
            ->willReturn(new Result(['Attributes' => ['ApproximateNumberOfMessagesDelayed' => '9']]));

        $this->assertSame(9, $this->queue->delayed());
    }

    public function testConsumeReturnsJob(): void
    {
        $message = [
            'Attributes' => [],
            'MD5OfBody' => md5($body = '{"test":"value"}'),
            'Body' => $body,
            'MessageId' => 'foo',
            'ReceiptHandle' => 'abc123',
        ];

        $this->sqsClientMock
            ->method('__call')
            ->with(
                'receiveMessage',
                [
                    [
                        'QueueUrl' => 'https://sqs.us-east-1.amazonaws.com/123456789012/testQueue',
                        'VisibilityTimeout' => 30,
                        'AttributeNames' => ['ApproximateReceiveCount'],
                    ]
                ]
            )
            ->willReturn(new Result(['Messages' => [$message]]));

        $job = $this->queue->consume();
        $this->assertInstanceOf(SqsJob::class, $job);
        $this->assertSame('abc123', $job->getAwsResult()['ReceiptHandle']);
    }

    public function testConsumeThrowsChecksumException(): void
    {
        $this->sqsClientMock
            ->method('__call')
            ->with(
                'receiveMessage',
                [
                    [
                        'QueueUrl' => 'https://sqs.us-east-1.amazonaws.com/123456789012/testQueue',
                        'VisibilityTimeout' => 30,
                        'AttributeNames' => ['ApproximateReceiveCount'],
                    ]
                ]
            )
            ->willReturn(
                new Result(
                    [
                        'Messages' => [
                            [
                                'Attributes' => [],
                                'Body' => '{"test":"value"}',
                                'MD5OfBody' => 'invalidchecksum',
                                'MessageId' => 'foo',
                            ]
                        ]
                    ]
                )
            );

        $this->expectException(QueueException::class);
        $this->queue->consume();
    }

    public function testPushReturnsJobId(): void
    {
        $jobDescriptorMock = $this->createMock(JobDescriptorInterface::class);

        $this->sqsClientMock
            ->method('__call')
            ->with(
                'sendMessage',
                [
                    [
                        'DelaySeconds' => 10,
                        'MessageBody' => json_encode($jobDescriptorMock),
                        'QueueUrl' => 'https://sqs.us-east-1.amazonaws.com/123456789012/testQueue',
                    ]
                ]
            )
            ->willReturn(new Result(['MessageId' => 'msg123']));

        $jobId = $this->queue->push($jobDescriptorMock, 10);
        $this->assertSame('msg123', $jobId);
    }

    public function testPurge(): void
    {
        $this->sqsClientMock
            ->expects($this->once())
            ->method('__call')
            ->with(
                'purgeQueue',
                [
                    [
                        'QueueUrl' => 'https://sqs.us-east-1.amazonaws.com/123456789012/testQueue'
                    ]
                ]
            );

        $this->queue->purge();
    }

    public function testRelease(): void
    {
        $jobMock = $this->createMock(SqsJob::class);
        $jobMock->method('getAwsResult')
            ->willReturn(['ReceiptHandle' => 'abc123']);
        $jobMock->method('isReleased')->willReturn(false);
        $jobMock->method('isDeleted')->willReturn(false);

        $this->sqsClientMock
            ->expects($this->once())
            ->method('__call')
            ->with(
                'changeMessageVisibility',
                [
                    [
                        'QueueUrl' => 'https://sqs.us-east-1.amazonaws.com/123456789012/testQueue',
                        'ReceiptHandle' => 'abc123',
                        'VisibilityTimeout' => 5,
                    ]
                ]
            );

        $this->queue->release($jobMock, 5);
    }

    public function testReleaseThrowsIfAlreadyReleased(): void
    {
        $jobMock = $this->createMock(SqsJob::class);
        $jobMock->method('isReleased')->willReturn(true);

        $this->sqsClientMock
            ->expects($this->never())
            ->method('__call');

        $this->expectException(JobException::class);

        $this->queue->release($jobMock, 5);
    }

    public function testDelete(): void
    {
        $jobMock = $this->createMock(SqsJob::class);
        $jobMock->method('getAwsResult')
            ->willReturn(['ReceiptHandle' => 'abc123']);
        $jobMock->method('isDeleted')->willReturn(false);

        $this->sqsClientMock
            ->expects($this->once())
            ->method('__call')
            ->with(
                'deleteMessage',
                [
                    [
                        'QueueUrl' => 'https://sqs.us-east-1.amazonaws.com/123456789012/testQueue',
                        'ReceiptHandle' => 'abc123',
                    ]
                ]
            );

        $this->queue->delete($jobMock);
    }

    public function testDeleteThrowsIfAlreadyDeleted(): void
    {
        $jobMock = $this->createMock(SqsJob::class);
        $jobMock->method('isDeleted')->willReturn(true);

        $this->sqsClientMock
            ->expects($this->never())
            ->method('__call');

        $this->expectException(JobException::class);

        $this->queue->delete($jobMock);
    }
}
