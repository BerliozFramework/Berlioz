# Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [3.1.1] - 2026-06-10

_No changes in this release._

## [3.1.0] - 2026-05-18

### Fixed

- Fix auto-wiring gracefully skips intersection and DNF types
- Fix failed auto-wiring instantiation no longer permanently blocks retries
- Fix service `provides` field is now preserved through serialization
- Fix services with null factory or alias can now be deserialized
- Fix auto-wiring now tries all types in union type parameters before giving up
- Fix method calls on non-shared services now correctly target the created instance

## [3.0.0] - 2026-02-19

The version 3.0.0 marks the transition of the Berlioz Framework to a **Monorepo** structure.

Why this move?

- **Consistency:** Ensures all Berlioz components are tested and released together, preventing version mismatches.
- **Maintenance:** Simplifies the management of issues and pull requests by centralizing them in one place.
- **Better CI/CD:** Global testing ensures that a change in one package never breaks another.
