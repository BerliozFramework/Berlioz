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

namespace Berlioz\QueueManager\Queue;

use AMQPChannel;
use AMQPConnection;
use AMQPExchange;
use Berlioz\QueueManager\Exception\JobException;
use Berlioz\QueueManager\Exception\QueueException;
use Berlioz\QueueManager\Exception\QueueManagerException;
use Berlioz\QueueManager\Job\AmqpJob;
use Berlioz\QueueManager\Job\JobDescriptorInterface;
use Berlioz\QueueManager\Job\JobInterface;
use Berlioz\QueueManager\RateLimiter\NullRateLimiter;
use Berlioz\QueueManager\RateLimiter\RateLimiterInterface;
use DateInterval;
use DateTimeInterface;
use Exception;

extension_loaded('amqp') || throw QueueManagerException::missingPackage('ext-amqp');

readonly class AmqpQueue extends AbstractQueue implements PurgeableQueueInterface
{
    private AMQPChannel $channel;
    private AMQPExchange $exchange;
    private \AMQPQueue $queue;

    public function __construct(
        private AMQPConnection $connection,
        string $name = 'default',
        private int $maxAttempts = 5,
        RateLimiterInterface $limiter = new NullRateLimiter(),
    ) {
        parent::__construct(name: $name, limiter: $limiter);

        $this->channel = $this->createChannel($this->connection);
        $this->exchange = $this->createExchange(
            channel: $this->channel,
            name: $this->name,
            type: AMQP_EX_TYPE_DIRECT,
        );
        $this->exchange->declareExchange();
        $this->queue = $this->createDefaultQueue();
        $this->queue->declareQueue();
        $this->queue->bind($this->exchange->getName(), $this->name);
    }

    private function createChannel(AMQPConnection $connection): AMQPChannel
    {
        return new AMQPChannel($connection);
    }

    private function createExchange(
        AMQPChannel $channel,
        string $name,
        string $type,
        int $flags = AMQP_NOPARAM,
        array $arguments = []
    ): AMQPExchange {
        $exchange = new AMQPExchange($channel);
        $exchange->setName($name);
        $exchange->setType($type);
        $exchange->setArguments($arguments);
        $exchange->setFlags($flags);

        return $exchange;
    }

    private function createQueue(
        AMQPChannel $channel,
        string $name,
        int $flags = AMQP_NOPARAM,
        array $arguments = []
    ): \AMQPQueue {
        $queue = new \AMQPQueue($channel);
        $queue->setName($name);
        $queue->setFlags($flags);
        $queue->setArguments($arguments);

        return $queue;
    }

    private function createDefaultQueue(?AMQPChannel $channel = null): \AMQPQueue
    {
        return $this->createQueue(
            channel: $channel ?? $this->channel,
            name: $this->name,
            arguments: [
                'x-max-priority' => $this->maxAttempts,
            ],
        );
    }

    private function getQueueName(int|false $delay = 0): string
    {
        if (0 === $delay) {
            return $this->name;
        }

        if (false === $delay) {
            return $this->name . ':dead';
        }

        return $this->name . ':' . $delay;
    }

    private function getExchange(int|false $delay): AMQPExchange
    {
        if (0 === $delay) {
            return $this->exchange;
        }

        $exchange = $this->createExchange(
            channel: $this->channel,
            name: '',
            type: AMQP_EX_TYPE_DIRECT,
        );

        $queue = match ($delay === false) {
            true => $this->createQueue(
                channel: $this->channel,
                name: $this->getQueueName(false),
            ),
            false => $this->createQueue(
                channel: $this->channel,
                name: $this->getQueueName($delay),
                flags: AMQP_AUTODELETE,
                arguments: [
                    'x-message-ttl' => $delay * 1000,
                    'x-dead-letter-exchange' => $this->exchange->getName(),
                    'x-dead-letter-routing-key' => $this->name,
                ],
            )
        };
        $queue->declareQueue();

        return $exchange;
    }

    /**
     * @inheritDoc
     */
    public function size(): int
    {
        try {
            $channel = $this->createChannel($this->connection);
            $queue = $this->createDefaultQueue(channel: $channel);

            return $queue->declareQueue();
        } catch (Exception $e) {
            throw new QueueException('Failed to purge queue.', previous: $e);
        } finally {
            unset($queue, $channel);
        }
    }

    /**
     * @inheritDoc
     */
    public function consume(): ?JobInterface
    {
        try {
            // Rate limit reached? Wait...
            $this->getRateLimiter()->wait();

            $envelope = $this->queue->get(AMQP_NOPARAM);
            if (null === $envelope) {
                return null;
            }

            $this->getRateLimiter()->pop();

            return new AmqpJob($envelope, $this);
        } catch (Exception $e) {
            throw new QueueException('Failed to consume a job.', previous: $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function push(
        JobDescriptorInterface $jobDescriptor,
        DateTimeInterface|DateInterval|int $delay = 0,
    ): string {
        return $this->pushRaw(
            payload: $jobDescriptor,
            delay: $delay,
            attempts: $jobDescriptor instanceof JobInterface ? $jobDescriptor->getAttempts() : 0,
        );
    }

    /**
     * @inheritDoc
     * @throws QueueException
     */
    public function pushRaw(
        mixed $payload,
        DateTimeInterface|DateInterval|int $delay = 0,
        int $attempts = 0,
    ): string {
        try {
            $delaySeconds = $this->getDelayInSeconds($this->getAvailableDateTime($delay));
            $queueDelay = $attempts >= $this->maxAttempts ? false : $delaySeconds;
            $priority = max(0, $this->maxAttempts - $attempts);

            $this
                ->getExchange($queueDelay)
                ->publish(
                    message: json_encode($payload),
                    routingKey: $this->getQueueName($queueDelay),
                    headers: [
                        'content_type' => 'application/json',
                        'delivery_mode' => 2,
                        'headers' => [
                            'jobId' => $jobId = uniqid(more_entropy: true),
                            'attempts' => $attempts,
                            'delay' => $delaySeconds,
                        ],
                        'priority' => $priority,
                        'timestamp' => time(),
                    ],
                );

            return $jobId;
        } catch (Exception $e) {
            throw new QueueException('Failed to push a job.', previous: $e);
        }
    }

    /**
     * @inheritDoc
     * @throws QueueException
     */
    public function purge(): void
    {
        try {
            $channel = $this->createChannel($this->connection);
            $queue = $this->createDefaultQueue(channel: $channel);
            $queue->declareQueue();
            $queue->purge();
        } catch (Exception $e) {
            throw new QueueException('Failed to purge queue.', previous: $e);
        } finally {
            unset($queue, $channel);
        }
    }

    /**
     * Release job.
     *
     * @param AmqpJob $job
     * @param int $delay
     *
     * @return void
     * @throws QueueException
     */
    public function release(AmqpJob $job, int $delay = 0): void
    {
        $job->isReleased() && throw JobException::alreadyReleased($job);
        $job->isDeleted() && throw JobException::alreadyDeleted($job);

        $this->push($job, $delay);
    }

    /**
     * Delete job.
     *
     * @param AmqpJob $job
     *
     * @return void
     * @throws QueueException
     */
    public function delete(AmqpJob $job): void
    {
        try {
            $this->queue->ack($job->getAmqpEnvelope()->getDeliveryTag());
        } catch (Exception $e) {
            throw new QueueException('Failed to delete job.', previous: $e);
        }
    }
}
