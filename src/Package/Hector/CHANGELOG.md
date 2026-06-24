# Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Copy-to-clipboard buttons on debug console SQL queries (raw and with interpolated values)
- Detection and highlighting of duplicate SQL queries in the debug console
- Configurable debug thresholds via `hector.debug` (`slow_query`, `very_slow_query`, `duplicate_threshold`)
- Support for Hector ORM database migrations (`hectororm/migration`)
- CLI commands `hector:migrate`, `hector:migrate:down` and `hector:migrate:status`
- Configurable migration provider (`directory` / `psr4` / custom) and tracker (`db` / `file` / custom) under `hector.migration`
- `--dry-run` flag on `hector:migrate` and `hector:migrate:down` (delegates to the runner native dry-run)
- `--interactive` (`-i`) flag on `hector:migrate` and `hector:migrate:down` to ask for confirmation before each migration

### Changed

- Slow-query detection now uses absolute, configurable thresholds instead of a per-request average
- `HectorSection` now accepts a single `Logger` (the variadic constructor argument has been removed)
- Bumped `hectororm/hectororm` requirement to `^1.4` (required for DDL migrations, see [hectororm/hectororm#127](https://github.com/hectororm/hectororm/issues/127))

## [3.1.1] - 2026-06-10

_No changes in this release._

## [3.1.0] - 2026-05-18

### Fixed

- Fixed restoration of internal loggers in `HectorSection::__unserialize()`
- Updated `HectorException::typesConfig()` to use a safe non-late-static constructor return

## [3.0.0] - 2026-02-19

The version 3.0.0 marks the transition of the Berlioz Framework to a **Monorepo** structure.

Why this move?

- **Consistency:** Ensures all Berlioz components are tested and released together, preventing version mismatches.
- **Maintenance:** Simplifies the management of issues and pull requests by centralizing them in one place.
- **Better CI/CD:** Global testing ensures that a change in one package never breaks another.
