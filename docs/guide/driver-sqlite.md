# SQLite Driver

## Configuration

```php
use Gobl\DBAL\Db;
use Gobl\DBAL\DbConfig;
use Gobl\DBAL\Drivers\SQLite\SQLite;

$config = new DbConfig([
    'db_name'         => '/var/data/my_app.db',  // absolute path to .db file
    'db_table_prefix' => 'app_',                 // optional
]);

$db = Db::newInstanceOf(SQLite::NAME, $config);
```

Use `:memory:` for an in-memory database (tests, demos):

```php
$config = new DbConfig(['db_name' => ':memory:']);
$db = Db::newInstanceOf(SQLite::NAME, $config);
```

## Requirements

| Requirement       | Minimum          |
| ----------------- | ---------------- |
| SQLite            | 3.35             |
| PHP PDO extension | `ext-pdo_sqlite` |

SQLite is bundled with PHP on most platforms; no extra installation is
usually needed.

## Generated DDL

```sql
CREATE TABLE "app_users" (
  "user_id"   INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
  "user_name" TEXT NOT NULL
);
```

## Driver quirks

- **Auto-increment**: uses `INTEGER PRIMARY KEY AUTOINCREMENT`.
- **LIMIT on DELETE/UPDATE**: SQLite does not support `DELETE ... LIMIT`
  without the `SQLITE_ENABLE_UPDATE_DELETE_LIMIT` compile option (not
  enabled by default). Gobl rewrites these as:
    ```sql
    DELETE FROM "t" WHERE rowid IN (
        SELECT rowid FROM "t" AS alias WHERE ... ORDER BY ... LIMIT n
    )
    ```
- **Concurrency**: SQLite uses file-level locking. For multi-process or
  high-write workloads prefer MySQL or PostgreSQL.
- **WAL mode** (recommended for concurrent reads):
    ```php
    // Execute raw DDL/PRAGMA statements:
    $db->execute('PRAGMA journal_mode=WAL');
    ```
- **Foreign keys** must be enabled explicitly per connection:
    ```php
    $db->execute('PRAGMA foreign_keys = ON');
    ```
- **DDL changes**: SQLite has limited `ALTER TABLE`. Gobl's diff/migrate
  recreates tables when complex schema changes are needed.
