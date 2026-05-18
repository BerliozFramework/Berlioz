# Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [3.1.0] - 2026-05-18

### Added

- [cli-core] Add `CommandDeclaration::integrity()` method to verify command class availability
- [config] Detect and report circular references in config function resolution
- [event-manager] Add max dispatch depth guard to prevent infinite recursion in `EventDispatcher::dispatch()`
- [http-message] Add `Request::HTTP_METHOD_PATCH` constant
- [queue-manager] Add `MonitorableQueueInterface` with `waitTime()` and `delayed()` metrics for supported queue backends
- [queue-manager] Add `RabbitMqQueue` to expose RabbitMQ-specific monitoring metrics through the management API

### Changed

- [config] Raise minimum `colinodell/json5` requirement to `^2.2.1` to avoid `prefer-lowest` JSON5 parsing failures with `JSON_THROW_ON_ERROR`
- [mailer] Make reserved headers check case-insensitive in `Mail::addHeader()` and `Mail::setHeaders()` (RFC 5322)
- [queue-manager] Make Redis deleted-jobs TTL configurable in `RedisQueue` constructor (default: 86400s)
- [queue-manager] Deprecate `QueueManager::stats()` in favor of iterating over `QueueManager::getQueues()` for detailed metrics
- [queue-manager] Order DB queue consumption by `availability_time` before `job_id`
- [router] Host restrictions are now inherited from parent route groups
- [router] Duplicate attribute names in route path now throw `RoutingException`

### Removed

- [queue-manager] Remove unused private method `QueueManager::consumeInAllQueues()`

### Fixed

- [cli-core] Gracefully handle unavailable command classes (e.g. removed package with stale cache) instead of crashing the entire console
- [cli-core] Fix `Parser::getCommandAndArguments()` injecting `null` into arguments array when no command is provided
- [cli-core] Fix `Card::result()` using non-multibyte-safe `str_pad()` causing misaligned rendering with accented characters
- [cli-core] Fix `AbstractCommand::get()` accessing uninitialized `$app` property directly instead of using `getApp()`
- [cli-core] Fix `CacheClearCommand::clearCache()` ignoring explicitly requested hidden directories
- [cli-core] Add missing `declare(strict_types=1)` in `Console`, `Parser` and `ArgumentsManager`
- [cli-core] Suppress PHP 8.4+ deprecation `Using null as an array offset` triggered by `league/climate` when a command declares positional arguments (without prefix/longPrefix)
- [config] Fix `JSON_THROW_ON_ERROR` passed as options instead of depth in `JsonAdapter`
- [config] Fix `IniAdapter` no longer rejects valid empty INI files
- [config] Fix `get(null)` now returns the full merged configuration instead of throwing TypeError
- [config] Fix `getOrFail()` no longer throws on falsy configuration values (0, false, empty string)
- [config] Fix shift accumulation in string interpolation with multiple config functions
- [core] Fix `AbstractFilesystem::copy()` incorrectly calling `move()` on the adapter, causing source file deletion
- [core] Fix `AbstractFilesystem::move()` and `copy()` passing full URIs instead of stripped paths to filesystem adapters
- [core] Fix `DefaultDirectories::getAppDir()` never caching due to operator precedence (`!null ===`)
- [core] Fix `ComposerBuilder::build()` checking `json_decode()` result against `false` instead of `null`
- [core] Fix `PhpErrorHandler::handler()` returning `true` which suppressed PHP's built-in error handling
- [core] Fix `Snapshot::__construct()` discarding `array_filter()` result for sections filtering
- [core] Fix `Timeline::__construct()` discarding `array_filter()` result for activities filtering
- [core] Fix `FileCacheDriver::get()` treating a cached `false` value as corrupted data
- [core] Move `Composer` object creation outside the loop in `ComposerBuilder::build()` to avoid unnecessary intermediate instance
- [core] Fix `FileCacheDriver::set()` using unreachable `false` check on `serialize()`, use try/catch instead
- [event-manager] Fix listener priority sort order so that higher priority listeners execute first
- [event-manager] Fix `addEventListener()` TypeError when passing an object instead of a string event name
- [event-manager] Fix `SubscriberProvider` tracking wrong subscriber after sequential dispatches due to `array_slice` offset mismatch
- [event-manager] Fix delegate dispatchers being called even when event propagation was stopped
- [flash-bag] Fix `saveToSession()` writing to `$_SESSION` when session is not active, causing silent data loss
- [flash-bag] Fix constructor silently continuing when session cannot be started (headers already sent)
- [form] Enforce `max_elements` limits consistently in `Collection::setValue()` and `Collection::submitValue()`
- [form] Avoid duplicate `[]` suffix in multiple file input names rendered by Twig
- [form] Fix enum array transformation by converting each submitted value independently in `EnumTransformer::fromForm()`
- [form] Rewind uploaded file stream after reading magic bytes in `FileFormatValidator::validate()` to prevent downstream code from missing the beginning of the stream
- [hector-package] Fixed restoration of internal loggers in `HectorSection::__unserialize()`
- [hector-package] Updated `HectorException::typesConfig()` to use a safe non-late-static constructor return
- [http-core] Fix `RouterBuilder::createRoutesFromArray()` unsetting wrong key (`children` instead of `routes`), breaking nested config routes
- [http-core] Fix `MaintenanceMiddleware` ignoring `start`/`end` dates of scheduled maintenance windows
- [http-core] Fix `ControllerHandler` silently returning empty 200 response when `json_encode()` fails
- [http-core] Fix middlewares accumulating on repeated `HttpApp::handle()` calls by resetting before adding
- [http-core] Fix non-exhaustive `match` for font extensions in `DebugController::distFiles()` causing `UnhandledMatchError`
- [http-core] Fix `ErrorHandler::fallback()` using `??` instead of `?:` for status code fallback, allowing invalid code `0`
- [mailer] Fix `Address::__toString()` TypeError when mail property is not set
- [mailer] Fix null pointer error in `Smtp::send()` and `PhpMail::send()` when no sender address is defined
- [mailer] Fix `Mail::getHtml()` minification having no effect due to `pcre.recursion_limit` being restored before `preg_replace`
- [mailer] Fix Windows detection in `Mail::getHtml()` using `PHP_OS_FAMILY` instead of unreliable `PHP_OS == 'WIN'`
- [mailer] Fix SMTP transport using `EHLO` instead of `HELO` for proper AUTH extension support (RFC 4954)
- [mailer] Fix `Smtp::write()` treating zero-length writes as errors by using strict `=== false` check on `fwrite`
- [mailer] Fix `Smtp::get()` not consuming multi-line SMTP responses, causing protocol desynchronization
- [mailer] Fix `Smtp::__destruct()` potentially throwing an exception causing a fatal error
- [mailer] Fix `PhpMail` leaking Bcc addresses in headers sent to recipients
- [mailer] Fix MIME boundary generation including a spurious `--` prefix, producing malformed multipart messages
- [mailer] Fix header injection vulnerability by rejecting CR/LF characters in `Mail::addHeader()`
- [queue-manager] Fix `AwsSqsQueue::delete()` using `MessageId` instead of `ReceiptHandle`, causing SQS messages to never be deleted
- [queue-manager] Fix operator precedence in `AwsSqsQueue::size()` causing TypeError when attributes are missing
- [queue-manager] Fix `AmqpQueue::release()` not acknowledging original message, causing duplicate processing
- [queue-manager] Fix `QueueManager::pushRaw()` not passing `$delay` parameter when using the default queue
- [queue-manager] Add missing `declare(strict_types=1)` in `SqsJob`
- [queue-manager] Fix `AmqpJob` TypeError when delivery tag fallback is used as job id by casting to string
- [queue-manager] Fix `RedisQueue::freeDelayedJobs()` lock release safety: use unique lock value to prevent deleting another process's lock
- [queue-manager] Fix missing `json_decode` error handling in `SqsJob`, `AmqpJob`, and `RedisQueue`, preventing TypeError on invalid JSON
- [queue-manager] Fix missing released/deleted state guards in `AwsSqsQueue::release()` and `AwsSqsQueue::delete()`
- [queue-manager] Fix incorrect exception message in `AmqpQueue::size()` (was referring to purge instead of size)
- [queue-manager] Fix grammar in `QueueManagerException::queueNotFound()` plural message
- [queue-manager-package] Added a clear exception when no queue is configured to avoid runtime constructor crashes
- [queue-manager-package] Fixed SQS queue factory handling when queue entries are arrays without explicit `name`
- [queue-manager-package] Added explicit validation for required SQS queue URLs in queue factory configuration
- [router] Fix named route groups can now be found by `getRoute()`
- [router] Fix inline regex `0` in route attribute is no longer silently dropped
- [router] Fix `finalizePath()` URI scheme check no longer triggered by user parameters
- [router] Fix `count()` now recursively counts all leaf routes including those in groups
- [router] Fix route regex delimiter no longer conflicts with user-supplied requirement patterns
- [router] Fix add PATCH to the list of default accepted HTTP methods
- [router] Fix `float` route type now accepts integer values
- [service-container] Fix auto-wiring gracefully skips intersection and DNF types
- [service-container] Fix failed auto-wiring instantiation no longer permanently blocks retries
- [service-container] Fix service `provides` field is now preserved through serialization
- [service-container] Fix services with null factory or alias can now be deserialized
- [service-container] Fix auto-wiring now tries all types in union type parameters before giving up
- [service-container] Fix method calls on non-shared services now correctly target the created instance
- [twig-package] Updated `TwigException` factories to use safe non-late-static constructor returns

### Security

- Bump minimum `phpunit/phpunit` version to `^11.5.51` to pull in the fix for [GHSA-vvj3-c3rp-c85p](https://github.com/sebastianbergmann/phpunit/security/advisories/GHSA-vvj3-c3rp-c85p) (Unsafe Deserialization in PHPT Code Coverage Handling, High severity). PHPUnit is a development dependency: production deployments using `composer install --no-dev` are not affected.

## [3.0.0] - 2026-02-19

The version 3.0.0 marks the transition of the Berlioz Framework to a **Monorepo** structure.

Why this move?

- **Consistency:** Ensures all Berlioz components are tested and released together, preventing version mismatches.
- **Maintenance:** Simplifies the management of issues and pull requests by centralizing them in one place.
- **Better CI/CD:** Global testing ensures that a change in one package never breaks another.
