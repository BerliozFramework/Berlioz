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

use Berlioz\QueueManager\Queue\QueueInterface;
use Berlioz\QueueManager\Tests\Queue\MemoryQueueTest;

class MemoryJobTest extends JobTestCase
{
    protected function newQueue(): QueueInterface
    {
        return MemoryQueueTest::newQueue();
    }
}
