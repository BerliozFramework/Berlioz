# Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [3.2.1] - 2026-09-11

_No changes in this release._

## [3.2.0] - 2026-07-02

### Added

- Copy-to-clipboard buttons on template and name values in debug console Twig page

### Fixed

- Twig profile detail modal now reuses the shared debug console detail mechanism, restoring interactive components (copy buttons) in the modal
- Removed a leftover `console.log` from the Twig debug console template

## [3.1.1] - 2026-06-10

_No changes in this release._

## [3.1.0] - 2026-05-18

### Fixed

- Updated `TwigException` factories to use safe non-late-static constructor returns

## [3.0.0] - 2026-02-19

The version 3.0.0 marks the transition of the Berlioz Framework to a **Monorepo** structure.

Why this move?

- **Consistency:** Ensures all Berlioz components are tested and released together, preventing version mismatches.
- **Maintenance:** Simplifies the management of issues and pull requests by centralizing them in one place.
- **Better CI/CD:** Global testing ensures that a change in one package never breaks another.
