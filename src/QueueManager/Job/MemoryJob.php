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

declare(strict_types=1);

namespace Berlioz\QueueManager\Job;

use Berlioz\QueueManager\Exception\QueueException;
use Berlioz\QueueManager\Queue\MemoryQueue;

class MemoryJob extends Job
{
    public function __construct(
        string $id,
        ?string $name,
        int $attempts,
        PayloadInterface|array $payload,
        protected readonly MemoryQueue $queue,
    ) {
        parent::__construct($id, $name, $attempts, $payload);
    }

    /**
     * @inheritDoc
     */
    public function getQueue(): MemoryQueue
    {
        return $this->queue;
    }

    /**
     * @inheritDoc
     * @throws QueueException
     */
    public function release(int $delay = 0): void
    {
        $this->queue->release($this, $delay);
        parent::release($delay);
    }

    /**
     * @inheritDoc
     */
    public function delete(): void
    {
        $this->queue->delete($this);
        parent::delete();
    }
}
