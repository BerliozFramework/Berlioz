# Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [3.2.0] - 2026-07-02

_No changes in this release._

## [3.1.1] - 2026-06-10

_No changes in this release._

## [3.1.0] - 2026-05-18

### Added

- Detect and report circular references in config function resolution

### Changed

- Raise minimum `colinodell/json5` requirement to `^2.2.1` to avoid `prefer-lowest` JSON5 parsing failures with `JSON_THROW_ON_ERROR`

### Fixed

- Fix `JSON_THROW_ON_ERROR` passed as options instead of depth in `JsonAdapter`
- Fix `IniAdapter` no longer rejects valid empty INI files
- Fix `get(null)` now returns the full merged configuration instead of throwing TypeError
- Fix `getOrFail()` no longer throws on falsy configuration values (0, false, empty string)
- Fix shift accumulation in string interpolation with multiple config functions

## [3.0.0] - 2026-02-19

The version 3.0.0 marks the transition of the Berlioz Framework to a **Monorepo** structure.

Why this move?

- **Consistency:** Ensures all Berlioz components are tested and released together, preventing version mismatches.
- **Maintenance:** Simplifies the management of issues and pull requests by centralizing them in one place.
- **Better CI/CD:** Global testing ensures that a change in one package never breaks another.
