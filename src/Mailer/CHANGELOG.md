# Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Security

- Reject CR/LF in subject, address display name and bulk headers to prevent email header injection
- Always encode MIME headers (subject, address display name) with a deterministic `UTF-8` charset instead of `mb_detect_encoding()`

## [3.1.1] - 2026-06-10

_No changes in this release._

## [3.1.0] - 2026-05-18

### Changed

- Make reserved headers check case-insensitive in `Mail::addHeader()` and `Mail::setHeaders()` (RFC 5322)

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
- Fix MIME boundary generation including a spurious `--` prefix, producing malformed multipart messages
- Fix header injection vulnerability by rejecting CR/LF characters in `Mail::addHeader()`

## [3.0.0] - 2026-02-19

The version 3.0.0 marks the transition of the Berlioz Framework to a **Monorepo** structure.

Why this move?

- **Consistency:** Ensures all Berlioz components are tested and released together, preventing version mismatches.
- **Maintenance:** Simplifies the management of issues and pull requests by centralizing them in one place.
- **Better CI/CD:** Global testing ensures that a change in one package never breaks another.
