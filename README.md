# Gobl

A PHP 8.1+ **Database Abstraction Layer (DBAL)** and **ORM** with code generation
for MySQL/MariaDB, PostgreSQL, and SQLite.

## Features

- Schema-first: define tables, columns, constraints, and relations in PHP arrays or a fluent builder
- Typed query builder: `QBSelect`, `QBInsert`, `QBUpdate`, `QBDelete` with safe PDO value binding
- Code generation: strongly-typed PHP entity, controller, query, and CRUD classes per table
- Event-driven CRUD: before/after hooks for every write operation via `*Crud` classes
- Multi-driver: one schema, three databases - MySQL/MariaDB, PostgreSQL, SQLite
- Migration engine: `Diff` between schema versions + `MigrationRunner`

## Requirements

- PHP >= 8.1
- PDO extension with at least one of: `pdo_mysql`, `pdo_pgsql`, `pdo_sqlite`

## Installation

```bash
composer require silassare/gobl
```

## Documentation

Full documentation on GitHub:

- [Quick Start](https://github.com/silassare/gobl/blob/main/docs/guide/quick-start.md)
- [Schema Definition](https://github.com/silassare/gobl/blob/main/docs/guide/schema.md)
- [Column Types](https://github.com/silassare/gobl/blob/main/docs/guide/column-types.md)
- [Query Builder](https://github.com/silassare/gobl/blob/main/docs/guide/query-builder.md)
- [Filters](https://github.com/silassare/gobl/blob/main/docs/guide/filters.md)
- [ORM & Code Generation](https://github.com/silassare/gobl/blob/main/docs/guide/orm.md)
- [CRUD Events](https://github.com/silassare/gobl/blob/main/docs/guide/crud-events.md)

## Quick example

```php
use Gobl\DBAL\Drivers\MySQL\MySQL;
use Gobl\DBAL\DbConfig;

$db = MySQL::newInstanceOf(new DbConfig([
    'db_host' => '127.0.0.1',
    'db_name' => 'myapp',
    'db_user' => 'root',
    'db_pass' => 'secret',
]));

$db->ns('App\Db')->schema([
    'users' => [
        'singular_name' => 'user',
        'plural_name'   => 'users',
        'column_prefix' => 'user',
        'constraints' => [
            ['type' => 'primary_key', 'columns' => ['id']],
        ],
        'columns' => [
            'id'   => ['type' => 'bigint', 'auto_increment' => true, 'unsigned' => true],
            'name' => ['type' => 'string', 'max' => 60],
        ],
    ],
])->enableORM('/path/to/output');
```

## License

MIT
