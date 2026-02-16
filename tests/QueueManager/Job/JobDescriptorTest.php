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
use PHPUnit\Framework\TestCase;

class JobDescriptorTest extends TestCase
{
    private function createJobDescriptor(): JobDescriptor
    {
        return new JobDescriptor(
            name: 'bar',
            payload: ['foo' => 'bar'],
        );
    }

    public function testGetName(): void
    {
        $job = $this->createJobDescriptor();
        $this->assertEquals('bar', $job->getName());
    }

    public function testGetPayload(): void
    {
        $job = $this->createJobDescriptor();
        $this->assertEquals(['foo' => 'bar'], $job->getPayload()->getArrayCopy());
    }
}
