# Contributing to Berlioz Framework

First off, thank you for considering contributing to the Berlioz Framework! Every contribution helps make Berlioz
better for everyone.

## Monorepo

This repository is a **monorepo** that contains all official Berlioz packages. Sub-repositories (e.g.
`berlioz/core`, `berlioz/router`, etc.) are **read-only mirrors** that are synchronized automatically.

**All contributions (bug reports, feature requests, pull requests) must be made on this repository.**

## Reporting bugs

Before opening a new issue, please check if a similar issue already exists.

When reporting a bug, please include:

- The PHP version you are using.
- The Berlioz version (or commit hash).
- A clear description of the expected behavior and what actually happened.
- Steps to reproduce the issue, ideally with a minimal code example.

## Suggesting features

Feature requests are welcome. Open an issue with the `enhancement` label and describe:

- The problem you are trying to solve.
- How you envision the solution.
- Any alternatives you have considered.

## Pull requests

### Getting started

1. **Fork** the repository and clone your fork locally.
2. Install dependencies:
   ```bash
   composer install
   ```
3. Create a **new branch** from `main`:
   ```bash
   git checkout -b my-feature
   ```

### Coding standards

- PHP code must follow **PSR-12** coding style.
- Use `declare(strict_types=1);` in every PHP file.
- Indentation: 4 spaces for PHP files, 2 spaces for other files (see `.editorconfig`).
- Maximum line length: 120 characters for PHP files.
- Import classes with `use` statements; do not use fully qualified names in code.

### Tests

All changes must be covered by tests. The project uses **PHPUnit 11**.

```bash
# Run the full test suite
vendor/bin/phpunit

# Run tests for a specific package
vendor/bin/phpunit --testsuite "Berlioz Router test suite"
```

Make sure all tests pass before submitting your pull request:

```bash
vendor/bin/phpunit --coverage-text
```

### Static analysis

The project uses **Rector** for automated code quality checks:

```bash
# Check for issues (dry run)
vendor/bin/rector --dry-run

# Apply fixes
vendor/bin/rector
```

### Commit messages

- Use clear, descriptive commit messages.
- Start with a short summary (max 72 characters).
- Use the imperative mood ("Add feature", not "Added feature").
- Reference related issues when applicable (e.g. `Fix #42`).

### Submitting

1. Make sure the test suite passes.
2. Push your branch to your fork.
3. Open a **pull request** against the `main` branch of this repository.
4. Describe your changes clearly in the pull request description.
5. Be prepared to address feedback during code review.

## Development setup

### Requirements

- PHP >= 8.2
- Extensions: `fileinfo`, `intl`, `mbstring`, `xml`
- Composer v2

### Optional extensions

Some packages require additional extensions for their full test suite:

- `yaml` -- Config YAML adapter
- `amqp` -- Queue Manager AMQP driver
- `redis` -- Queue Manager Redis driver
- `curl` -- HTTP Client CURL adapter

## Code of conduct

Please be respectful and constructive in all interactions. We are committed to providing a welcoming and inclusive
experience for everyone.

## Questions?

If you have any questions, feel free to open an issue or reach out via the project website at
[getberlioz.com](https://getberlioz.com).
