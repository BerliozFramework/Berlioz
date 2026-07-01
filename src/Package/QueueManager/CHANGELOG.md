# Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- HTTP endpoint exposing queue metrics (`berlioz.queues.metrics`), opt-in and served by `QueueMetricsMiddleware` on a configurable path (default `/metrics/queues`), gated by a client IP allow-list and an optional bearer token, supporting `prometheus` and `json` formats; it never overrides an existing application route
- Reusable `QueueMetricsExporter` shared by the `queue:size` command and the HTTP endpoint; monitorable queues always expose `wait_time_seconds` / `delayed` (defaulting to `0` when the backend returns no value, e.g. an empty queue) so known series never disappear, while non-monitorable queues omit them

### Changed

- `berlioz:system` job handler is now opt-in via configuration
- The `prometheus` output of `queue:size` now includes `# HELP` / `# TYPE` metadata lines

### Fixed

- Escape queue name label values in the Prometheus output

### Security

- Disable the `berlioz:system` job handler by default and remove shell-string construction from job payloads (RCE)

## [3.1.1] - 2026-06-10

_No changes in this release._

## [3.1.0] - 2026-05-18

### Fixed

- Added a clear exception when no queue is configured to avoid runtime constructor crashes
- Fixed SQS queue factory handling when queue entries are arrays without explicit `name`
- Added explicit validation for required SQS queue URLs in queue factory configuration

## [3.0.0] - 2026-02-19

The version 3.0.0 marks the transition of the Berlioz Framework to a **Monorepo** structure.

Why this move?

- **Consistency:** Ensures all Berlioz components are tested and released together, preventing version mismatches.
- **Maintenance:** Simplifies the management of issues and pull requests by centralizing them in one place.
- **Better CI/CD:** Global testing ensures that a change in one package never breaks another.
