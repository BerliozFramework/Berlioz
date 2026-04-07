# Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- Fix `Address::__toString()` TypeError when mail property is not set
- Fix null pointer error in `Smtp::send()` and `PhpMail::send()` when no sender address is defined
- Fix `Mail::getHtml()` minification having no effect due to `pcre.recursion_limit` being restored before `preg_replace`
- Fix Windows detection in `Mail::getHtml()` using `PHP_OS_FAMILY` instead of unreliable `PHP_OS == 'WIN'`
- Fix SMTP transport using `EHLO` instead of `HELO` for proper AUTH extension support (RFC 4954)
- Fix `Smtp::write()` treating zero-length writes as errors by using strict `=== false` check on `fwrite`
- Fix `Smtp::get()` not consuming multi-line SMTP responses, causing protocol desynchronization
- Fix `Smtp::__destruct()` potentially throwing an exception causing a fatal error
- Fix `PhpMail` leaking Bcc addresses in headers sent to recipients

## [3.0.0] - 2026-02-19

The version 3.0.0 marks the transition of the Berlioz Framework to a **Monorepo** structure.

Why this move?

- **Consistency:** Ensures all Berlioz components are tested and released together, preventing version mismatches.
- **Maintenance:** Simplifies the management of issues and pull requests by centralizing them in one place.
- **Better CI/CD:** Global testing ensures that a change in one package never breaks another.
