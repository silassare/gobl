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

| Column | Type | Description |
|--------|------|-------------|
| `version` | INTEGER PK | The version number returned by `getVersion()` |
| `label` | VARCHAR(255) | The label returned by `getLabel()` |
| `applied_at` | INTEGER | Unix timestamp of when the migration was applied |

---

## Recommended workflow

1. Create a new class implementing `MigrationInterface` for each schema change.
2. Give it a monotonically increasing `getVersion()` number.
3. Return the DDL SQL as a string from `up()` and the complementary SQL from `down()`.
4. Call `$runner->migrate()` in your deployment bootstrap.
5. Use `$runner->rollback()` during development to iterate.
6. Commit migration files alongside application code.
