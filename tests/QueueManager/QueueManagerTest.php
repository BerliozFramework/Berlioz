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

namespace Berlioz\QueueManager\Tests;

use Berlioz\QueueManager\Exception\QueueException;
use Berlioz\QueueManager\Exception\QueueManagerException;
use Berlioz\QueueManager\Job\JobDescriptorInterface;
use Berlioz\QueueManager\Job\JobInterface;
use Berlioz\QueueManager\Queue\PurgeableQueueInterface;
use Berlioz\QueueManager\Queue\QueueInterface;
use Berlioz\QueueManager\QueueManager;
use Berlioz\QueueManager\RateLimiter\NullRateLimiter;
use Berlioz\QueueManager\RateLimiter\RateLimiterInterface;
use PHPUnit\Framework\TestCase;

class QueueManagerTest extends TestCase
{
    private QueueInterface $primaryQueueMock;
    private QueueInterface $secondaryQueueMock;
    private QueueManager $queueManager;

    protected function setUp(): void
    {
        $this->primaryQueueMock = $this->createMock(QueueInterface::class);
        $this->secondaryQueueMock = $this->createMock(QueueInterface::class);

        $this->primaryQueueMock->method('getName')->willReturn('PrimaryQueue');
        $this->secondaryQueueMock->method('getName')->willReturn('SecondaryQueue');

        $this->queueManager = new QueueManager($this->primaryQueueMock, $this->secondaryQueueMock);
    }

    public function testCount(): void
    {
        $this->assertCount(2, $this->queueManager);
    }

    public function testGetQueues(): void
    {
        $queues = iterator_to_array($this->queueManager->getQueues(), false);

        $this->assertCount(2, $queues);
        $this->assertSame($this->primaryQueueMock, $queues[0]);
        $this->assertSame($this->secondaryQueueMock, $queues[1]);
    }

    public function testFilterReturnsFilteredQueueManager(): void
    {
        $filteredQueueManager = $this->queueManager->filter('SecondaryQueue');

        $filteredQueues = iterator_to_array($filteredQueueManager->getQueues(), false);
        $this->assertCount(1, $filteredQueues);
        $this->assertSame($this->secondaryQueueMock, $filteredQueues[0]);
    }

    public function testFilterReturnsFilteredQueueManagerWithWildcard(): void
    {
        $filteredQueueManager = $this->queueManager->filter('Secondary*');

        $filteredQueues = iterator_to_array($filteredQueueManager->getQueues(), false);
        $this->assertCount(1, $filteredQueues);
        $this->assertSame($this->secondaryQueueMock, $filteredQueues[0]);
    }

    public function testFilterReturnsFilteredQueueManagerOrder(): void
    {
        $filteredQueueManager = $this->queueManager->filter('Secondary*', 'Primary*');

        $filteredQueues = iterator_to_array($filteredQueueManager->getQueues(), false);
        $this->assertCount(2, $filteredQueues);
        $this->assertSame($this->secondaryQueueMock, $filteredQueues[0]);
        $this->assertSame($this->primaryQueueMock, $filteredQueues[1]);
    }

    public function testFilterThrowsExceptionIfQueueNotFound(): void
    {
        $this->expectException(QueueManagerException::class);
        $this->expectExceptionMessage('Queue `NonExistentQueue` not found');

        $this->queueManager->filter('NonExistentQueue');
    }

    public function testStats(): void
    {
        $this->primaryQueueMock->method('size')->willReturn(5);
        $this->secondaryQueueMock->method('size')->willReturn(10);

        $deprecationMessage = null;
        set_error_handler(function (int $errno, string $errstr) use (&$deprecationMessage): bool {
            if ($errno !== E_USER_DEPRECATED) {
                return false;
            }

            $deprecationMessage = $errstr;

            return true;
        });

        try {
            $this->assertEquals(
                [
                    'PrimaryQueue' => 5,
                    'SecondaryQueue' => 10
                ],
                iterator_to_array($this->queueManager->stats())
            );
        } finally {
            restore_error_handler();
        }

        $this->assertSame(
            'QueueManager::stats() is deprecated, iterate over getQueues() for detailed metrics or use size() for total count.',
            $deprecationMessage,
        );
    }

    public function testGetName(): void
    {
        $this->assertSame('PrimaryQueue, SecondaryQueue', $this->queueManager->getName());
    }

    public function testSize(): void
    {
        $this->primaryQueueMock->method('size')->willReturn(5);
        $this->secondaryQueueMock->method('size')->willReturn(10);

        $this->assertSame(15, $this->queueManager->size());
    }

    public function testGetRateLimiter(): void
    {
        $this->assertInstanceOf(NullRateLimiter::class, $this->queueManager->getRateLimiter());
    }

    public function testConsumeReturnsJobFromFirstQueue(): void
    {
        $jobMock = $this->createMock(JobInterface::class);
        $this->primaryQueueMock->method('consume')->willReturn($jobMock);

        $job = $this->queueManager->consume();

        $this->assertSame($jobMock, $job);
    }

    public function testConsumeReturnsJobFromSecondQueueIfFirstIsEmpty(): void
    {
        $jobMock = $this->createMock(JobInterface::class);
        $this->primaryQueueMock->method('consume')->willReturn(null);
        $this->secondaryQueueMock->method('consume')->willReturn($jobMock);

        $job = $this->queueManager->consume();

        $this->assertSame($jobMock, $job);
    }

    public function testConsumeReturnsNullIfNoJobsAvailable(): void
    {
        $this->primaryQueueMock->method('consume')->willReturn(null);
        $this->secondaryQueueMock->method('consume')->willReturn(null);

        $this->assertNull($this->queueManager->consume());
    }

    public function testConsumeSkipsQueueWhenRateLimiterReached(): void
    {
        $primaryLimiter = $this->createMock(RateLimiterInterface::class);
        $primaryLimiter->method('reached')->willReturn(true);
        $this->primaryQueueMock->method('getRateLimiter')->willReturn($primaryLimiter);
        $this->primaryQueueMock
            ->expects($this->never())
            ->method('consume');

        $jobMock = $this->createMock(JobInterface::class);
        $secondaryLimiter = $this->createMock(RateLimiterInterface::class);
        $secondaryLimiter->method('reached')->willReturn(false);
        $this->secondaryQueueMock->method('getRateLimiter')->willReturn($secondaryLimiter);
        $this->secondaryQueueMock->method('consume')->willReturn($jobMock);

        $job = $this->queueManager->consume();

        $this->assertSame($jobMock, $job);
    }

    public function testPushToSpecificQueue(): void
    {
        $jobDescriptorMock = $this->createMock(JobDescriptorInterface::class);

        $this->secondaryQueueMock
            ->expects($this->once())
            ->method('push')
            ->with($jobDescriptorMock, 0)
            ->willReturn('JobID123');

        $jobId = $this->queueManager->push($jobDescriptorMock, 0, 'SecondaryQueue');

        $this->assertSame('JobID123', $jobId);
    }

    public function testPushThrowsExceptionIfQueueNotFound(): void
    {
        $jobDescriptorMock = $this->createMock(JobDescriptorInterface::class);

        $this->expectException(QueueException::class);
        $this->expectExceptionMessage('No queue found to push job');

        $this->queueManager->push($jobDescriptorMock, 0, 'NonExistentQueue');
    }

    public function testPushUsesPrimaryQueueByDefault(): void
    {
        $jobDescriptorMock = $this->createMock(JobDescriptorInterface::class);

        $this->primaryQueueMock
            ->expects($this->once())
            ->method('push')
            ->with($jobDescriptorMock, 0)
            ->willReturn('JobID456');

        $jobId = $this->queueManager->push($jobDescriptorMock);

        $this->assertSame('JobID456', $jobId);
    }

    public function testPushRawToSpecificQueue(): void
    {
        $payload = ['data' => 'value'];

        $this->secondaryQueueMock
            ->expects($this->once())
            ->method('pushRaw')
            ->with($payload, 0)
            ->willReturn('JobID789');

        $jobId = $this->queueManager->pushRaw($payload, 0, 'SecondaryQueue');

        $this->assertSame('JobID789', $jobId);
    }

    public function testPushRawThrowsExceptionIfQueueNotFound(): void
    {
        $payload = ['data' => 'value'];

        $this->expectException(QueueException::class);
        $this->expectExceptionMessage('No queue found to push job');

        $this->queueManager->pushRaw($payload, 0, 'NonExistentQueue');
    }

    public function testPushRawUsesPrimaryQueueByDefault(): void
    {
        $payload = ['data' => 'value'];

        $this->primaryQueueMock
            ->expects($this->once())
            ->method('pushRaw')
            ->with($payload, 0)
            ->willReturn('JobID111');

        $jobId = $this->queueManager->pushRaw($payload);

        $this->assertSame('JobID111', $jobId);
    }

    public function testPushRawPassesDelayToPrimaryQueue(): void
    {
        $payload = ['data' => 'value'];

        $this->primaryQueueMock
            ->expects($this->once())
            ->method('pushRaw')
            ->with($payload, 30)
            ->willReturn('JobID222');

        $jobId = $this->queueManager->pushRaw($payload, 30);

        $this->assertSame('JobID222', $jobId);
    }

    public function testPurge(): void
    {
        $purgeableQueueMock = $this->createMock(PurgeableQueueInterface::class);
        $purgeableQueueMock
            ->expects($this->once())
            ->method('purge');

        $queueManager = new QueueManager($this->primaryQueueMock, $purgeableQueueMock);
        $queueManager->purge();
    }
}
