# Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

## [3.0.0] - 2026-02-19

The version 3.0.0 marks the transition of the Berlioz Framework to a **Monorepo** structure.

Why this move?

- **Consistency:** Ensures all Berlioz components are tested and released together, preventing version mismatches.
- **Maintenance:** Simplifies the management of issues and pull requests by centralizing them in one place.
- **Better CI/CD:** Global testing ensures that a change in one package never breaks another.
