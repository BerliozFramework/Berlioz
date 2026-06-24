# Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Add `berlioz:debug-clear` command to clear debug reports (`--all`, `--days=N`, or configured retention policy by default)

## [3.1.1] - 2026-06-10

### Fixed

- Fix command arguments being ignored and built-in `help` triggered erroneously: removed the obsolete `league/climate` null-offset workaround (forcing `prefix`/`longPrefix` to empty strings), which made every `-`/`--` argument match positional and help arguments. The upstream deprecation is fixed in `league/climate` 3.11.0

## [3.1.0] - 2026-05-18

### Added

- Add `CommandDeclaration::integrity()` method to verify command class availability

### Fixed

- Gracefully handle unavailable command classes (e.g. removed package with stale cache) instead of crashing the entire console
- Fix `Parser::getCommandAndArguments()` injecting `null` into arguments array when no command is provided
- Fix `Card::result()` using non-multibyte-safe `str_pad()` causing misaligned rendering with accented characters
- Fix `AbstractCommand::get()` accessing uninitialized `$app` property directly instead of using `getApp()`
- Fix `CacheClearCommand::clearCache()` ignoring explicitly requested hidden directories
- Add missing `declare(strict_types=1)` in `Console`, `Parser` and `ArgumentsManager`
- Suppress PHP 8.4+ deprecation `Using null as an array offset` triggered by `league/climate` when a command declares positional arguments (without prefix/longPrefix)

## [3.0.0] - 2026-02-19

The version 3.0.0 marks the transition of the Berlioz Framework to a **Monorepo** structure.

Why this move?

- **Consistency:** Ensures all Berlioz components are tested and released together, preventing version mismatches.
- **Maintenance:** Simplifies the management of issues and pull requests by centralizing them in one place.
- **Better CI/CD:** Global testing ensures that a change in one package never breaks another.
