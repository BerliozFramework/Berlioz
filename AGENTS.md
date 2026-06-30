# AGENTS.md -- Berlioz Framework

> This file provides architectural context for AI coding assistants (Claude Code, Copilot, Cursor, etc.)
> and human contributors. It describes the project structure, conventions, and key patterns to avoid
> unnecessary codebase exploration on every interaction.

## Overview

**Berlioz Framework** is a slim PHP framework for web applications, APIs, and CLI tools.
This repository is a **monorepo** containing all official Berlioz packages. Sub-repositories
(e.g., `berlioz/core`, `berlioz/router`) are **read-only mirrors** synchronized automatically
via subsplit. All development, issues, and pull requests happen here.

- **Website:** https://getberlioz.com
- **PHP:** >= 8.2
- **License:** MIT
- **PSR compliance:** PSR-4, PSR-7, PSR-11, PSR-12, PSR-14, PSR-16, PSR-17, PSR-18

## Monorepo Structure

```
.
├── src/                          # Source code -- one subdirectory per package
│   ├── Cli/Core/                 # berlioz/cli-core       -- CLI application core
│   ├── Config/                   # berlioz/config          -- Configuration management
│   ├── Core/                     # berlioz/core            -- Framework kernel
│   ├── EventManager/             # berlioz/event-manager   -- Event dispatcher (PSR-14)
│   ├── FlashBag/                 # berlioz/flash-bag       -- Flash messages
│   ├── Form/                     # berlioz/form            -- HTML form handling
│   ├── HtmlSelector/             # berlioz/html-selector   -- CSS selector queries on HTML
│   ├── Http/
│   │   ├── Client/              # berlioz/http-client      -- HTTP client (PSR-18)
│   │   ├── Core/                # berlioz/http-core        -- HTTP application core
│   │   └── Message/             # berlioz/http-message     -- HTTP messages (PSR-7/PSR-17)
│   ├── Mailer/                   # berlioz/mailer           -- Email sending (SMTP/mail)
│   ├── Package/
│   │   ├── Hector/              # berlioz/hector-package   -- Hector ORM integration
│   │   ├── QueueManager/        # berlioz/queue-manager-package -- Queue integration
│   │   └── Twig/                # berlioz/twig-package     -- Twig template integration
│   ├── QueueManager/             # berlioz/queue-manager    -- Background job processing
│   ├── Router/                   # berlioz/router           -- HTTP routing
│   └── ServiceContainer/         # berlioz/service-container -- DI container (PSR-11)
├── tests/                        # Test suites -- mirrors src/ structure
├── bin/                          # Release tooling scripts
├── composer.json                 # Root composer -- defines all packages
├── phpunit.xml.dist              # PHPUnit configuration (17 test suites)
├── rector.php                    # Rector automated refactoring config
└── config.subsplit-publish.json  # Subsplit configuration for read-only mirrors
```

Each package under `src/` contains its own `CHANGELOG.md`, `composer.json`, `LICENSE`, and `README.md`.

## Package Architecture

### Dependency Layers

```
                    ┌─────────────┐   ┌────────────┐
                    │  Http/Core  │   │  Cli/Core  │  ← Application layer
                    └──────┬──────┘   └──────┬─────┘
                           │                 │
              ┌────────────┼─────────────────┤
              │            │                 │
        ┌─────┴──────┐  ┌──┴───┐  ┌──────────┴────────────┐
        │   Router   │  │ Core │  │ Package/* (Twig,      │  ← Framework layer
        └────────────┘  └──┬───┘  │ Hector, QueueManager) │
                           │      └───────────────────────┘
             ┌─────────────┼───────────────┐
             │             │               │
      ┌──────┴──────┐ ┌────┴──────┐ ┌──────┴───────┐
      │   Config    │ │  Service  │ │ EventManager │  ← Foundation layer
      └─────────────┘ │ Container │ └──────────────┘
                      └───────────┘

     ┌────────────────┐  ┌──────────────┐  ┌─────────┐  ┌──────────────┐
     │ Http/Message   │  │ Http/Client  │  │ Mailer  │  │ QueueManager │  ← Standalone
     │ Form, FlashBag │  │ HtmlSelector │  └─────────┘  └──────────────┘    components
     └────────────────┘  └──────────────┘
```

### Core (`src/Core/`)

The framework kernel. The `Core` class holds all components and orchestrates the boot sequence:

**Boot sequence:** Core constructor → DebugHandler → Filesystem → CoreCacheFactory (Composer, Config, Packages) →
ContainerBuilder → locale → packages register → packages boot → ready.

Key entry points:

- `Core` -- central kernel, holds config, container, packages, debug, filesystem, cache
- `App\AbstractApp` -- base application class (extended by `HttpApp` and `CliApp`)
- `Package\PackageInterface` -- package lifecycle: `config()` → `register(Container)` → `boot(Core)`
- `Factory\CoreFactory` / `CoreCacheFactory` -- builds and caches Composer, Config, PackageSet

**Default config** (`src/Core/resources/config.default.json`):

```json
{
  "berlioz": {
    "environment": "prod",
    "locale": null,
    "debug": {
      "enable": false,
      "ip": []
    },
    "directories": {
      "app": "{var: berlioz.directories.app}",
      "cache": "{var: berlioz.directories.cache}",
      "config": "{var: berlioz.directories.config}",
      "debug": "{var: berlioz.directories.debug}",
      "log": "{var: berlioz.directories.log}",
      "tmp": "{config: berlioz.directories.var}/tmp",
      "var": "{var: berlioz.directories.var}",
      "vendor": "{var: berlioz.directories.vendor}",
      "working": "{var: berlioz.directories.working}"
    },
    "assets": {
      "manifest": null,
      "entrypoints": null,
      "entrypoints_key": null
    }
  },
  "events": {
    "listeners": {},
    "subscribers": []
  },
  "container": {
    "services": {},
    "providers": []
  }
}
```

### Config (`src/Config/`)

Multi-source configuration with priority-based merging. Pluggable adapters (JSON, YAML, INI, Array) sorted by priority.

**Config Functions** -- dynamic value interpolation in strings:

- `{config: berlioz.environment}` -- references another config key
- `{var: berlioz.directories.app}` -- resolves a runtime variable
- `{env: APP_ENV}` -- reads an environment variable
- `{constant: PHP_INT_MAX}` -- resolves a PHP constant
- `{file: /path/to/file}` -- reads file contents

Dot-notation key access: `$config->get('berlioz.debug.enable')`

### Service Container (`src/ServiceContainer/`)

PSR-11 dependency injection container with auto-wiring. `Container` delegates to a chain: DefaultContainer →
ProviderContainer → AutoWiringContainer. `Instantiator` provides reflection-based auto-wiring. `Inflector` auto-calls
setter methods when a service implements a given interface (the "Aware" pattern).

### Event Manager (`src/EventManager/`)

PSR-14 event dispatcher. Dispatches through a provider chain (SubscriberProvider → ListenerProvider). Supports lazy
subscribers, named stoppable events, and delegate dispatchers.

### Router (`src/Router/`)

`Router` and `Route` both implement `RouteSetInterface` (composite pattern: routes can be groups). Path syntax: `{id}`,
`{id:regex}`, `{id::int}`, `{id::uuid4}`, `{id::slug}`. Optional segments: `[/page/{page}]`. Routes compile to regex,
cached via serialization.

### HTTP Core (`src/Http/Core/`)

HTTP application framework. `HttpApp` extends `AbstractApp` and implements PSR-15 `RequestHandlerInterface`. Middleware
pipeline (Russian doll) wraps `ControllerHandler`. Routes are built from `#[Route]`/`#[RouteGroup]` PHP 8 attributes by
`RouterBuilder`.

**Controller return type handling:** `ResponseInterface` → pass through, `null`/empty → 204, scalar → 200, other → JSON.

### HTTP Message (`src/Http/Message/`)

Pure PSR-7/PSR-17 implementation with no framework dependencies. `HttpFactory` implements all 6 PSR-17 factory
interfaces via traits. Includes pluggable body parsers and specialized streams.

### HTTP Client (`src/Http/Client/`)

PSR-18 HTTP client. Adapter pattern for transport (cURL, stream, HAR replay). `Session` provides stateful cookies and
history. Follow-redirect (method-aware for 307/308), retry with backoff.

### CLI Core (`src/Cli/Core/`)

CLI application framework, structurally mirrors HTTP Core. `CliApp` extends `AbstractApp`. Commands use `#[Argument]`
attributes for argument declaration. `Console` extends League CLImate. Entry point: `src/Cli/Core/berlioz`.

### Standalone Components

- **Mailer** -- strategy pattern for transports (SMTP, PHP mail)
- **Form** -- composite tree of elements with collectors, hydrators, transformers, validators; bidirectional object
  mapping, PSR-7 integration
- **FlashBag** -- single class, session-backed, consume-on-read
- **HtmlSelector** -- CSS→XPath translation, jQuery-like query API
- **QueueManager** -- multiple queue backends (AMQP, Redis, SQS, DB, Memory, Null); `Worker` with rate limiting,
  backoff, signal handling

### Integration Packages (`src/Package/`)

All follow the same pattern: `BerliozPackage` extending `AbstractPackage` + `ServiceProvider` + optional Debug section,
middleware, CLI commands.

- **Twig** -- Twig template engine integration with extensions and `TwigAwareInterface`
- **Hector** -- Hector ORM integration with middleware, event subscriber, CLI commands
- **QueueManager** -- QueueManager integration with queue factories, CLI commands, built-in job handlers

## Coding Conventions

### Commit Messages

This project follows [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/).

Format: `<type>(<scope>): <description>`

**Types:** `feat`, `fix`, `docs`, `refactor`, `test`, `chore`, `perf`, `style`, `ci`, `build`.

**Scope** is the package name. For nested packages, use `:` as sub-scope separator:

```
feat(router): add wildcard route parameters
fix(http:core): handle empty body in ControllerHandler
docs(package:twig): update TwigExtension usage examples
refactor(service-container): simplify auto-wiring resolution
test(queue-manager): add worker backoff tests
chore(release): prepare v3.1.0
```

Breaking changes use `!` after the scope: `feat(config)!: remove INI adapter support`

### Formatting

| Rule                | Value                                      |
|---------------------|--------------------------------------------|
| Coding standard     | **PSR-12**                                 |
| Indent              | **4 spaces** (PHP), 2 spaces (other files) |
| Max line length     | **120 characters**                         |
| End of line         | **LF**                                     |
| Charset             | **UTF-8**                                  |
| Final newline       | **Yes**                                    |
| Trailing whitespace | **Trim** (except Markdown)                 |

### PHP File Template

Every PHP file must follow this structure:

```php
<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2026 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\PackageName;

use Some\Dependency;

class ClassName
{
}
```

**Non-negotiable rules:**

- `declare(strict_types=1)` in **every** PHP file
- License block comment header (not PHPDoc)
- All imports via `use` statements -- never inline fully qualified names
- One class/interface/trait/enum per file

### Naming Conventions

| Element        | Convention                                 | Example                                  |
|----------------|--------------------------------------------|------------------------------------------|
| Interface      | Suffix `Interface`                         | `RouteInterface`, `ConfigInterface`      |
| Trait          | Suffix `Trait`                             | `RouteSetTrait`, `ContainerAwareTrait`   |
| Abstract class | Prefix `Abstract`                          | `AbstractApp`, `AbstractController`      |
| Exception      | Suffix `Exception`, in `Exception/` subdir | `RoutingException`, `ContainerException` |
| Test class     | Suffix `Test`                              | `RouterTest`, `ConfigTest`               |

### Key Patterns

**Aware Pattern** -- pervasive dependency injection via interface + trait + inflector:

```
{Xxx}AwareInterface  →  get{Xxx}(): ?{Xxx}, set{Xxx}({Xxx}): static
{Xxx}AwareTrait      →  implements the interface
Inflector            →  auto-calls set{Xxx}() when a service implements the interface
```

**Exception Design** -- each package has its own `Exception/` directory with a root exception extending `\Exception` and
specialized sub-exceptions. Named static constructors are common: `NotFoundException::notFound($id)`.

**Serialization** -- custom `__serialize()` / `__unserialize()` methods (not the `Serializable` interface).

**Package Lifecycle** -- `config()` → `register(Container)` → `boot(Core)`.

### PHP 8.x Features in Use

- Constructor property promotion (preferred way to declare properties)
- Union types, `match` expressions, nullsafe `?->`, named arguments -- all used **heavily**
- Enums, `readonly class`, `readonly` properties -- used where appropriate
- Trailing commas everywhere
- PHP 8 attributes for routing (`#[Route]`, `#[RouteGroup]`), CLI (`#[Argument]`), tests (`#[DataProvider]`)
- `static` return type for fluent chainable setters

### PHPDoc

- **Interfaces/abstract classes:** Full PHPDoc on every method (`@param`, `@return`, `@throws`)
- **Concrete implementations:** `/** @inheritDoc */` when overriding
- **Class docblock:** `Class ClassName.` or `Interface InterfaceName.`
- **Complex types:** PHPDoc for arrays of objects (`@var Attribute[]`), generics (`@template T`)
- **Simple types:** Native type declarations preferred, PHPDoc only when adding information

## Testing

- **Framework:** PHPUnit 11 with **attributes** (not annotations)
- **Config:** `phpunit.xml.dist` -- 17 named test suites, one per package
- **Strict:** `failOnDeprecation`, `failOnRisky`, `failOnWarning` all enabled
- **Test suite names** match `phpunit.xml.dist` exactly (e.g., `Berlioz Router test suite`)

```bash
vendor/bin/phpunit                                          # All tests
vendor/bin/phpunit --testsuite="Berlioz Router test suite"  # Single package
```

### Test Conventions

- Test files: `*Test.php`, namespace `Berlioz\{Package}\Tests\` mirroring `src/`
- Data providers: `public static` methods, named `provide*`, referenced with `#[DataProvider('...')]`
- Mocking: PHPUnit built-in `createMock()` / `getMockBuilder()` only (no Mockery/Prophecy)
- Method naming: `testMethodName()`, variants with underscore: `testGenerate_withPrefix()`
- Fake/helper classes live alongside tests (e.g., `tests/Form/Fake/`)

### `tests_env/` Directories

Some packages have `tests_env/` directories containing **fake Berlioz project environments** for integration tests (
config, fake services, stub vendor). Found in: `tests/Core/`, `tests/Cli/Core/`, `tests/Http/Core/`, `tests/Package/*/`.
Autoloaded under `TestProject` namespaces.

## Changelogs & Releases

Each package has its own `CHANGELOG.md` at the root of its directory (e.g., `src/Router/CHANGELOG.md`). The monorepo
root also has a `CHANGELOG.md` that aggregates all package changes per version.

**Format:** [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) with categories: Added, Changed, Deprecated,
Removed, Fixed, Security, Docs.

### Workflow for contributors

When you modify a package, **add your changes to the `## [Unreleased]` section** of that package's `CHANGELOG.md`.
Do **not** touch the root `CHANGELOG.md` or assign a version number -- the release tooling handles that.

Example -- after adding a feature to the Router:

```markdown
## [Unreleased]

### Added

- Support for wildcard route parameters
```

### Release process

> **Lockstep versioning — release ALL packages together.** Every package is always released under the
> **same version** as the monorepo, **even packages with no changes** (they get a `_No changes in this release._`
> entry). This is mandatory, not cosmetic. Never release a subset of packages, even for a single-package fix.

**Why all packages must share the version:**

1. **`self.version` dependencies.** Inter-package `require` constraints use `"berlioz/xxx": "self.version"`
   (see any package `composer.json`). So `berlioz/cli-core:3.1.1` requires `berlioz/core:3.1.1`. If `core` is
   never tagged `3.1.1`, installing the patched package fails with an unsatisfiable constraint.
2. **Subsplit tag propagation.** On a `v*` tag, the subsplit action recomputes each package's split hash from
   its directory contents and only propagates the tag if **no tag already points at that hash**. A package whose
   directory is unchanged produces the *same* hash as the previous release (already tagged), so the new tag is
   **skipped** for it. Bumping its `CHANGELOG.md` (the `_No changes_` entry) changes the directory content →
   new hash → the tag propagates to every mirror.
3. **Consistency.** This matches the broader PHP-monorepo convention (Symfony, Laravel/Illuminate, etc.).

`bin/prepare-release.php` automates versioning. **Always run it without a package filter:**

```bash
php bin/prepare-release.php <version> <date> [--dry-run]

# Examples:
php bin/prepare-release.php 3.1.1 2026-06-10 --dry-run    # Preview ALL packages first
php bin/prepare-release.php 3.1.1 2026-06-10              # Release ALL packages (lockstep)
```

The optional `[package-names...]` argument exists but is **reserved for exceptional manual recovery only**
(e.g. fixing up a single mishandled changelog). It must **not** be used for normal releases, because it leaves
the other packages behind and breaks `self.version` resolution and tag propagation as described above.

What it does:
1. For each package: renames `[Unreleased]` → `[version] - date` and creates a fresh empty `[Unreleased]` section.
   Packages with an empty `[Unreleased]` get a `_No changes in this release._` placeholder so their split hash
   changes and the tag propagates.
2. Aggregates all package items into the root `CHANGELOG.md` under the same version, prefixed with the package name
   (e.g., `[router] Support for wildcard route parameters`)
3. Merges intelligently if the version block already exists (idempotent)

After running it, commit everything as `chore(release): prepare v<version>`, then tag `v<version>` to trigger the
subsplit publication to all mirrors.

## Issues & Pull Requests

`CONTRIBUTING.md` is the source of truth; this is the working convention used in this repo.

- **One issue per bug/feature**, one branch, one PR — keep each PR atomic (a single fix or feature).
- **Branch naming:** `fix/<slug>` for bugfixes, `feat/<slug>` (or `feature/<slug>`) for features, branched from `3.x`.
- **Issues** use the templates in `.github/ISSUE_TEMPLATE/`; tag the affected package with a `package: <name>` label (e.g. `package: router`) and include a confirmed reproduction.
- **PRs** target `3.x`, follow `.github/PULL_REQUEST_TEMPLATE.md`, and link the issue with `closes #N`. A good description has **Summary / Fix / Tests / Validation** sections (test + assertion counts, Rector result).
- **Merging/closing PRs:** prefer **squash merge** to keep a clean, linear history on `3.x` (one commit per PR). The squash commit message must follow Conventional Commits.
- **Do not push fixes directly to `3.x`** — go through a branch + PR even for small changes, so the issue/PR trail stays consistent.
- Sub-package repos are read-only mirrors: **everything goes through the monorepo.**

## Tooling

| Tool         | Command                       | Purpose                      |
|--------------|-------------------------------|------------------------------|
| PHPUnit      | `vendor/bin/phpunit`          | Test suite                   |
| Rector       | `vendor/bin/rector --dry-run` | Code quality check           |
| Rector (fix) | `vendor/bin/rector`           | Auto-fix code quality issues |
| PHPStan      | `vendor/bin/phpstan`          | Static analysis              |

## CI/CD

- **Tests:** GitHub Actions on push to `main`/`*.x`, PRs, and daily. Matrix: PHP 8.2-8.5 x prefer-lowest/prefer-stable.
  Coverage via xdebug.
- **Subsplit:** Automatically synchronizes each `src/` subdirectory to its read-only mirror repository.
