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

declare(strict_types=1);

namespace Berlioz\Package\QueueManager\Tests\Factory;

use Berlioz\Package\QueueManager\Factory\NullQueueFactory;
use Berlioz\QueueManager\Queue\NullQueue;
use PHPUnit\Framework\TestCase;

class NullQueueFactoryTest extends TestCase
{
    public function testGetQueueClass(): void
    {
        $this->assertSame(NullQueue::class, NullQueueFactory::getQueueClass());
    }

    public function testCreateFromConfig(): void
    {
        $queues = iterator_to_array(NullQueueFactory::createFromConfig([
            'name' => [
                'queue1',
                ['name' => 'queue2'],
                [],
            ],
        ]));

        $this->assertCount(3, $queues);
        $this->assertContainsOnlyInstancesOf(NullQueue::class, $queues);
        $this->assertSame('queue1', $queues[0]->getName());
        $this->assertSame('queue2', $queues[1]->getName());
        $this->assertSame('NULL', $queues[2]->getName());
    }

    public function testCreateFromConfig_singleName(): void
    {
        $queues = iterator_to_array(NullQueueFactory::createFromConfig(['name' => 'foo']));

        $this->assertCount(1, $queues);
        $this->assertSame('foo', $queues[0]->getName());
    }

    public function testCreateFromConfig_withoutName(): void
    {
        $this->assertSame([], iterator_to_array(NullQueueFactory::createFromConfig([])));
    }
}
