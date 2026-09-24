# Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [3.3.0] - 2026-09-24

### Added

- [form] `FormMapping(get:, set:)` strategy accepted as the `mapped` option, allowing full control over how an
  element reads from and writes to the mapped object (arbitrary internal target, nested properties, on-the-fly
  transformations). The public `name` stays decoupled from the internal mapping target.
- [form] `ElementInterface::getMapping(): ?FormMapping` normalizing the `mapped` option (`false`/`string`/`true`/
  `FormMapping`) into a single strategy.
- [http-client] Additive `redirectSensitiveHeaders` option to configure application-specific credential headers
- [http-core] Display response status, reason phrase, protocol and headers in the debug console's HTTP / Router section
- [http-core] HTTP Core refreshes the router server-parameter context before matching each request, keeping route and asset URLs consistent with the supplied PSR-7 request even when URI rewriting is disabled
- [http-core] Optional `ForwardedPrefixMiddleware`, applied last in the pipeline when both `berlioz.router.rewriteRequestUri` and `X-Forwarded-Prefix` handling are enabled, rewrites the current request URI with the reverse-proxy prefix so URLs derived from `getUri()` are correctly prefixed; the resolved prefix is also exposed via the `berlioz.forwarded_prefix` request attribute
- [http-core] `berlioz.router.rewriteRequestUri` defaults to `false` in v3, preserving the internal request path unless rewriting is explicitly enabled
- [http-core] `HttpApp::setRequest()` to update the application-wide server request
- [http-core] Request rewriting uses a processing attribute to avoid applying the prefix twice to the same rewritten request, while preserving internal paths that overlap the proxy mount
- [router] `RouterInterface::setServerParams()` supplies a non-serialized request context for URL generation; explicit `finalizePath()` parameters take precedence, and `$_SERVER` remains the fallback without a context
- [router] `ForwardedPrefixResolver` centralizes prefix validation and proxy trust checks; `Router::getForwardedPrefixResolver()` exposes the resolver built from the concrete router's effective options
- [router] `Router::finalizePath()` accepts an optional `?array $serverParams` argument (uses the current context, then `$_SERVER` when omitted), added to `RouterInterface`
- [router] New `trustedProxies` router option: `X-Forwarded-Prefix` is now only honoured when `REMOTE_ADDR` is a trusted proxy (IP, CIDR or alias via `NetworkHelper::isTrustedProxy()`)

### Changed

- [form] Mapping is now resolved uniformly through the `FormMapping` strategy in `TypeHydrator`, `TypeCollector`
  and `AbstractElement::getMapped()`. No behavior change for the existing `bool`/`string` forms of the
  `mapped` option.
- [http-message] Generate 70-character multipart boundaries directly from `random_bytes()` instead of the generic random-string helper
- [router] `Router::finalizePath()` normalizes leading/trailing slashes of the prefix and prefixes internal paths even when they start with the same segment as the proxy mount

### Deprecated

- [http-core] Handling a valid trusted forwarded prefix without request URI rewriting now emits `E_USER_DEPRECATED`; enable `berlioz.router.rewriteRequestUri` to adopt the mandatory v4 behavior. Requests without a valid trusted prefix do not emit this deprecation

### Fixed

- [html-selector] Preserve quotes and backslashes in XPath literals for attribute selectors and `:contains()` / `:lang()` arguments
- [http-client] Restore cookie manager snapshots in linear time without replaying cookie replacement checks
- [http-client] Reject empty cookie hosts before IDNA conversion to avoid ValueError on PHP 8.4 and later
- [http-client] Ignore invalid cookie domains individually during HAR import and replay
- [http-client] Serialize session cookies as an array and discard cookies from legacy session formats
- [http-client] Preserve host-only and SameSite attributes when updating cookies
- [http-client] Honor `cookies: false` when sending requests and collecting response cookies
- [http-client] Preserve content type for 307/308 request bodies and recalculate redirect content length
- [http-core] Log caught HTTP server exceptions and error-handler failures to the configured PHP error log, even when debug is disabled
- [queue-manager] Fix `DbQueue::waitTime()` acquiring a needless `FOR UPDATE SKIP LOCKED` lock and skipping locked rows, distorting the monitoring metric
- [queue-manager-package] Resolve the queue manager only when collecting HTTP metrics, allowing applications without configured queues to handle requests while metrics are disabled or not requested

### Security

- [html-selector] Encode XPath string literals safely and validate interpolated element/attribute names to prevent XPath injection
- [http-client] Preserve host-only scope across HAR exports/imports and validate imported cookies against their entry host
- [http-client] Normalize internationalized cookie domains when IDNA support is available; reject Unicode domains otherwise
- [http-client] Reject response cookies for unrelated domains before storage, replacement or deletion
- [http-client] Preserve host-only cookie scope and enforce cookie path boundaries and default paths
- [http-client] Strip credential headers and URI credentials persistently after cross-origin redirects without modifying caller options
- [http-client] Sanitize redirect referers and omit them on HTTPS-to-HTTP redirects, preventing reapplication from default headers
- [http-core] The reverse-proxy prefix is only applied when `REMOTE_ADDR` matches the router's trusted proxies (inherited from `berlioz.proxies.trusted` unless explicitly overridden); HTTP Core injects the concrete router's `ForwardedPrefixResolver` into the middleware to share the same validation and effective options
- [mailer] Use cryptographically secure randomness for MIME boundaries and attachment Content-IDs, preserving their existing formats
- [router] Ignore malformed forwarded prefixes, including header lists, traversal segments, delimiters, controls and ambiguous encoded separators or percent signs
- [router] `X-Forwarded-Prefix` is ignored unless it comes from a configured trusted proxy, preventing a client from spoofing the prefix to poison generated URLs

## [3.2.1] - 2026-09-11

### Fixed

- [form] Avoid "Array to string conversion" warning in `LengthValidator`, `IntervalValidator` and `FormatValidator` when the submitted value is not scalar (e.g. an array)
- [http-message] `Base64Stream` now closes the base64 write filter after writing so the final quantum and padding are emitted (fixes truncated output on PHP 8.4/8.5 following php-src GH-22360)

### Security

- [http-core] Prevent debug console access-control bypass through case variants and alternate routes to the debug controller

## [3.2.0] - 2026-07-02

### Added

- [cli-core] Add `berlioz:debug-clear` command to clear debug reports (`--all`, `--days=N`, or configured retention policy by default)
- [core] `RedisCacheDriver`, a PSR-16 cache driver backed by phpredis (ext-redis)
- [core] `FallbackCacheDriver`, a resilience decorator that falls back to the next driver when one fails
- [core] `CacheDriverFactory` to build a cache driver from an array of options, a JSON file (`fromFile()`) or environment variables (`fromEnv()`/`auto()`)
- [core] `SnapshotCleaner` to garbage collect debug snapshots according to a retention policy (max age in days and/or max number of files)
- [core] Automatic, probabilistic garbage collection of debug snapshots on write, configurable via `berlioz.debug.gc` (`probability`, `divisor`, `max_age`, `max_files`)
- [core] Number of open PHP resources in debug system info (`SystemInfo::getOpenResources()`)
- [hector-package] Copy-to-clipboard buttons on debug console SQL queries (raw and with interpolated values)
- [hector-package] Detection and highlighting of duplicate SQL queries in the debug console
- [hector-package] Configurable debug thresholds via `hector.debug` (`slow_query`, `very_slow_query`, `duplicate_threshold`)
- [hector-package] Database migrations support with `hector:migrate`, `hector:migrate:down` and `hector:migrate:status` commands
- [http-client] `AutoAdapter` selecting cURL by default and falling back to the stream transport automatically
- [http-core] Copy-to-clipboard buttons on debug console code blocks and values
- [http-core] "Clear all caches" button in debug console cache page (internal cache, OPcache and cache directories)
- [http-core] Cache clear shortcuts on the debug console dashboard (clear internal cache or all caches)
- [http-core] Support for inline modal content in the debug console via `data-content` on `data-toggle="detail"` triggers (in addition to remote `data-target`)
- [http-core] Display the number of open PHP resources in the debug console environment page
- [queue-manager-package] HTTP endpoint exposing queue metrics (`berlioz.queues.metrics`), opt-in and served by `QueueMetricsMiddleware` on a configurable path (default `/metrics/queues`), gated by a client IP allow-list and an optional bearer token, supporting `prometheus` and `json` formats; it never overrides an existing application route
- [queue-manager-package] Reusable `QueueMetricsExporter` shared by the `queue:size` command and the HTTP endpoint; monitorable queues always expose `wait_time_seconds` / `delayed` (defaulting to `0` when the backend returns no value, e.g. an empty queue) so known series never disappear, while non-monitorable queues omit them
- [twig-package] Copy-to-clipboard buttons on template and name values in debug console Twig page

### Changed

- [core] `FileCacheDriver` now also accepts a directory path string in addition to `DirectoriesInterface`
- [core] Bump `berlioz/helpers` requirement to `^1.14` (network helpers)
- [hector-package] Slow-query detection now uses absolute, configurable thresholds instead of a per-request average
- [hector-package] `HectorSection` now accepts a single `Logger` (the variadic constructor argument has been removed)
- [hector-package] Bumped `hectororm/hectororm` requirement to `^1.4`
- [http-core] Use a more meaningful cache icon in the debug console (menu and dashboard)
- [http-core] Use a distinct icon for the environment section in the debug console menu (avoid clash with Hector ORM section)
- [http-core] Debug console cache-clearing actions now require `POST` (cache page and dashboard buttons are forms instead of links)
- [queue-manager-package] `berlioz:system` job handler is now opt-in via configuration
- [queue-manager-package] The `prometheus` output of `queue:size` now includes `# HELP` / `# TYPE` metadata lines

### Fixed

- [core] `DefaultDirectories::getLibraryDirectory()` no longer throws when the package is installed standalone: it now falls back to the package-root `composer.json` when the monorepo-relative path does not resolve
- [http-core] Round load average values in debug performances page to avoid excessive decimals
- [http-core] Re-initialize debug console JS components (copy buttons, tooltips, syntax highlighting) on content loaded into modals (remote or inline)
- [http-core] Prevent `TypeError` ("Cannot read properties of null") in debug console when repeatedly clicking copy buttons, caused by accumulated `hidden.bs.tooltip` listeners disposing the tooltip twice
- [http-message] Guard malformed `Content-Type` parsing in `getParsedBody()` (no more undefined-index warning on a type without a subtype)
- [http-message] Pass an explicit nesting depth to `json_decode()` in `JsonParser`
- [mailer] Add explicit nullable types to parameters with `null` default to fix PHP 8.4 implicit nullable deprecation
- [queue-manager-package] Escape queue name label values in the Prometheus output
- [router] Fix inline route attribute regex containing curly braces (quantifiers like `{2}` or `{1,3}`) is no longer truncated at the first closing brace
- [twig-package] Twig profile detail modal now reuses the shared debug console detail mechanism, restoring interactive components (copy buttons) in the modal
- [twig-package] Removed a leftover `console.log` from the Twig debug console template

### Security

- [core] Stop trusting `X-Forwarded-For` for the debug IP allow-list; use `REMOTE_ADDR` unless a trusted proxy is configured (`berlioz.proxies.trusted`)
- [core] Create cache directories with restrictive permissions (`0750`) instead of world-writable `0777`, and write cache files as `0640`
- [http-core] Gate the entire `/_console` debug surface (including `phpinfo`) behind debug-enabled + client IP allow-list via a new `DebugConsoleMiddleware`; non-allowed requests get a 404
- [http-core] Require `POST` for cache-clearing actions, removing the unauthenticated GET trigger
- [http-message] Reject CR/LF and invalid characters in header names/values and URI components (CRLF/header injection); HTTP/2 pseudo-headers are still allowed
- [http-message] Reject CR/LF and NUL in the `Response` reason phrase (HTTP status-line injection)
- [http-message] Create upload target directories with restrictive permissions (`0750`) in `UploadedFile::moveTo()`
- [mailer] Reject CR/LF in subject, address display name and bulk headers to prevent email header injection
- [mailer] Always encode MIME headers (subject, address display name) with a deterministic `UTF-8` charset instead of `mb_detect_encoding()`
- [queue-manager-package] Disable the `berlioz:system` job handler by default and remove shell-string construction from job payloads (RCE)

## [3.1.1] - 2026-06-10

### Fixed

- [cli-core] Fix command arguments being ignored and built-in `help` triggered erroneously: removed the obsolete `league/climate` null-offset workaround (forcing `prefix`/`longPrefix` to empty strings), which made every `-`/`--` argument match positional and help arguments. The upstream deprecation is fixed in `league/climate` 3.11.0

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
