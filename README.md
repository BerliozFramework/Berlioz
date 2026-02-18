# Berlioz Framework

[![Latest Version](https://img.shields.io/packagist/v/berlioz/berlioz.svg?style=flat-square)](https://github.com/BerliozFramework/Berlioz/releases)
![Packagist Dependency Version](https://img.shields.io/packagist/dependency-v/berlioz/berlioz/php?version=3.x-dev&style=flat-square)
[![Software license](https://img.shields.io/github/license/BerliozFramework/Berlioz.svg?style=flat-square)](https://github.com/BerliozFramework/Berlioz/blob/3.x/LICENSE)
[![Build Status](https://img.shields.io/github/actions/workflow/status/BerliozFramework/Berlioz/tests.yml?branch=3.x&style=flat-square&label=tests)](https://github.com/BerliozFramework/Berlioz/actions/workflows/tests.yml?query=branch%3A3.x)

**Berlioz Framework** is a slim PHP framework, to easily publish your online projects like website, intranet, api... or
all your creations!

> **All development, issues, and pull requests are managed in this monorepo.**  
> Sub-repositories are automatically synchronized for each package.

📖 **[Full documentation](https://getberlioz.com)**

## Quick start

```bash
composer create-project berlioz/website-skeleton my-project --remove-vcs
```

That's it! You are ready to develop your project.

## Features

- **Lightweight & fast** - Minimal overhead, designed to stay out of your way.
- **PSR compliant** - Implements PSR-7, PSR-11, PSR-14, PSR-17 and PSR-18 standards.
- **Monorepo architecture** - All packages are developed, tested and released together.
- **HTTP & CLI** - First-class support for both web applications and command-line tools.
- **Standalone components** - Every component can be used independently in any PHP project.

## Requirements

- PHP >= 8.2
- Extensions: `fileinfo`, `intl`, `mbstring`, `xml`

## Packages

This monorepo contains the following packages, each available as a standalone Composer package:

### Core

| Package                                                                   | Description                                                                   |
|---------------------------------------------------------------------------|-------------------------------------------------------------------------------|
| [**Core**](src/Core) `berlioz/core`                                       | Foundation of the framework: application lifecycle, configuration, debugging. |
| [**Config**](src/Config) `berlioz/config`                                 | Configuration file management (JSON5, YAML, PHP).                             |
| [**Service Container**](src/ServiceContainer) `berlioz/service-container` | Dependency injection container (PSR-11).                                      |
| [**Event Manager**](src/EventManager) `berlioz/event-manager`             | Event dispatcher (PSR-14).                                                    |
| [**Router**](src/Router) `berlioz/router`                                 | HTTP route management with PSR-7 support.                                     |

### HTTP

| Package                                                     | Description                                                        |
|-------------------------------------------------------------|--------------------------------------------------------------------|
| [**HTTP Core**](src/Http/Core) `berlioz/http-core`          | HTTP application core: request handling, controllers, middlewares. |
| [**HTTP Message**](src/Http/Message) `berlioz/http-message` | PSR-7 HTTP messages and PSR-17 HTTP factories implementation.      |
| [**HTTP Client**](src/Http/Client) `berlioz/http-client`    | HTTP client with session/cookie support (PSR-18).                  |

### CLI

| Package                                         | Description                                            |
|-------------------------------------------------|--------------------------------------------------------|
| [**CLI Core**](src/Cli/Core) `berlioz/cli-core` | CLI application core: commands, console and lifecycle. |

### Components

| Package                                                       | Description                                         |
|---------------------------------------------------------------|-----------------------------------------------------|
| [**Mailer**](src/Mailer) `berlioz/mailer`                     | Email sending, with or without a local SMTP server. |
| [**Form**](src/Form) `berlioz/form`                           | HTML form building, validation and handling.        |
| [**FlashBag**](src/FlashBag) `berlioz/flash-bag`              | Flash messages (one-time user notifications).       |
| [**HTML Selector**](src/HtmlSelector) `berlioz/html-selector` | Query HTML documents with CSS selectors.            |
| [**Queue Manager**](src/QueueManager) `berlioz/queue-manager` | Background job processing with workers.             |

### Integration packages

| Package                                                                               | Description                                   |
|---------------------------------------------------------------------------------------|-----------------------------------------------|
| [**Twig Package**](src/Package/Twig) `berlioz/twig-package`                           | Twig template engine integration.             |
| [**Hector Package**](src/Package/Hector) `berlioz/hector-package`                     | Hector ORM integration for database access.   |
| [**Queue Manager Package**](src/Package/QueueManager) `berlioz/queue-manager-package` | Queue Manager integration into the framework. |

## Documentation

Full documentation is available at [**getberlioz.com**](https://getberlioz.com).

## Contributing

All contributions (bug reports, feature requests, pull requests) should be made on this monorepo.
Sub-repositories are read-only mirrors and are automatically synchronized.

## License

Berlioz Framework is open-sourced software licensed under the [MIT license](LICENSE).
