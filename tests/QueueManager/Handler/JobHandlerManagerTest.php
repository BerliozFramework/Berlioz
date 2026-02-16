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

namespace Berlioz\QueueManager\Tests\Handler;

use Berlioz\QueueManager\Exception\QueueManagerException;
use Berlioz\QueueManager\Handler\JobHandlerInterface;
use Berlioz\QueueManager\Handler\JobHandlerManager;
use Berlioz\QueueManager\Job\JobInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use stdClass;

class JobHandlerManagerTest extends TestCase
{
    private ContainerInterface $containerMock;
    private JobHandlerManager $jobHandlerManager;

    protected function setUp(): void
    {
        $this->containerMock = $this->createMock(ContainerInterface::class);
        $this->jobHandlerManager = new JobHandlerManager($this->containerMock);
    }

    public function testAddHandlerSuccessfullyAddsHandler(): void
    {
        $result = $this->jobHandlerManager->addHandler('TestJob', JobHandlerInterface::class);
        $this->assertSame($this->jobHandlerManager, $result);
    }

    public function testAddHandlerThrowsExceptionIfHandlerExists(): void
    {
        $this->jobHandlerManager->addHandler('TestJob', JobHandlerInterface::class);

        $this->expectException(QueueManagerException::class);
        $this->expectExceptionMessage('A job handler already exists for job "TestJob".');

        $this->jobHandlerManager->addHandler('TestJob', JobHandlerInterface::class);
    }

    public function testHandleUsesCorrectHandler(): void
    {
        $jobMock = $this->createMock(JobInterface::class);
        $handlerMock = $this->createMock(JobHandlerInterface::class);

        $jobMock->method('getName')->willReturn('TestJob');
        $this->containerMock
            ->method('get')
            ->with(JobHandlerInterface::class)
            ->willReturn($handlerMock);

        $handlerMock
            ->expects($this->once())
            ->method('handle')
            ->with($jobMock);

        $this->jobHandlerManager->addHandler('TestJob', JobHandlerInterface::class);
        $this->jobHandlerManager->handle($jobMock);
    }

    public function testHandleUsesCorrectHandlerWithWildcard(): void
    {
        $jobMock = $this->createMock(JobInterface::class);
        $handlerMock = $this->createMock(JobHandlerInterface::class);

        $jobMock->method('getName')->willReturn('TestJob');
        $this->containerMock
            ->method('get')
            ->with(JobHandlerInterface::class)
            ->willReturn($handlerMock);

        $handlerMock
            ->expects($this->once())
            ->method('handle')
            ->with($jobMock);

        $this->jobHandlerManager->addHandler('Test*', JobHandlerInterface::class);
        $this->jobHandlerManager->handle($jobMock);
    }

    public function testHandleThrowsExceptionIfNoHandlerAndNoDefault(): void
    {
        $jobMock = $this->createMock(JobInterface::class);
        $jobMock->method('getName')->willReturn('UnknownJob');

        $this->expectException(QueueManagerException::class);
        $this->expectExceptionMessage('Invalid job handler for job `UnknownJob`.');

        $this->jobHandlerManager->handle($jobMock);
    }

    public function testHandleUsesDefaultHandlerIfNoSpecificHandlerExists(): void
    {
        $defaultHandlerMock = $this->createMock(JobHandlerInterface::class);
        $jobMock = $this->createMock(JobInterface::class);

        $jobMock->method('getName')->willReturn('UnknownJob');

        $defaultHandlerMock
            ->expects($this->once())
            ->method('handle')
            ->with($jobMock);

        $jobHandlerManager = new JobHandlerManager($this->containerMock, $defaultHandlerMock);
        $jobHandlerManager->handle($jobMock);
    }

    public function testHandleThrowsExceptionIfHandlerFromContainerIsInvalid(): void
    {
        $jobMock = $this->createMock(JobInterface::class);
        $jobMock->method('getName')->willReturn('TestJob');

        $this->containerMock
            ->method('get')
            ->with(JobHandlerInterface::class)
            ->willReturn(new stdClass()); // Not a valid JobHandlerInterface

        $this->jobHandlerManager->addHandler('TestJob', JobHandlerInterface::class);

        $this->expectException(QueueManagerException::class);
        $this->expectExceptionMessage('Invalid job handler for job `TestJob`.');

        $this->jobHandlerManager->handle($jobMock);
    }

    public function testDebugInfoReturnsCorrectStructure(): void
    {
        $debugInfo = $this->jobHandlerManager->__debugInfo();

        $this->assertArrayHasKey('handlers', $debugInfo);
        $this->assertArrayHasKey('container', $debugInfo);
        $this->assertSame('**CONTAINER**', $debugInfo['container']);
    }
}
