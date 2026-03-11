# MySQL Driver

## Configuration

```php
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

$db = Db::newInstanceOf(MySQL::NAME, $config); // 'mysql'
```

## Requirements

| Requirement       | Minimum         |
| ----------------- | --------------- |
| MySQL / MariaDB   | 8.0 / 10.5      |
| PHP PDO extension | `ext-pdo_mysql` |

Install the extension on Ubuntu / Debian:

```bash
sudo apt-get install php-mysql
```

## Generated DDL

Tables are created with:

```sql
CREATE TABLE `app_users` (
  `user_id`    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_name`  VARCHAR(255) NOT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Driver quirks

- **Auto-increment**: uses `AUTO_INCREMENT` on BIGINT PK columns.
- **LIMIT on DELETE/UPDATE**: Gobl rewrites single-table DELETE/UPDATE
  with `DELETE FROM t AS alias WHERE ... LIMIT n` (standard form - not
  the multi-table `DELETE alias FROM t AS alias` form, which MySQL does
  not accept with LIMIT).
- **Strict mode**: enable `sql_mode = 'STRICT_TRANS_TABLES'` on the
  server for consistent null/truncation behaviour.
- **Transactions**: fully supported via InnoDB engine.

## Useful MySQL-specific options

```ini
# my.cnf
[mysqld]
character-set-server  = utf8mb4
collation-server      = utf8mb4_unicode_ci
sql_mode              = STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO
```
