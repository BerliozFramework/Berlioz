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

namespace Berlioz\QueueManager\Tests\Job;

use Berlioz\QueueManager\Job\JobDescriptor;
use Berlioz\QueueManager\Job\JobInterface;
use Berlioz\QueueManager\Queue\QueueInterface;
use PHPUnit\Framework\TestCase;

abstract class JobTestCase extends TestCase
{
    abstract protected function newQueue(): QueueInterface;

    protected function newJob(): JobInterface
    {
        $queue = $this->newQueue();
        $queue->push(new JobDescriptor('bar', ['foo' => 'value']));

        return $queue->consume();
    }

    public function testGetId(): void
    {
        $queue = $this->newQueue();
        $jobId = $queue->push(new JobDescriptor('test', ['foo' => 'value']));
        $job = $queue->consume();

        $this->assertEquals($jobId, $job->getId());
    }

    public function testGetName(): void
    {
        $job = $this->newJob();

        $this->assertEquals('bar', $job->getName());
    }

    public function testGetAttempts(): void
    {
        $job = $this->newJob();

        $this->assertEquals(1, $job->getAttempts());

        $job->release();
        $job = $job->getQueue()->consume();

        $this->assertEquals(2, $job->getAttempts());
    }

    public function testGetPayload(): void
    {
        $job = $this->newJob();

        $this->assertEquals(['foo' => 'value'], $job->getPayload()->getArrayCopy());
    }

    public function testGetQueue(): void
    {
        $queue = $this->newQueue();
        $queue->push(new JobDescriptor('test', ['foo' => 'value']));
        $job = $queue->consume();

        $this->assertInstanceOf($queue::class, $job->getQueue());
        $this->assertEquals('default', $job->getQueue()->getName());
    }

    public function testRelease(): void
    {
        $job = $this->newJob();

        $this->assertEquals(0, $job->getQueue()->size());

        $job->release();

        $this->assertEquals(1, $job->getQueue()->size());
    }

    public function testRelease_withDelay(): void
    {
        $job = $this->newJob();

        $this->assertEquals(0, $job->getQueue()->size());

        $job->getQueue()->release($job, 2);

        $this->assertEquals(0, $job->getQueue()->size());

        sleep(3);

        $this->assertEquals(1, $job->getQueue()->size());
    }

    public function testDelete(): void
    {
        $job = $this->newJob();

        $job->delete();

        $this->assertEquals(0, $job->getQueue()->size());
    }
}
