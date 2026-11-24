# Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [3.4.0] - 2026-11-24

### Added

- `QueueInterface::getMaxAttempts()`, implemented by all queues (`null` if the queue has no limit or if the limit is unknown); custom `QueueInterface` implementations must implement it
- `JobInterface::getMaxAttempts()` (from the job queue) and `JobInterface::isLastAttempt()` to let a job handler know whether the current attempt is the last one; custom `JobInterface` implementations not extending `Job` must implement them
- Optional `maxAttempts` constructor parameter on `AwsSqsQueue`; if not given, `AwsSqsQueue::getMaxAttempts()` reads the `maxReceiveCount` of the queue redrive policy
- `QueueInterface::getRetryTime()`, time after which a consumed job, neither deleted nor released, becomes available again (`null` for queues without such a delay: AMQP, null queue and queue manager); custom `QueueInterface` implementations must implement it
- `RedisQueue::getMaxAttempts()`
- `RedisQueue::getRetryTime()` and `RedisQueue::recoverProcessingJobs()`
- `RedisJob::getRedisMessage()`
- `Worker::drain()` to process jobs until the queue is empty (optional time limit), without sleep, limits nor signal handlers
- `Worker::processJob()` extracted from `Worker::run()` (execute, then delete or release on failure)
- `QueueManager::getQueue()`, `QueueManager::hasQueue()` and `QueueManager::getDefaultQueue()` to get a queue (and its settings) by name
- `RabbitMqQueue` constructor parameter `managementApiContext` (stream context or stream context options, e.g. `ssl.cafile`) for management API requests

### Changed

- `RedisQueue` stops retrying a job after `maxAttempts` (new constructor parameter, 5 by default like the other queues): the job is moved to the `<name>:dead` list instead of being released again
- `Worker` installs its signal handlers when `run()` starts instead of in the constructor
- `RabbitMqQueue` throws a `QueueException` from its constructor, before connecting, if a management API base URL is given without explicit username and password, or if it is not a valid `http`/`https` URL (other stream wrappers, credentials, query or fragment are rejected)

### Security

- `RabbitMqQueue` no longer follows redirections of the management API, which could send the credentials to another origin, and ignores non-`200` responses (GHSA-xmfp-689m-hfp6)

### Fixed

- `RedisQueue` no longer loses a job when the worker dies during its processing: consumed jobs are kept in a `<name>:processing` set and, if neither deleted nor released within `retryTime` (new constructor parameter, 30 seconds by default like the other queues), pushed back to the queue as a failed attempt (or moved to the `<name>:dead` list once `maxAttempts` is reached)
- `AmqpQueue` counts a message redelivered by the broker (consumer died without deleting or releasing the job) as a failed attempt: it is published again with incremented attempts, or to the dead queue once `maxAttempts` is reached, instead of being redelivered indefinitely

## [3.3.2] - 2026-10-25

### Fixed

- Fix HectorORM 1.6 `$skipLocked` deprecation triggered on every `DbQueue::consume()` poll, and keep `SKIP LOCKED` semantics explicit ahead of HectorORM 2.0

## [3.3.1] - 2026-10-16

### Fixed

- Fix PHP 8.6 deprecation caused by the nullable return type of `JobHandlerManager::__debugInfo()`

## [3.3.0] - 2026-09-24

### Fixed

- Fix `DbQueue::waitTime()` acquiring a needless `FOR UPDATE SKIP LOCKED` lock and skipping locked rows, distorting the monitoring metric

## [3.2.1] - 2026-09-11

_No changes in this release._

## [3.2.0] - 2026-07-02

_No changes in this release._

## [3.1.1] - 2026-06-10

_No changes in this release._

## [3.1.0] - 2026-05-18

### Added

- Add `MonitorableQueueInterface` with `waitTime()` and `delayed()` metrics for supported queue backends
- Add `RabbitMqQueue` to expose RabbitMQ-specific monitoring metrics through the management API

### Changed

- Make Redis deleted-jobs TTL configurable in `RedisQueue` constructor (default: 86400s)
- Deprecate `QueueManager::stats()` in favor of iterating over `QueueManager::getQueues()` for detailed metrics
- Order DB queue consumption by `availability_time` before `job_id`

### Fixed

- Fix `AwsSqsQueue::delete()` using `MessageId` instead of `ReceiptHandle`, causing SQS messages to never be deleted
- Fix operator precedence in `AwsSqsQueue::size()` causing TypeError when attributes are missing
- Fix `AmqpQueue::release()` not acknowledging original message, causing duplicate processing
- Fix `QueueManager::pushRaw()` not passing `$delay` parameter when using the default queue
- Add missing `declare(strict_types=1)` in `SqsJob`
- Fix `AmqpJob` TypeError when delivery tag fallback is used as job id by casting to string
- Fix `RedisQueue::freeDelayedJobs()` lock release safety: use unique lock value to prevent deleting another process's lock
- Fix missing `json_decode` error handling in `SqsJob`, `AmqpJob`, and `RedisQueue`, preventing TypeError on invalid JSON
- Fix missing released/deleted state guards in `AwsSqsQueue::release()` and `AwsSqsQueue::delete()`
- Fix incorrect exception message in `AmqpQueue::size()` (was referring to purge instead of size)
- Fix grammar in `QueueManagerException::queueNotFound()` plural message

### Removed

- Remove unused private method `QueueManager::consumeInAllQueues()`

## [3.0.0] - 2026-02-19

The version 3.0.0 marks the transition of the Berlioz Framework to a **Monorepo** structure.

Why this move?

- **Consistency:** Ensures all Berlioz components are tested and released together, preventing version mismatches.
- **Maintenance:** Simplifies the management of issues and pull requests by centralizing them in one place.
- **Better CI/CD:** Global testing ensures that a change in one package never breaks another.
