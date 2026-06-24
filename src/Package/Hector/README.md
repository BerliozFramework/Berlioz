# Hector ORM package for Berlioz Framework

[![Latest Version](https://img.shields.io/packagist/v/berlioz/hector-package.svg?style=flat-square)](https://github.com/BerliozFramework/HectorPackage/releases)
![Packagist Dependency Version](https://img.shields.io/packagist/dependency-v/BerliozFramework/HectorPackage/php?version=3.x-dev&style=flat-square)
[![Software license](https://img.shields.io/github/license/BerliozFramework/HectorPackage.svg?style=flat-square)](https://github.com/BerliozFramework/HectorPackage/blob/3.x/LICENSE)

> **Note**
>
> This repository is a **read-only split** from
> the [main Berlioz Framework repository](https://github.com/BerliozFramework/Berlioz).
>
> For contributions, issues, or more information, please visit
> the [main Berlioz Framework repository](https://github.com/BerliozFramework/Berlioz).
>
> **Do not open issues or pull requests here.**

---

This package is intended to provide **Hector ORM** in **Berlioz Framework**.

📖 **[Full documentation](https://getberlioz.com/docs/3.x/guides/orm/hector)**

## Installation

You can install **Hector ORM Package for Berlioz Framework** with [Composer](https://getcomposer.org/), it's the
recommended installation.

```shell
$ composer require berlioz/hector-package
```

## Migrations

This package integrates the Hector ORM
[migration component](https://gethectororm.com/docs/current/components/migration) and exposes it through CLI commands.

Configure the migration source and tracker under the `hector.migration` key:

```json5
{
  hector: {
    migration: {
      // Provider supplying the migrations: 'directory' | 'psr4' | a container-resolvable FQCN
      provider: {
        type: 'directory',
        directory: '{config: berlioz.directories.app}/migrations',
        namespace: null, // required when type='psr4'
        pattern: '*.php',
        depth: 0
      },
      // Tracker recording applied migrations: 'db' | 'file' | a container-resolvable FQCN
      tracker: {
        type: 'db',
        table: 'hector_migrations',
        file: '{config: berlioz.directories.var}/hector.migrations.json'
      },
      schema: null // optional schema name to enable schema introspection
    }
  }
}
```

### Commands

| Command                  | Description                          |
|--------------------------|--------------------------------------|
| `hector:migrate`         | Apply pending migrations             |
| `hector:migrate:down`    | Revert applied migrations            |
| `hector:migrate:status`  | Show the status of all migrations    |

Both `hector:migrate` and `hector:migrate:down` accept `--dry-run` and `--interactive` (`-i`):

```shell
# Apply all pending migrations
$ php berlioz hector:migrate

# Apply at most N pending migrations
$ php berlioz hector:migrate --steps=3

# Dry-run: compile and dispatch events without executing (native runner dry-run)
$ php berlioz hector:migrate --dry-run

# Interactive: ask for confirmation before each migration
$ php berlioz hector:migrate --interactive

# Revert the last migration, asking for confirmation
$ php berlioz hector:migrate:down --interactive
```

## Documentation

For usage and examples, visit the
[official documentation on **getberlioz.com**](https://getberlioz.com/docs/3.x/guides/orm/hector).
