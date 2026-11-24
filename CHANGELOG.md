# Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [3.4.0] - 2026-11-24

### Added

- [cli-core] `CliTerminateEvent` dispatched by `CliApp::terminate()`, with the exit code of the command (`CliApp::getExitCode()`)
- [core] `AbstractApp::terminate()` dispatching a `TerminateEvent`, to run work once the application has done its job (debug activity in the `Terminate` group)
- [core] Debug response time: `DebugHandler::markResponseSent()`, `Snapshot::getResponseTime()` and `Timeline::getDurationUntil()`
- [core] Pending debug snapshot marker: `SnapshotLoader::markPending()`, `SnapshotLoader::status()` (`SnapshotStatus` enum) and `berlioz.debug.pending_ttl` configuration (default to `max_execution_time`, or 300 seconds if unlimited)
- [form] `strictJson` option on `ApiDataProvider` to preserve JSON types: only the root object is converted to an array,
  nested objects stay `stdClass` (a `{}` field is no longer confused with `[]`)
- [form] `Group::submitValue()` accepts a `stdClass` value
- [form] `ApiDataProvider` handles `PATCH` and `DELETE` requests, like `FormDataProvider`
- [form] `FormDataProvider` and `ApiDataProvider` read submitted data from the body of `QUERY` requests (RFC 10008)
- [form] `ScalarValidator` and `ScalarConstraint`, rejecting non-scalar values (arrays, objects) submitted to a simple type
- [form] Staged submission: `Form::handle($request, apply: false)` submits and validates without modifying mapped data,
  then `Form::apply()` applies the accepted submission; collection `add` / `remove` callbacks are deferred to
  `apply()`
- [form] `HydrationQueue` and `AbstractHydrator::plan()`, planning hydration writes before committing them
- [hector-package] Support constant names (e.g. `PDO::ATTR_TIMEOUT`) as attributes of `hector.options`
- [hector-package] Support list format `[{attribute, value}]` for `hector.options`
- [hector-package] Declare `username`, `password` and `options` in default configuration
- [http-client] `Client::query()` shortcut for the HTTP `QUERY` method (RFC 10008)
- [http-client] `HttpContext::$credentials_origins` to approve additional origins receiving origin transport credentials on
  redirects (`https://auth.example.com`, or `https://*.example.com` for one subdomain level)
- [http-client] `OriginCredentialsAwareInterface` for adapters holding their own origin credentials, implemented by `CurlAdapter`
  and `AutoAdapter`
- [http-client] `HttpContext` options `proxy_ssl_verify_peer`, `proxy_ssl_verify_host` (enabled by default, independent of the origin
  `ssl_verify_*` options), `proxy_ssl_cafile` and `proxy_ssl_capath` (default to `ssl_cafile` and `ssl_capath`)
- [http-client] Proxy environment variables are honored identically by the cURL and stream adapters: `https_proxy`/`HTTPS_PROXY`
  for HTTPS requests, `http_proxy` for HTTP requests (never the uppercase `HTTP_PROXY`, httpoxy),
  `all_proxy`/`ALL_PROXY` as fallback, and `no_proxy`/`NO_PROXY` exclusions (hosts, domains, IP addresses, CIDR
  ranges, optional port)
- [http-core] `HttpApp::terminate()` releasing the session lock and dispatching an `HttpTerminateEvent` (request and response metadata)
- [http-core] `berlioz.http.finish_request` configuration (default `true`)
- [http-core] `acceptQuery` route option and `AcceptQueryMiddleware` (enabled by default) for the HTTP `QUERY` method
  (RFC 10008): sends the `Accept-Query` header and rejects `QUERY` requests with a missing or malformed (400) or
  unsupported (415) content type
- [http-core] `MediaType` parser for media types and media ranges (RFC 9110), with quoted parameter values
- [http-core] `UnsupportedMediaTypeHttpException`
- [http-message] `Request::HTTP_METHOD_QUERY` constant (HTTP QUERY method, RFC 10008)
- [http-message] `MalformedBodyException` (extends `RuntimeException`), thrown when a message body cannot be parsed into usable data; `getHttpMessage()` returns the message whose body is malformed
- [http-message] `Http1ResponseValidator`, validating the status line and header fields of any PSR-7 response for an HTTP/1
  emission without side effects (protocol version, status code 100-599, reason phrase, header names and values;
  pseudo-headers are rejected); it returns an `Http1ResponseHead` (status line, status code, headers)
- [http-message] `InvalidResponseException` (extends `InvalidArgumentException`), thrown by `Http1ResponseValidator`
- [mailer] SMTP transport encryption with `encryption` argument (`SmtpEncryption` enum or string): `starttls` (mandatory
  STARTTLS, without plaintext fallback) and `tls` (implicit TLS)
- [mailer] SMTP transport `context` argument, to give stream context or stream context options (e.g. `ssl.cafile`)
- [mailer] SMTP transport `timeout` argument, now also applied to reads
- [queue-manager] `QueueInterface::getMaxAttempts()`, implemented by all queues (`null` if the queue has no limit or if the limit is unknown); custom `QueueInterface` implementations must implement it
- [queue-manager] `JobInterface::getMaxAttempts()` (from the job queue) and `JobInterface::isLastAttempt()` to let a job handler know whether the current attempt is the last one; custom `JobInterface` implementations not extending `Job` must implement them
- [queue-manager] Optional `maxAttempts` constructor parameter on `AwsSqsQueue`; if not given, `AwsSqsQueue::getMaxAttempts()` reads the `maxReceiveCount` of the queue redrive policy
- [queue-manager] `QueueInterface::getRetryTime()`, time after which a consumed job, neither deleted nor released, becomes available again (`null` for queues without such a delay: AMQP, null queue and queue manager); custom `QueueInterface` implementations must implement it
- [queue-manager] `RedisQueue::getMaxAttempts()`
- [queue-manager] `RedisQueue::getRetryTime()` and `RedisQueue::recoverProcessingJobs()`
- [queue-manager] `RedisJob::getRedisMessage()`
- [queue-manager] `Worker::drain()` to process jobs until the queue is empty (optional time limit), without sleep, limits nor signal handlers
- [queue-manager] `Worker::processJob()` extracted from `Worker::run()` (execute, then delete or release on failure)
- [queue-manager] `QueueManager::getQueue()`, `QueueManager::hasQueue()` and `QueueManager::getDefaultQueue()` to get a queue (and its settings) by name
- [queue-manager] `RabbitMqQueue` constructor parameter `managementApiContext` (stream context or stream context options, e.g. `ssl.cafile`) for management API requests
- [queue-manager-package] `max_attempts` option for AWS SQS queues
- [queue-manager-package] Deferred queues consumed on application terminate (after the HTTP response is sent, or at the end of a CLI command), configured with `berlioz.queues.deferred.queues` and `berlioz.queues.deferred.time_limit`; memory queues are purged afterwards
- [queue-manager-package] Debug activities for deferred queues and jobs (`Terminate` group)
- [queue-manager-package] Queue factories for Redis, AMQP (DSN and/or explicit connection parameters), RabbitMQ and Null queues, registered by default; an `amqps://` DSN requires verified TLS (CA certificate and verification enabled) and fails before connecting otherwise; the RabbitMQ `management` options (`base_url`, `username`, `password`, `context`) require explicit credentials (broker credentials are never reused) and are validated before connecting
- [router] Support of the HTTP `QUERY` method (RFC 10008): routes declared with `method: 'QUERY'` were silently ignored

### Changed

- [cli-core] `berlioz` entry point calls `CliApp::terminate()` after the command, even when it fails
- [core] `berlioz.debug.ip` defaults to `null` (loopback only) and accepts CIDR ranges and the `loopback`, `private`,
  `public` and `*` aliases; use `["*"]` to allow every client, including behind a reverse proxy
- [core] CLI processes (`cli` SAPI without `REMOTE_ADDR`) are considered local for debug when `berlioz.debug.ip` allows
  loopback clients; other non-web SAPIs (`phpdbg`, `embed`) are no longer granted debug by default
- [core] New `DebugAccessPolicy` shared by `DebugHandler::isEnabledInConfig()` and the HTTP debug console
- [core] Require `berlioz/helpers` `^1.15`
- [core] `SnapshotLoader::save()` removes the pending marker of the snapshot
- [core] `SnapshotCleaner` also removes expired pending markers (`clean()`), all markers (`clear()`) and the marker of a deleted snapshot (`delete()`)
- [form] Simple types (`Text`, `Number`, `Date`, `Hidden`, ...) now reject non-scalar submitted values by default with a
  `ScalarConstraint`, and no longer hydrate them into the mapped object (previously a `TypeError` on typed setters or
  properties). `File` and `Choice` are not affected
- [form] Hydration runs every conversion before the first write: a failing transformer no longer leaves the mapped object
  partially updated. Reads done while planning (sub-objects, collection items) see the mapped object as it was before
  any setter ran, so a setter replacing a sub-object no longer affects the hydration of that sub-object
- [form] `Form::handle()` has a second parameter `bool $apply = true`: subclasses overriding `handle()` must add it
- [form] `AbstractHydrator` implements `hydrate()` (plan, then commit) and declares the abstract `plan()` method: custom
  hydrators extending it must implement `plan()` instead of `hydrate()`
- [form] `AbstractHydrator::getSubMapped()` has a fourth parameter `?HydrationQueue $queue = null` and
  `AbstractHydrator::locateHydrator()` returns `AbstractHydrator`: overriding subclasses must match
- [hector-package] Require HectorORM 1.5 or later, the first version supporting PDO connection options
- [http-client] `HttpContext::$proxy` defaults to `null`, which follows the proxy environment variables in every adapter; `false` now
  means no proxy at all, including for the cURL adapter, which previously still honored the environment variables
- [http-client] The stream adapter now goes through the proxy of the environment variables, it previously always connected directly
- [http-client] The cURL adapter resolves the proxy itself: libcurl no longer reads the proxy environment variables, except when a
  raw `CURLOPT_PROXY` option is given with the default `null` proxy; a configured proxy or `false` takes precedence
  over a raw `CURLOPT_PROXY` option
- [http-client] A proxy of the environment variables with an unsupported scheme (e.g. `ftp://`) is rejected with a
  `RequestException` naming the variable; SOCKS proxies (`socks4`, `socks4a`, `socks5`, `socks5h`) are still handed
  over to libcurl by the cURL adapter, and rejected by the stream adapter
- [http-client] A proxy of the environment variables without port uses port 80 (`http`) or 443 (`https`), libcurl used 1080
- [http-client] The cURL adapter only opens a `CONNECT` tunnel for HTTPS origins; HTTP requests are sent to the proxy in
  absolute-form, as the stream adapter does
- [http-client] `QUERY` requests keep their method and body when following a 301 or 302 redirection (RFC 10008, section 2.5);
  only a 303 switches to `GET`
- [http-client] Disabling origin TLS verification (`ssl_verify_peer`, `ssl_verify_host`) no longer disables proxy TLS verification;
  use `proxy_ssl_verify_peer` and `proxy_ssl_verify_host`
- [http-client] An unsupported proxy configuration (scheme other than `http`/`https`, missing host, path, query or fragment) is
  rejected with a `RequestException` before any connection; a proxy without scheme is an `http` proxy
- [http-core] `HttpApp::print()` closes the client connection once the response is printed (`fastcgi_finish_request()` or `litespeed_finish_request()` when available); output produced afterwards is no longer sent to the client
- [http-core] `HttpApp::print()` marks the debug response as sent and writes a pending snapshot marker when debug is enabled
- [http-core] Debug console and toolbar show an "in progress" page (HTTP 202, auto-reload) while the snapshot is pending, an "incomplete" page (HTTP 410) when abandoned, and a 404 when unknown, instead of waiting server-side
- [http-core] Debug toolbar shows the response time; dashboard, performances and activities show response time and total time
- [http-core] Debug router panel includes `QUERY` in the default methods
- [http-core] `HttpApp::print()` validates the complete response (status line and headers) before any output; an invalid
  response throws an `InvalidResponseException` and emits no header nor body; the exception is left to the front
  controller
- [http-message] `JsonParser` throws `MalformedBodyException` on invalid JSON, and `getParsedBody()` throws it when the parsed body is neither an array, an object nor `null` (e.g. a scalar JSON root); `getParsedBody()` attaches the message to the exception when a parser does not
- [http-message] Header names are validated with the same RFC 9110 field-name grammar as `Http1ResponseValidator`; pseudo-headers
  remain accepted (e.g. HAR replay)
- [http-message] Leading and trailing spaces and tabs of header values are removed (optional whitespace, RFC 9110)
- [queue-manager] `RedisQueue` stops retrying a job after `maxAttempts` (new constructor parameter, 5 by default like the other queues): the job is moved to the `<name>:dead` list instead of being released again
- [queue-manager] `Worker` installs its signal handlers when `run()` starts instead of in the constructor
- [queue-manager] `RabbitMqQueue` throws a `QueueException` from its constructor, before connecting, if a management API base URL is given without explicit username and password, or if it is not a valid `http`/`https` URL (other stream wrappers, credentials, query or fragment are rejected)
- [router] Routes without explicit methods also match `QUERY` requests

### Deprecated

- [core] Hostnames (reverse DNS) in `berlioz.debug.ip` configuration; use IP addresses, CIDR ranges or aliases instead.
  An `E_USER_DEPRECATED` is triggered when a hostname entry is evaluated
- [core] Service declarations in `container.services` configuration; register services from a service provider listed in
  `container.providers` instead. An `E_USER_DEPRECATED` is triggered when `container.services` is not empty
- [form] Constructing `ApiDataProvider` without `strictJson: true`; strict JSON will be the only behavior in v4
- [form] `scalar` option of simple types: `false` disables the non-scalar check, `true` is redundant; the option will be
  removed in v4 and non-scalar values always rejected, use a `Collection` or a `RawType` instead
- [mailer] Argument `$options` of `Smtp::__construct()`, use named argument `$timeout` instead; it will be removed in 4.0
- [twig-package] `preload()` parameter `nopush`, HTTP/2 Server Push is no longer supported by browsers
- [twig-package] `AssetRuntimeExtension::H2PUSH_CACHE_COOKIE` constant and `AssetRuntimeExtension::setCookie()` method, no longer used

### Fixed

- [cli-core] `--help` displays the command usage without instantiating the command and its dependencies
- [core] `FileCacheDriver::set()` now writes atomically (temporary file + `rename()`), preventing concurrent readers from
  getting "Corrupted data" exceptions
- [form] `ApiDataProvider` ignores a scalar parsed body instead of throwing a `TypeError`
- [form] `isValid()` takes into account constraints added with `invalid()`, on the element and its parents;
  `resetConstraints()` (now part of `ValidatorHandlerInterface`) clears them recursively, `Form::handle()` resets
  them, and cloned elements (including `Collection` entries) no longer copy the constraints of the original
- [http-core] Fix `ResponseHelperTrait::jsonResponse()` silently returning an empty 204 response when JSON encoding fails; an
  `InternalServerErrorHttpException` is now thrown
- [http-core] A malformed incoming request body (`MalformedBodyException` raised for the server request, e.g. invalid JSON) produces a 400 Bad Request instead of a 500 Internal Server Error, and is no longer logged as a server error; malformed bodies of other messages (e.g. upstream responses) remain logged 500 errors
- [http-core] `DefaultErrorHandler` and the fallback of `ErrorHandler` use the default reason phrase of the status code when the
  `HttpException` message cannot be used as a reason phrase (control character other than a tab), instead of failing
  and losing the status code
- [http-message] `getParsedBody()` compares the media type case-insensitively (RFC 9110), so bodies sent as e.g. `Application/JSON` are now parsed; `addBodyParser()` registers media types in lowercase
- [http-message] Header names ending with a line feed are rejected
- [queue-manager] `RedisQueue` no longer loses a job when the worker dies during its processing: consumed jobs are kept in a `<name>:processing` set and, if neither deleted nor released within `retryTime` (new constructor parameter, 30 seconds by default like the other queues), pushed back to the queue as a failed attempt (or moved to the `<name>:dead` list once `maxAttempts` is reached)
- [queue-manager] `AmqpQueue` counts a message redelivered by the broker (consumer died without deleting or releasing the job) as a failed attempt: it is published again with incremented attempts, or to the dead queue once `maxAttempts` is reached, instead of being redelivered indefinitely
- [twig-package] Twig debug console template no longer uses the `spaceless` filter, deprecated since Twig 3.12
- [twig-package] `date_format` filter accepts any `DateTimeInterface`, `DateTimeImmutable` no longer throws a `TypeError`
- [twig-package] `path()` keeps the routing exception as previous exception of the Twig `RuntimeError`
- [twig-package] `path_exists()` accepts a `RouteAttributes` object, like `path()`
- [twig-package] `entrypoints()` with the `preload` option now preloads every rendered file, not only the first one
- [twig-package] `entrypoints()` no longer preloads files when the `preload` option is `false`
- [twig-package] `preload()` always sends the `Link` preload header (once per link and response) and no longer sets
  `h2pushes[...]` cookies, the HTTP/2 Server Push cache is removed
- [twig-package] `asset()` reports `No manifest file` instead of `No entry points file` when no manifest is configured

### Security

- [core] Debug access is restricted to loopback clients (`127.0.0.1`, `::1`) by default when `berlioz.debug.enable` is
  `true`; an explicitly empty `berlioz.debug.ip` list now denies access instead of allowing everyone
- [core] Debug access is denied when the client cannot be determined reliably: forwarding headers (`Forwarded`,
  `X-Forwarded-For`, `X-Real-IP`) sent by an untrusted peer, or a trusted proxy that does not forward the client
- [http-client] Configured proxy is enforced across adapters (GHSA-3jc3-3r75-xrx6): the cURL adapter now keeps the proxy scheme, so
  an `https://` proxy is reached over TLS instead of plain HTTP; the stream adapter now connects through the proxy
  (absolute-form requests, `CONNECT` tunnel for HTTPS origins, data sent by the proxy before the origin TLS handshake
  rejected) instead of connecting directly to the origin, sends proxy credentials in `Proxy-Authorization` instead of
  `Authorization`, and rejects HTTPS origins through an HTTPS proxy, which PHP streams cannot handle
- [http-client] Origin transport credentials (client certificate, key and passphrases of `HttpContext`, credential options of
  `CurlAdapter` such as `CURLOPT_USERPWD`, `CURLOPT_XOAUTH2_BEARER` or `CURLOPT_COOKIE`) are no longer sent when
  a redirection leaves the initial origin or the approved ones; proxy settings are kept (GHSA-7xg7-qg7v-j4r4)
- [http-client] Custom adapters holding their own origin credentials must implement `OriginCredentialsAwareInterface`: the client
  uses their `withoutOriginCredentials()` copy on those redirections, other adapters only receive the `HttpContext`
  without TLS client options
- [http-core] Debug console access follows the core `DebugAccessPolicy`: loopback clients only by default, an empty
  `berlioz.debug.ip` list denies access, and clients behind an untrusted or non-forwarding proxy are denied
- [http-message] `MultipartStream` validates part framing metadata (GHSA-r8g6-2j96-hjg9): boundaries must follow RFC 2046 (1-70 allowed characters, no trailing space), part header names must be tokens and values must be strings without control characters (except tab), otherwise an `InvalidArgumentException` is thrown before any bytes are appended; default `Content-Disposition` `name` and `filename` parameters are escaped as in HTML forms (`"`, CR and LF percent-encoded), and backslash is percent-encoded (`%5C`) so that receivers applying quoted-pair unescaping, such as PHP, cannot read it as an escape character; `addElements()` adds no element if one of them is invalid
- [mailer] SMTP transport no longer discloses Bcc recipients in message headers, they are kept in the envelope only
  (GHSA-3cwp-w36j-c8j6)
- [queue-manager] `RabbitMqQueue` no longer follows redirections of the management API, which could send the credentials to another origin, and ignores non-`200` responses (GHSA-xmfp-689m-hfp6)
- [twig-package] `nl2p` filter escapes its input before adding paragraph and line break markup, like Twig `nl2br`
  (GHSA-543h-rf5x-cc4g); HTML passed to the filter is now escaped unless marked safe upstream (e.g. `|raw`)

## [3.3.2] - 2026-10-25

### Fixed

- [http-core] `HttpApp::print()` preserves the response status code when a `Location` header is sent with a status other than 201 or 3xx, instead of letting PHP turn it into a 302 redirect
- [http-message] `getParsedBody()` no longer parses the body again when its parsed value is falsy (`null`, `[]`), and respects a `null` value set with `withParsedBody()`
- [http-message] `JsonParser` returns `null` for an empty body instead of throwing a `RuntimeException`
- [queue-manager] Fix HectorORM 1.6 `$skipLocked` deprecation triggered on every `DbQueue::consume()` poll, and keep `SKIP LOCKED` semantics explicit ahead of HectorORM 2.0

## [3.3.1] - 2026-10-16

### Fixed

- [queue-manager] Fix PHP 8.6 deprecation caused by the nullable return type of `JobHandlerManager::__debugInfo()`

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
