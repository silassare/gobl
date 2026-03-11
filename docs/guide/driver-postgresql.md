# PostgreSQL Driver

## Configuration

```php
use Gobl\DBAL\Db;
use Gobl\DBAL\DbConfig;
use Gobl\DBAL\Drivers\PostgreSQL\PostgreSQL;

$config = new DbConfig([
    'db_host'         => '127.0.0.1',
    'db_port'         => 5432,
    'db_name'         => 'my_app',
    'db_user'         => 'postgres',
    'db_pass'         => 'secret',
    'db_table_prefix' => 'app_',     // optional
]);

$db = Db::newInstanceOf(PostgreSQL::NAME, $config); // 'postgresql'
```

## Requirements

| Requirement       | Minimum         |
| ----------------- | --------------- |
| PostgreSQL        | 13              |
| PHP PDO extension | `ext-pdo_pgsql` |

Install the extension on Ubuntu / Debian:

```bash
sudo apt-get install php-pgsql
```

## Generated DDL

```sql
CREATE TABLE "app_users" (
  "user_id"   BIGSERIAL NOT NULL,
  "user_name" VARCHAR(255) NOT NULL,
  PRIMARY KEY ("user_id")
);
```

## Driver quirks

- **Auto-increment**: uses `BIGSERIAL` for auto-increment PK columns.
- **LIMIT on DELETE/UPDATE**: PostgreSQL does not support `DELETE ... LIMIT`.
  Gobl rewrites these as:
    ```sql
    DELETE FROM "t" WHERE ctid IN (
        SELECT ctid FROM "t" AS alias WHERE ... ORDER BY ... LIMIT n
    )
    ```
    The subquery alias is required so ORM-generated alias-prefixed WHERE
    columns resolve correctly.
- **Identifiers**: all table and column names are double-quoted.
- **Transactions**: fully supported including `SAVEPOINT`.
- **Schema search path**: set `search_path` in `pg_hba.conf` or via a
  `SET search_path TO ...` query after connecting if you use non-default
  schemas.
