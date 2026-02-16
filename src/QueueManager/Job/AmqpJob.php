<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2025 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\QueueManager\Job;

use AMQPEnvelope;
use Berlioz\QueueManager\Queue\AmqpQueue;

class AmqpJob extends Job
{
    public function __construct(
        protected AMQPEnvelope $envelope,
        protected readonly AmqpQueue $queue,
    ) {
        $payload = json_decode($this->envelope->getBody(), true);

        parent::__construct(
            id: $envelope->getHeader('jobId') ?? $this->envelope->getDeliveryTag(),
            name: $payload['jobName'] ?? null,
            attempts: ($envelope->getHeader('attempts') ?? 0) + 1,
            payload: $payload,
        );
    }

    /**
     * @inheritDoc
     */
    public function getQueue(): AmqpQueue
    {
        return $this->queue;
    }

    /**
     * Get AMQP envelope.
     *
     * @return AMQPEnvelope
     */
    public function getAmqpEnvelope(): AMQPEnvelope
    {
        return $this->envelope;
    }

    /**
     * @inheritDoc
     */
    public function release(int $delay = 0): void
    {
        $this->getQueue()->release($this, $delay);
        parent::release($delay);
    }

    /**
     * @inheritDoc
     */
    public function delete(): void
    {
        $this->getQueue()->delete($this);
        parent::delete();
    }
}
