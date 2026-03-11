# Installation

## Requirements

- PHP **>= 8.1** with extensions `pdo`, `json`, `bcmath`
- Composer

## Install via Composer

```bash
composer require silassare/gobl
```

## Database drivers

Gobl uses PHP's PDO layer. Enable the PDO extension for the database(s) you need:

::: code-group

```ini [MySQL / MariaDB]
; php.ini
extension=pdo_mysql
```

```ini [PostgreSQL]
extension=pdo_pgsql
```

```ini [SQLite]
extension=pdo_sqlite
```

:::

## Optional: phpDocumentor (API reference)

To generate the auto-linked API reference locally:

```bash
composer require --dev phpdocumentor/phpdocumentor
```

Then run:

```bash
vendor/bin/phpdoc run           # outputs to docs/api/
```

Or use the provided shortcut:

```bash
make docs-api
```

## Project structure after install

```
your-project/
├── composer.json
├── vendor/
│   └── silassare/gobl/
└── src/
    └── Db/             <- generated entity classes will go here
```

## Next step

-> [Quick Start](/guide/quick-start)
