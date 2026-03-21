# Connecting to a Database

## `DbConfig`

`DbConfig` holds the driver-specific connection settings.
The **driver name is NOT stored in `DbConfig`** - it is passed as the first
argument to `Db::newInstanceOf()`.

```php
use Gobl\DBAL\DbConfig;

$config = new DbConfig([
    'db_host'         => '127.0.0.1',
    'db_port'         => 3306,
    'db_name'         => 'my_app',
    'db_user'         => 'root',
    'db_pass'         => 'secret',
    'db_charset'      => 'utf8mb4',            // optional
    'db_collate'      => 'utf8mb4_unicode_ci', // optional
    'db_table_prefix' => 'app_',               // optional - prepended to every table name
]);
```

### All recognized keys

| Key                 | Default              | Description                                                             |
| ------------------- | -------------------- | ----------------------------------------------------------------------- |
| `db_host`           | `""`                 | Database server hostname or IP                                          |
| `db_port`           | `""`                 | Server port                                                             |
| `db_name`           | `""`                 | Database / schema name                                                  |
| `db_user`           | `""`                 | Login user                                                              |
| `db_pass`           | `""`                 | Login password                                                          |
| `db_charset`        | `utf8mb4`            | Connection character set                                                |
| `db_collate`        | `utf8mb4_unicode_ci` | Connection collation                                                    |
| `db_table_prefix`   | `""`                 | Prefix prepended to every table name                                    |
| `db_server_version` | `""`                 | Cached server version - avoids an extra query on `getDbServerVersion()` |

---

## Opening a connection - `Db::newInstanceOf()`

```php
use Gobl\DBAL\Db;
use Gobl\DBAL\DbConfig;

$config = new DbConfig([/* ... */]);

// First argument: driver name string.
// Second argument: DbConfig instance.
$db = Db::newInstanceOf('mysql', $config);
```

Supported driver name strings:

| Constant           | String value   | RDBMS           |
| ------------------ | -------------- | --------------- |
| `MySQL::NAME`      | `'mysql'`      | MySQL / MariaDB |
| `PostgreSQL::NAME` | `'postgresql'` | PostgreSQL      |
| `SQLite::NAME`     | `'sqlite'`     | SQLite          |

The underlying PDO connection is established **lazily** on the first query.

---

## Driver-specific config examples

::: code-group

```php [MySQL]
use Gobl\DBAL\Db;
use Gobl\DBAL\DbConfig;
use Gobl\DBAL\Drivers\MySQL\MySQL;

$config = new DbConfig([
    'db_host'         => '127.0.0.1',
    'db_port'         => 3306,
    'db_name'         => 'my_app',
    'db_user'         => 'root',
    'db_pass'         => 'secret',
    'db_charset'      => 'utf8mb4',
    'db_collate'      => 'utf8mb4_unicode_ci',
    'db_table_prefix' => 'app_',
]);

$db = Db::newInstanceOf(MySQL::NAME, $config);
```

```php [PostgreSQL]
use Gobl\DBAL\Db;
use Gobl\DBAL\DbConfig;
use Gobl\DBAL\Drivers\PostgreSQL\PostgreSQL;

$config = new DbConfig([
    'db_host'         => '127.0.0.1',
    'db_port'         => 5432,
    'db_name'         => 'my_app',
    'db_user'         => 'postgres',
    'db_pass'         => 'secret',
    'db_table_prefix' => 'app_',
]);

$db = Db::newInstanceOf(PostgreSQL::NAME, $config);
```

```php [SQLite]
use Gobl\DBAL\Db;
use Gobl\DBAL\DbConfig;
use Gobl\DBAL\Drivers\SQLite\SQLite;

$config = new DbConfig([
    'db_name' => '/var/data/my_app.db', // absolute path, or ':memory:'
]);

$db = Db::newInstanceOf(SQLite::NAME, $config);
```

:::

---

## Loading a schema

Once you have a `$db` instance, load your table definitions through
`$db->ns()`, which returns a fluent `NamespaceBuilder`:

```php
$tables = require 'config/tables.php'; // associative array - see Schema guide

$db->ns('My\\App\\Db')
   ->schema($tables);
```

To also register the namespace for ORM generation, chain `enableORM()`:

```php
$db->ns('My\\App\\Db')
   ->schema($tables)
   ->enableORM(__DIR__ . '/src/Db'); // output directory for generated classes
```

---

## Building the physical tables

```php
// Generate CREATE TABLE DDL for a namespace (returns a SQL string).
$sql = $db->getGenerator()->buildDatabase('My\\App\\Db');

// Execute the DDL statements (uses IF NOT EXISTS where supported):
$db->executeMulti($sql);
```

---

## Checking server version

```php
// Pass the $db instance so it can query the server if db_server_version is blank.
$version = $config->getDbServerVersion($db);
echo $version; // e.g. "8.0.32"
```
