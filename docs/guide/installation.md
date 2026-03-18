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
; php.ini
extension=pdo_pgsql
```

```ini [SQLite]
; php.ini
extension=pdo_sqlite
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
