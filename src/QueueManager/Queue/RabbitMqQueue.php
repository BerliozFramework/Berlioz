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

namespace Berlioz\QueueManager\Queue;

use AMQPConnection;
use Berlioz\QueueManager\RateLimiter\NullRateLimiter;
use Berlioz\QueueManager\RateLimiter\RateLimiterInterface;
use DateTimeImmutable;
use Exception;

/**
 * Class RabbitMqQueue.
 */
readonly class RabbitMqQueue extends AmqpQueue implements MonitorableQueueInterface
{
    public function __construct(
        AMQPConnection $connection,
        string $name = 'default',
        int $maxAttempts = 5,
        RateLimiterInterface $limiter = new NullRateLimiter(),
        private ?string $managementApiBaseUrl = null,
        private ?string $managementApiUsername = null,
        private ?string $managementApiPassword = null,
        private string $vhost = '/',
    ) {
        parent::__construct(
            connection: $connection,
            name: $name,
            maxAttempts: $maxAttempts,
            limiter: $limiter,
        );
    }

    /**
     * @inheritDoc
     */
    public function waitTime(): ?int
    {
        return $this->getApproximateWaitTime();
    }

    /**
     * @inheritDoc
     */
    public function delayed(): ?int
    {
        $queues = $this->getQueuesData();
        if (null === $queues) {
            return null;
        }

        $delayed = 0;

        foreach ($queues as $queueData) {
            $queueName = $queueData['name'] ?? null;
            if (!is_string($queueName) || !str_starts_with($queueName, $this->getName() . ':')) {
                continue;
            }

            $suffix = substr($queueName, strlen($this->getName()) + 1);
            if (false === ctype_digit($suffix)) {
                continue;
            }

            $delayed += (int)($queueData['messages'] ?? 0);
        }

        return $delayed;
    }

    /**
     * @return int|null
     */
    private function getApproximateWaitTime(): ?int
    {
        $headTimestamp = $this->getHeadMessageTimestamp();
        if (null === $headTimestamp) {
            return null;
        }

        return max(0, time() - $headTimestamp);
    }

    /**
     * @return int|null
     */
    private function getHeadMessageTimestamp(): ?int
    {
        if (null === $this->managementApiBaseUrl
            || null === $this->managementApiUsername
            || null === $this->managementApiPassword) {
            return null;
        }

        try {
            $queueData = $this->getQueueData();
            if (!is_array($queueData)) {
                return null;
            }

            return $this->normalizeTimestamp(
                $queueData['head_message_timestamp']
                ?? $queueData['backing_queue_status']['head_message_timestamp']
                ?? null,
            );
        } catch (Exception) {
            return null;
        }
    }

    /**
     * @return array|null
     */
    private function getQueueData(): ?array
    {
        return $this->fetchManagementApiJson(
            sprintf('/api/queues/%s/%s', rawurlencode($this->vhost), rawurlencode($this->getName())),
        );
    }

    /**
     * @return array<array>|null
     */
    private function getQueuesData(): ?array
    {
        $queues = $this->fetchManagementApiJson(sprintf('/api/queues/%s', rawurlencode($this->vhost)));

        return is_array($queues) ? $queues : null;
    }

    /**
     * @param string $path
     *
     * @return mixed
     */
    private function fetchManagementApiJson(string $path): mixed
    {
        if (null === $this->managementApiBaseUrl
            || null === $this->managementApiUsername
            || null === $this->managementApiPassword) {
            return null;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    sprintf('Authorization: Basic %s', base64_encode(sprintf(
                        '%s:%s',
                        $this->managementApiUsername,
                        $this->managementApiPassword,
                    ))),
                ],
                'timeout' => 2,
                'ignore_errors' => true,
            ],
        ]);

        $body = file_get_contents(rtrim($this->managementApiBaseUrl, '/') . $path, false, $context);
        if (false === $body) {
            return null;
        }

        return json_decode($body, true);
    }

    /**
     * @param mixed $rawTimestamp
     *
     * @return int|null
     */
    private function normalizeTimestamp(mixed $rawTimestamp): ?int
    {
        if (is_int($rawTimestamp)) {
            return $rawTimestamp > 1000000000000 ? (int)floor($rawTimestamp / 1000) : $rawTimestamp;
        }

        if (is_string($rawTimestamp)) {
            if (is_numeric($rawTimestamp)) {
                return $this->normalizeTimestamp((int)$rawTimestamp);
            }

            $dateTime = new DateTimeImmutable($rawTimestamp);

            return $dateTime->getTimestamp();
        }

        return null;
    }
}
