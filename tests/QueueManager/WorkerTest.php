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

use Berlioz\QueueManager\Handler\JobHandlerInterface;
use Berlioz\QueueManager\Job\JobDescriptor;
use Berlioz\QueueManager\Job\JobInterface;
use Berlioz\QueueManager\Queue\MemoryQueue;
use Berlioz\QueueManager\Queue\QueueInterface;
use Berlioz\QueueManager\RateLimiter\RateLimiterInterface;
use Berlioz\QueueManager\Worker;
use Berlioz\QueueManager\WorkerExit;
use Berlioz\QueueManager\WorkerOptions;
use Closure;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;

class WorkerTest extends TestCase
{
    private JobHandlerInterface $jobHandlerMock;
    private QueueInterface $queueMock;
    private Worker $worker;

    protected function setUp(): void
    {
        $this->jobHandlerMock = $this->createMock(JobHandlerInterface::class);
        $this->queueMock = $this->createMock(QueueInterface::class);

        $this->worker = new Worker($this->jobHandlerMock);
    }

    public function testMemoryLimitExceeded(): void
    {
        $workerOptions = new WorkerOptions(memoryLimit: 10); // 10 MB

        $workerStub = $this->getMockBuilder(Worker::class)
            ->setConstructorArgs([$this->jobHandlerMock])
            ->onlyMethods(['getMemoryUsage'])
            ->getMock();

        $workerStub->method('getMemoryUsage')->willReturn(15); // Simulate 15 MB usage

        $this->assertTrue($workerStub->memoryLimitExceeded($workerOptions));
    }

    public function testTimeLimitExceeded(): void
    {
        $workerOptions = new WorkerOptions(timeLimit: 1); // 1 second
        $startTime = 0;

        $workerStub = $this->getMockBuilder(Worker::class)
            ->setConstructorArgs([$this->jobHandlerMock])
            ->onlyMethods(['hrtime'])
            ->getMock();

        $workerStub->method('hrtime')->willReturn(2_000_000_000); // Simulate 2 seconds in nanoseconds

        $this->assertTrue($workerStub->timeLimitExceeded($startTime, $workerOptions));
    }

    public function testKillFileExists(): void
    {
        $killFile = tempnam(sys_get_temp_dir(), 'berlioz');
        $workerOptions = new WorkerOptions(killFilePath: $killFile);

        $this->assertTrue($this->worker->killFileExists($workerOptions));

        unlink($workerOptions->killFilePath);
    }

    public function testContinueShouldTerminate(): void
    {
        $workerStub = $this->getMockBuilder(Worker::class)
            ->setConstructorArgs([$this->jobHandlerMock])
            ->onlyMethods([])
            ->getMock();

        $reflection = new ReflectionClass(Worker::class);
        $property = $reflection->getProperty('shouldTerminate');
        $property->setValue($workerStub, true);

        $workerExit = $workerStub->continue(new WorkerOptions(), 0, null, 0);

        $this->assertSame(WorkerExit::SHOULD_TERMINATE, $workerExit);
    }

    public function testExecuteJobCallsJobHandler(): void
    {
        $jobMock = $this->createMock(JobInterface::class);

        $this->jobHandlerMock
            ->expects($this->once())
            ->method('handle')
            ->with($jobMock);

        $this->worker->executeJob($jobMock);
    }

    public function testRunProcessesJobSuccessfully(): void
    {
        $loggerMock = $this->createMock(LoggerInterface::class);
        $jobMock = $this->createMock(JobInterface::class);

        $this->worker->setLogger($loggerMock);

        $this->queueMock->method('consume')->willReturnOnConsecutiveCalls($jobMock, null);
        $jobMock->expects($this->once())->method('delete');
        $jobMock->method('getId')->willReturn('Job123');
        $jobMock->method('getQueue')->willReturn($this->queueMock);

        $loggerMock
            ->expects($this->exactly(4))
            ->method('info')
            ->willReturnOnConsecutiveCalls(
                [$this->stringContains('Start worker on queue(s):')],
                [$this->stringContains('Job Job123 consumed from queue')],
                [$this->stringContains('Job Job123 executed from queue')],
                [$this->stringContains('Exit')]
            );

        $exitCode = $this->worker->run($this->queueMock, new WorkerOptions(limit: 1));

        $this->assertSame(WorkerExit::LIMIT_EXCEEDED->code(), $exitCode);
    }

    public function testRunHandlesJobException(): void
    {
        $loggerMock = $this->createMock(LoggerInterface::class);
        $jobMock = $this->createMock(JobInterface::class);

        $this->worker->setLogger($loggerMock);

        $this->queueMock->method('consume')->willReturn($jobMock);
        $jobMock->method('getId')->willReturn('Job123');
        $jobMock->method('getQueue')->willReturn($this->queueMock);

        $this->jobHandlerMock->method('handle')->willThrowException(new Exception('Job failed'));

        $jobMock->expects($this->once())->method('release');
        $loggerMock->expects($this->once())->method('error');

        $exitCode = $this->worker->run($this->queueMock, new WorkerOptions(limit: 1));
        $this->assertSame(WorkerExit::LIMIT_EXCEEDED->code(), $exitCode);
    }

    public function testRunExitsWhenKillFileExists(): void
    {
        $loggerMock = $this->createMock(LoggerInterface::class);
        $this->worker->setLogger($loggerMock);

        $killFile = tempnam(sys_get_temp_dir(), 'berlioz');

        $workerOptions = new WorkerOptions(killFilePath: $killFile);
        $exitCode = $this->worker->run($this->queueMock, $workerOptions);

        $this->assertSame(WorkerExit::SHOULD_TERMINATE->code(), $exitCode);

        unlink($killFile); // Cleanup
    }

    public function testRunWaitOnRateLimiter(): void
    {
        $rateLimiterMock = $this->createMock(RateLimiterInterface::class);
        $jobMock = $this->createMock(JobInterface::class);
        $options = new WorkerOptions(limit: 1, rateLimiter: $rateLimiterMock);

        // Mock rate limiter behavior: reached limit, so it must wait
        $rateLimiterMock->expects($this->once())->method('reached')->willReturn(true);
        $rateLimiterMock->expects($this->once())->method('wait');

        // After consuming the job, it must pop from limiter
        $rateLimiterMock->expects($this->once())->method('pop');

        $this->queueMock->method('consume')->willReturn($jobMock);
        $jobMock->method('getId')->willReturn('Job123');
        $jobMock->method('getQueue')->willReturn($this->queueMock);

        $exitCode = $this->worker->run($this->queueMock, $options);

        $this->assertSame(WorkerExit::LIMIT_EXCEEDED->code(), $exitCode);
    }

    public static function provideJobAndOptionsForDelay(): array
    {
        return [
            [
                'jobAttempts' => 1,
                'backoffTime' => 30,
                'backoffMultiplier' => 2,
                'exceptedDelay' => 30,
            ],
            [
                'jobAttempts' => 2,
                'backoffTime' => 30,
                'backoffMultiplier' => 2,
                'exceptedDelay' => 60,
            ],
            [
                'jobAttempts' => 3,
                'backoffTime' => 30,
                'backoffMultiplier' => 2,
                'exceptedDelay' => 120,
            ],
            [
                'jobAttempts' => 4,
                'backoffTime' => 30,
                'backoffMultiplier' => 2,
                'exceptedDelay' => 240,
            ],
            [
                'jobAttempts' => 3,
                'backoffTime' => 30,
                'backoffMultiplier' => 1,
                'exceptedDelay' => 30,
            ],
            [
                'jobAttempts' => 3,
                'backoffTime' => 1,
                'backoffMultiplier' => 2,
                'exceptedDelay' => 4,
            ],
        ];
    }

    #[DataProvider('provideJobAndOptionsForDelay')]
    public function testNextDelayAfterFailure(
        int $jobAttempts,
        int $backoffTime,
        int $backoffMultiplier,
        int $exceptedDelay
    ): void {
        $options = new WorkerOptions(backoffTime: $backoffTime, backoffMultiplier: $backoffMultiplier);
        $jobMock = $this->createMock(JobInterface::class);
        $jobMock->method('getAttempts')->willReturn($jobAttempts);

        $this->assertEquals($exceptedDelay, $this->worker->nextDelayAfterFailure($jobMock, $options));
    }

    public function testRunJobAlreadyDeletedByHandler(): void
    {
        $jobMock = $this->createMock(JobInterface::class);

        $this->queueMock->method('consume')->willReturn($jobMock);
        $jobMock->method('getId')->willReturn('Job123');
        $jobMock->method('getQueue')->willReturn($this->queueMock);

        // Handler deletes the job itself
        $this->jobHandlerMock->method('handle')->willReturnCallback(
            function (JobInterface $job) use ($jobMock): void {
                // Simulate that the handler called $job->delete()
                $jobMock->method('isDeleted')->willReturn(true);
            }
        );

        // delete() should NOT be called by the Worker since handler already did it
        $jobMock->expects($this->never())->method('delete');
        $jobMock->expects($this->never())->method('release');

        $exitCode = $this->worker->run($this->queueMock, new WorkerOptions(limit: 1));
        $this->assertSame(WorkerExit::LIMIT_EXCEEDED->code(), $exitCode);
    }

    public function testRunJobAlreadyReleasedByHandler(): void
    {
        $jobMock = $this->createMock(JobInterface::class);

        $this->queueMock->method('consume')->willReturn($jobMock);
        $jobMock->method('getId')->willReturn('Job123');
        $jobMock->method('getQueue')->willReturn($this->queueMock);

        // Handler releases the job itself
        $this->jobHandlerMock->method('handle')->willReturnCallback(
            function (JobInterface $job) use ($jobMock): void {
                // Simulate that the handler called $job->release()
                $jobMock->method('isReleased')->willReturn(true);
            }
        );

        // Neither delete() nor release() should be called by the Worker
        $jobMock->expects($this->never())->method('delete');
        $jobMock->expects($this->never())->method('release');

        $exitCode = $this->worker->run($this->queueMock, new WorkerOptions(limit: 1));
        $this->assertSame(WorkerExit::LIMIT_EXCEEDED->code(), $exitCode);
    }

    public function testRunJobAlreadyReleasedByHandlerThenException(): void
    {
        $jobMock = $this->createMock(JobInterface::class);

        $this->queueMock->method('consume')->willReturn($jobMock);
        $jobMock->method('getId')->willReturn('Job123');
        $jobMock->method('getQueue')->willReturn($this->queueMock);
        $jobMock->method('isReleased')->willReturn(true);

        // Handler releases the job then throws
        $this->jobHandlerMock->method('handle')->willThrowException(new Exception('Job failed'));

        // release() should NOT be called again by the Worker
        $jobMock->expects($this->never())->method('release');
        $jobMock->expects($this->never())->method('delete');

        $exitCode = $this->worker->run($this->queueMock, new WorkerOptions(limit: 1));
        $this->assertSame(WorkerExit::LIMIT_EXCEEDED->code(), $exitCode);
    }

    public function testDrain(): void
    {
        $queue = new MemoryQueue();
        $queue->push(new JobDescriptor('foo', ['id' => 1]));
        $queue->push(new JobDescriptor('foo', ['id' => 2]));
        $handled = [];

        $this->jobHandlerMock->method('handle')->willReturnCallback(
            function (JobInterface $job) use ($queue, &$handled): void {
                $handled[] = $job->getPayload()->get('id');

                // Job pushed while draining
                if (1 === $job->getPayload()->get('id')) {
                    $queue->push(new JobDescriptor('foo', ['id' => 3]));
                }
            }
        );

        $this->assertSame(3, $this->worker->drain($queue));
        $this->assertEqualsCanonicalizing([1, 2, 3], $handled);
        $this->assertSame(0, $queue->size());
    }

    public function testDrain_emptyQueue(): void
    {
        $this->jobHandlerMock->expects($this->never())->method('handle');

        $this->assertSame(0, $this->worker->drain(new MemoryQueue()));
    }

    public function testDrain_failedJobIsReleased(): void
    {
        $queue = new MemoryQueue(maxAttempts: 2);
        $queue->push(new JobDescriptor('foo', []));
        $this->jobHandlerMock->method('handle')->willThrowException(new Exception('Job failed'));

        // Released without delay (backoff 0), so retried until max attempts
        $this->assertSame(2, $this->worker->drain($queue));
        $this->assertSame(0, $queue->size());
    }

    public function testDrain_timeLimit(): void
    {
        $queue = new MemoryQueue();
        $queue->push(new JobDescriptor('foo', []));
        $queue->push(new JobDescriptor('foo', []));

        $workerStub = $this->getMockBuilder(Worker::class)
            ->setConstructorArgs([$this->jobHandlerMock])
            ->onlyMethods(['hrtime'])
            ->getMock();
        // Start, 1st check, 1st job execution, 2nd check (time limit of 1 second exceeded)
        $workerStub->method('hrtime')->willReturnOnConsecutiveCalls(0, 0, 0, 2_000_000_000);

        $this->assertSame(1, $workerStub->drain($queue, timeLimit: 1));
        $this->assertSame(1, $queue->size());
    }

    #[RunInSeparateProcess]
    public function testSignalHandler_onlyInstalledByRun(): void
    {
        if (!extension_loaded('pcntl')) {
            $this->markTestSkipped('pcntl extension required');
        }

        $worker = new Worker($this->createMock(JobHandlerInterface::class));
        $worker->drain(new MemoryQueue());

        $this->assertSame(SIG_DFL, pcntl_signal_get_handler(SIGTERM));

        $worker->run(new MemoryQueue(), new WorkerOptions(stopNoJob: true, sleepNoJob: 0));

        $this->assertInstanceOf(Closure::class, pcntl_signal_get_handler(SIGTERM));
    }

    public function testRunJobAlreadyDeletedByHandlerThenException(): void
    {
        $jobMock = $this->createMock(JobInterface::class);

        $this->queueMock->method('consume')->willReturn($jobMock);
        $jobMock->method('getId')->willReturn('Job123');
        $jobMock->method('getQueue')->willReturn($this->queueMock);
        $jobMock->method('isDeleted')->willReturn(true);

        // Handler deletes the job then throws
        $this->jobHandlerMock->method('handle')->willThrowException(new Exception('Job failed'));

        // Neither delete() nor release() should be called by the Worker
        $jobMock->expects($this->never())->method('release');
        $jobMock->expects($this->never())->method('delete');

        $exitCode = $this->worker->run($this->queueMock, new WorkerOptions(limit: 1));
        $this->assertSame(WorkerExit::LIMIT_EXCEEDED->code(), $exitCode);
    }
}
