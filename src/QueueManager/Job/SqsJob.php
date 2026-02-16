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

namespace Berlioz\QueueManager\Job;

use Berlioz\QueueManager\Queue\AwsSqsQueue;

class SqsJob extends Job
{
    public function __construct(
        protected array $awsResult,
        protected readonly AwsSqsQueue $queue,
    ) {
        $payload = json_decode($this->awsResult['Body'], true);

        parent::__construct(
            id: $this->awsResult['MessageId'],
            name: $payload['jobName'] ?? null,
            attempts: (int)(($this->awsResult['Attributes'] ?? [])['ApproximateReceiveCount'] ?? 0),
            payload: $payload,
        );
    }

    /**
     * @inheritDoc
     */
    public function getQueue(): AwsSqsQueue
    {
        return $this->queue;
    }

    /**
     * Get AWS result.
     *
     * @return array
     */
    public function getAwsResult(): array
    {
        return $this->awsResult;
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
