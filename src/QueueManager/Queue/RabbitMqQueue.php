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
use Berlioz\QueueManager\Exception\QueueException;
use Berlioz\QueueManager\RateLimiter\NullRateLimiter;
use Berlioz\QueueManager\RateLimiter\RateLimiterInterface;
use DateTimeImmutable;
use Exception;
use SensitiveParameter;

/**
 * Class RabbitMqQueue.
 */
readonly class RabbitMqQueue extends AmqpQueue implements MonitorableQueueInterface
{
    private ?RabbitMqManagementApi $managementApi;

    /**
     * RabbitMqQueue constructor.
     *
     * Management API is optional, used for monitoring metrics. If a base URL is given, explicit credentials are
     * required: a dedicated read-only user (with "monitoring" tag) is recommended, never the broker credentials.
     *
     * @param AMQPConnection $connection
     * @param string $name
     * @param int $maxAttempts
     * @param RateLimiterInterface $limiter
     * @param string|null $managementApiBaseUrl HTTP(S) base URL of the management API
     * @param string|null $managementApiUsername
     * @param string|null $managementApiPassword
     * @param string $vhost
     * @param array|resource|null $managementApiContext Stream context or stream context options (e.g. "ssl.cafile")
     *
     * @throws QueueException
     */
    public function __construct(
        AMQPConnection $connection,
        string $name = 'default',
        int $maxAttempts = 5,
        RateLimiterInterface $limiter = new NullRateLimiter(),
        ?string $managementApiBaseUrl = null,
        ?string $managementApiUsername = null,
        #[SensitiveParameter] ?string $managementApiPassword = null,
        private string $vhost = '/',
        mixed $managementApiContext = null,
    ) {
        // Validated before connecting to the broker
        $this->managementApi = $this->createManagementApi(
            $managementApiBaseUrl,
            $managementApiUsername,
            $managementApiPassword,
            $managementApiContext,
        );

        parent::__construct(
            connection: $connection,
            name: $name,
            maxAttempts: $maxAttempts,
            limiter: $limiter,
        );
    }

    /**
     * Create management API client.
     *
     * @param string|null $baseUrl
     * @param string|null $username
     * @param string|null $password
     * @param mixed $context
     *
     * @return RabbitMqManagementApi|null
     * @throws QueueException
     */
    private function createManagementApi(
        ?string $baseUrl,
        ?string $username,
        #[SensitiveParameter] ?string $password,
        mixed $context,
    ): ?RabbitMqManagementApi {
        if (null === $baseUrl) {
            return null;
        }

        if (null === $username || '' === $username || null === $password) {
            throw QueueException::invalidManagementApi('explicit username and password are required');
        }

        return new RabbitMqManagementApi(
            baseUrl: $baseUrl,
            username: $username,
            password: $password,
            context: $context,
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
        if (null === $this->managementApi) {
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
        return $this->managementApi?->get($path);
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
