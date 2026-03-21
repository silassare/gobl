# Migrations

`MigrationRunner` tracks and applies migrations against a live database,
recording state in a `_gobl_migrations` bookkeeping table.

## `MigrationInterface`

Each migration is a PHP class that implements `MigrationInterface`:

```php
use Gobl\DBAL\Interfaces\MigrationInterface;
use Gobl\DBAL\MigrationMode;

class CreateUsersTable implements MigrationInterface
{
    public function getVersion(): int   { return 1; }
    public function getLabel(): string  { return 'create_users_table'; }
    public function getTimestamp(): int { return 1700000000; }
    public function getSchema(): array  { return []; }  // optional schema array
    public function getConfigs(): array { return []; }

    public function up(): string
    {
        return "
            CREATE TABLE app_users (
                user_id    BIGINT NOT NULL AUTO_INCREMENT,
                user_name  VARCHAR(100) NOT NULL,
                user_email VARCHAR(255) NOT NULL,
                PRIMARY KEY (user_id)
            );
        ";
    }

    public function down(): string
    {
        return "DROP TABLE IF EXISTS app_users;";
    }

    public function beforeRun(MigrationMode $mode, string $query): bool|string
    {
        return true; // return false to skip, or a new SQL string to override
    }

    public function afterRun(MigrationMode $mode): void {}
}
```

A migration file typically returns the instance when included:

```php
// migrations/0001_create_users.php
<?php
return new CreateUsersTable();
```

---

## Running migrations

```php
use Gobl\DBAL\MigrationRunner;

$runner = new MigrationRunner($db);

// Register migration instances directly:
$runner->add(new CreateUsersTable(), new AddPostsTable());

// Or load from PHP files that return MigrationInterface instances:
$runner->addFromFile(
    'migrations/0001_create_users.php',
    'migrations/0002_add_posts.php'
);

// Apply all pending migrations (idempotent - skips already-applied versions):
$applied = $runner->migrate();
// Returns: int[] - the version numbers that were applied this run.
```

Apply only up to a specific version:

```php
$runner->migrate(target_version: 5);
```

---

## Rolling back

```php
// Roll back the most recent migration:
$runner->rollback();

// Roll back the last N migrations (in reverse order):
$runner->rollback(3);

// Returns: int[] - version numbers that were rolled back.
```

---

## Status report

```php
$status = $runner->status();
// Returns:
// [
//   ['version' => 1, 'label' => 'create_users', 'applied' => true,  'applied_at' => 1700000000],
//   ['version' => 2, 'label' => 'add_posts',     'applied' => false, 'applied_at' => null],
// ]
```

---

## Migration tracking table

`MigrationRunner` auto-creates `_gobl_migrations` on first use:

| Column       | Type         | Description                                      |
| ------------ | ------------ | ------------------------------------------------ |
| `version`    | INTEGER PK   | The version number returned by `getVersion()`    |
| `label`      | VARCHAR(255) | The label returned by `getLabel()`               |
| `applied_at` | INTEGER      | Unix timestamp of when the migration was applied |

---

## Schema Diff engine

`Diff` compares two `RDBMSInterface` instances (old vs. new schema state) and
produces the minimal ALTER/ADD/DROP SQL needed to bring the old schema up to
the new one. Pass the result to `generateMigrationFile()` to get a ready-to-use
anonymous-class migration file.

```php
use Gobl\DBAL\Diff\Diff;

$old_db = /* existing DB instance, locked */;
$new_db = /* updated DB instance, locked */;

$diff = new Diff($old_db, $new_db);

if ($diff->hasChanges()) {
    // Write the generated migration PHP file to disk.
    file_put_contents('migrations/0002_auto.php', (string) $diff->generateMigrationFile(2));
}
```

The generated file is a PHP file returning an anonymous `MigrationInterface`
class with `up()` and `down()` SQL strings. It can be passed directly to
`MigrationRunner::addFromFile()`.

### Driver-specific ALTER COLUMN behaviour

Each driver emits the ALTER syntax that is correct for that RDBMS:

| Driver     | Column-type change SQL                                     |
| ---------- | ---------------------------------------------------------- |
| MySQL      | `ALTER TABLE t CHANGE col col new_type ...`                |
| PostgreSQL | `ALTER TABLE t ALTER COLUMN col new_type ...`              |
| SQLite     | SQLite ignores column-type differences (no-op in the diff) |

#### PostgreSQL: automatic USING clause for JSON casts

PostgreSQL cannot implicitly cast `TEXT`/`VARCHAR` or a TEXT-stored JSON column
to `JSONB`. When the diff detects that a column is changing _to_ a native JSON
type (`native_json: true`) from a plain string or text-stored JSON type, Gobl
automatically appends the required `USING` expression:

```sql
-- string/text column -> native JSONB
ALTER TABLE "docs" ALTER COLUMN "doc_payload" jsonb NOT NULL
    USING to_jsonb("doc_payload"::text);

-- TEXT-stored JSON (native_json=false) -> native JSONB
ALTER TABLE "docs" ALTER COLUMN "doc_payload" jsonb NOT NULL
    USING to_jsonb("doc_payload"::text);
```

The reverse direction (native JSONB -> text/string) does not require a USING
clause and is emitted as a plain `ALTER COLUMN` statement.

---

## Rename tracking

By default the diff engine identifies a table by `md5(namespace/name)` and a column by
`md5(table_key/prefix_name)`. Renaming without extra hints causes the engine to emit a
DROP + CREATE instead of an ALTER RENAME.

Set `old_name` (and optionally `old_prefix` for columns) on the **new** schema definition
before generating the diff. These options are ephemeral: they are **not** written by
`toArray()`. Remove them once the migration has been applied.

### Fluent builder

```php
// Table rename: 'users' -> 'members'
$db->ns('App\Db')->table('members', function (TableBuilder $t) {
    $t->oldName('users');   // remove after migration is applied
    $t->id();
    $t->string('name');
});

// Column rename: 'user_email' -> 'user_email_address'
$t->string('email_address');
$t->useColumn('email_address')->oldName('email');
// prefix unchanged - current prefix ('user') is reused

// Column that also gained a prefix: previously 'email' (no prefix) -> 'user_email'
$t->string('email');
$col = $t->useColumn('email');
$col->oldName('email');
$col->oldPrefix('');         // explicit empty string = column had no prefix before
```

### Array schema

```php
$db->ns('App\Db')->schema([
    'members' => [
        'old_name'      => 'users',     // remove after migration
        'singular_name' => 'member',
        'plural_name'   => 'members',
        'column_prefix' => 'member',
        'columns' => [
            'id'            => ['type' => 'bigint', 'auto_increment' => true],
            'email_address' => ['type' => 'string', 'old_name' => 'email'],
        ],
    ],
]);
```

::: warning One-shot option
Leaving `old_name` across two separate rename cycles causes the diff key to stay anchored
to the first old name. Always remove it after the migration has been run.
:::

---

## Recommended workflow

1. Create a new class implementing `MigrationInterface` for each schema change.
2. Give it a monotonically increasing `getVersion()` number.
3. Return the DDL SQL as a string from `up()` and the complementary SQL from `down()`.
4. Call `$runner->migrate()` in your deployment bootstrap.
5. Use `$runner->rollback()` during development to iterate.
6. Commit migration files alongside application code.
