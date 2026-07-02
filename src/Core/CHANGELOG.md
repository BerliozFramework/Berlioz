# Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [3.2.0] - 2026-07-02

### Added

- `RedisCacheDriver`, a PSR-16 cache driver backed by phpredis (ext-redis)
- `FallbackCacheDriver`, a resilience decorator that falls back to the next driver when one fails
- `CacheDriverFactory` to build a cache driver from an array of options, a JSON file (`fromFile()`) or environment variables (`fromEnv()`/`auto()`)
- `SnapshotCleaner` to garbage collect debug snapshots according to a retention policy (max age in days and/or max number of files)
- Automatic, probabilistic garbage collection of debug snapshots on write, configurable via `berlioz.debug.gc` (`probability`, `divisor`, `max_age`, `max_files`)
- Number of open PHP resources in debug system info (`SystemInfo::getOpenResources()`)

### Changed

- `FileCacheDriver` now also accepts a directory path string in addition to `DirectoriesInterface`
- Bump `berlioz/helpers` requirement to `^1.14` (network helpers)

### Fixed

- `DefaultDirectories::getLibraryDirectory()` no longer throws when the package is installed standalone: it now falls back to the package-root `composer.json` when the monorepo-relative path does not resolve

### Security

- Stop trusting `X-Forwarded-For` for the debug IP allow-list; use `REMOTE_ADDR` unless a trusted proxy is configured (`berlioz.proxies.trusted`)
- Create cache directories with restrictive permissions (`0750`) instead of world-writable `0777`, and write cache files as `0640`

## [3.1.1] - 2026-06-10

_No changes in this release._

## [3.1.0] - 2026-05-18

### Fixed

- Fix `AbstractFilesystem::copy()` incorrectly calling `move()` on the adapter, causing source file deletion
- Fix `AbstractFilesystem::move()` and `copy()` passing full URIs instead of stripped paths to filesystem adapters
- Fix `DefaultDirectories::getAppDir()` never caching due to operator precedence (`!null ===`)
- Fix `ComposerBuilder::build()` checking `json_decode()` result against `false` instead of `null`
- Fix `PhpErrorHandler::handler()` returning `true` which suppressed PHP's built-in error handling
- Fix `Snapshot::__construct()` discarding `array_filter()` result for sections filtering
- Fix `Timeline::__construct()` discarding `array_filter()` result for activities filtering
- Fix `FileCacheDriver::get()` treating a cached `false` value as corrupted data
- Move `Composer` object creation outside the loop in `ComposerBuilder::build()` to avoid unnecessary intermediate instance
- Fix `FileCacheDriver::set()` using unreachable `false` check on `serialize()`, use try/catch instead

## [3.0.0] - 2026-02-19

The version 3.0.0 marks the transition of the Berlioz Framework to a **Monorepo** structure.

Why this move?

- **Consistency:** Ensures all Berlioz components are tested and released together, preventing version mismatches.
- **Maintenance:** Simplifies the management of issues and pull requests by centralizing them in one place.
- **Better CI/CD:** Global testing ensures that a change in one package never breaks another.
