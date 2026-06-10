# Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [3.1.1] - 2026-06-10

_No changes in this release._

## [3.1.0] - 2026-05-18

### Added

- Add max dispatch depth guard to prevent infinite recursion in `EventDispatcher::dispatch()`

### Fixed

- Fix listener priority sort order so that higher priority listeners execute first
- Fix `addEventListener()` TypeError when passing an object instead of a string event name
- Fix `SubscriberProvider` tracking wrong subscriber after sequential dispatches due to `array_slice` offset mismatch
- Fix delegate dispatchers being called even when event propagation was stopped

## [3.0.0] - 2026-02-19

The version 3.0.0 marks the transition of the Berlioz Framework to a **Monorepo** structure.

Why this move?

- **Consistency:** Ensures all Berlioz components are tested and released together, preventing version mismatches.
- **Maintenance:** Simplifies the management of issues and pull requests by centralizing them in one place.
- **Better CI/CD:** Global testing ensures that a change in one package never breaks another.
