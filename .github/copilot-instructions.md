# Gobl - AI Coding Instructions

**No hallucination.** Before writing any code, read the relevant source files. Every method name, signature, and option key must be verified in the codebase. When a bug or issue is found, ask for approval before fixing.

`AGENTS.md`, `CLAUDE.md`, and `GEMINI.md` are symlinks to `.github/copilot-instructions.md` — keep them that way.

## Architecture

PHP 8.1+ library: DBAL + ORM + CRUD event system. Supports MySQL, PostgreSQL, SQLite via PDO.

```
DBAL  (src/DBAL/)  — schema model, query builders, drivers, diff/migration
  ORM  (src/ORM/)  — entity base classes, controller, code generators (PHP/TS/Dart)
    CRUD  (src/CRUD/)  — before/after event system
```

Entry points: `src/Gobl.php` (static, forbidden name lists), `src/bootstrap.php` (constants, default type provider).

## Schema Definition

**Array-based** (canonical reference: `tests/assets/schemas.php`):

```php
$db->ns('App\Db')->schema([
    'users' => [
        'plural_name'   => 'users',
        'singular_name' => 'user',
        'column_prefix' => 'user',
        'constraints' => [
            ['type' => 'primary_key', 'columns' => ['id']],
            ['type' => 'unique_key',  'columns' => ['email']],
            ['type' => 'foreign_key', 'reference' => 'roles', 'columns' => ['role_id' => 'id']],
        ],
        'relations' => ['role' => ['type' => 'many-to-one', 'target' => 'roles']],
        'columns' => [
            'id'      => ['type' => 'bigint', 'auto_increment' => true, 'unsigned' => true],
            'role_id' => 'ref:roles.id',   // inherit type from roles.id (shared instance)
            'email'   => ['type' => 'string', 'min' => 5, 'max' => 255],
        ],
    ],
]);
```

**Fluent builder** (canonical reference: `tests/BaseTestCase.php::getSampleDB()`):

```php
$db->ns('App\Db')->table('users', function (TableBuilder $t) {
    $t->id();
    $t->string('name');
    $t->timestamps();       // created_at + updated_at
    $t->softDeletable();    // deleted + deleted_at columns
    $t->foreign('role_id', 'roles', 'id');
    $t->belongsTo('role')->from('roles');
});
```

`TableBuilder` column methods (check `src/DBAL/Builders/TableBuilder.php` for full signatures):
`id()`, `int()`, `bigint()`, `string()`, `bool()`, `float()`, `decimal()`, `date()`, `enum()`, `json()`, `jsonOf()`, `list()`, `listOf()`, `map()`, `mapOf()`, `timestamp()`, `timestamps()`, `softDeletable()`, `morph()`, `foreign()`, `sameAs()`.
Constraint/index shortcuts: `unique()`, `primary()`, `index()`.
Relation methods: `hasMany()`, `hasOne()`, `belongsTo()`, `belongsToMany()`.
`useColumn($name)` — returns an already-defined `Column` by name (throws `DBALRuntimeException` if not found).

### Schema rules (enforced, do not invent exceptions)

- Table and column names: `[a-z][a-z0-9_]*` only
- `column_prefix` is prepended to every column's DB name (prefix `user` + name `id` = full name `user_id`)
- `ref:table.column` — shared type instance; `cp:table.column` — cloned copy
- Forbidden column/relation names: `save`, `saved`, `is_saved`, `new`, `is_new`, `table`, `crud`, `qb`, `ctrl`, `results`, `register_collection`, `computed_value` (see `Gobl::getForbiddenColumnsName()`)
- Supported types: `bigint`, `int`, `bool`, `string`, `decimal`, `float`, `date`, `enum`, `json`, `list`, `map`
- `json`/`list`/`map` use native JSON storage by default (`native_json=true`). Opt out with `->nativeJson(false)`.
- Column relations in array schema: auto-detected from FK constraints; `link` key only needed for morph/through/custom join.

## Code Generation

```php
$db->ns('App\Db')->schema($tables)->enableORM('/path/to/output');
```

Per table, 5 class pairs generated. Files in `Base/` are overwritten on every run — never edit them.

| Kind       | Class name                                              | File location  |
| ---------- | ------------------------------------------------------- | -------------- |
| Entity     | `{SingularName}` / `{SingularName}Base`                 | root / `Base/` |
| Controller | `{PluralName}Controller` / `{PluralName}ControllerBase` | root / `Base/` |
| Query      | `{PluralName}Query` / `{PluralName}QueryBase`           | root / `Base/` |
| Results    | `{PluralName}Results` / `{PluralName}ResultsBase`       | root / `Base/` |
| Crud       | `{PluralName}Crud` / `{PluralName}CrudBase`             | root / `Base/` |

Class names driven by `ORMClassKind::getClassName()` — check source before assuming.

**Generated getter names** (`CSGeneratorORM::propertyGetterName()`):

- Column starts with `is_` → `is{Name}()`
- Column ends with `ed` → `is{Name}()`
- Otherwise → `get{Name}()`
- Setters: always `set{Name}()`
- Magic property access also works: `$entity->user_name` (full column name with prefix)

## ORM Entity

`ORMEntity` uses magic `__get`/`__set` keyed by full column name:

```php
$entity->user_name;           // read
$entity->user_name = 'Alice'; // write (validates via type)
$entity->save();              // persist
```

Key contracts (verify exact signatures in `src/ORM/ORMEntity.php`):

- **Dirty tracking** — `__set` hashes the new validated value; column marked dirty only when hash differs from the last-saved snapshot. Handles mutable values (e.g. `Map`) correctly.
- **`isSaved(bool $set_as_saved = false): bool`** — `true` when no dirty columns and not new. `isSaved(true)` snapshots hashes, clears dirty set, unsets isNew. Also auto-detects partial state when PDO row has fewer columns than the table defines.
- **`isNew(): bool`** — `true` until first successful `save()` or DB load.
- **`toIdentityKey(): string`** — stable opaque key from PK columns (`:` joined for composite). Used as map key in batch methods.
- **`markAsPartial(array $partial_columns): static`** — the only way to mark an entity as partial. Accepts short or full column names. Partial entities throw on access to unloaded columns.
- **`isPartial(): bool`**, **`isColumnLoaded(string $name): bool`** — guards for partial state.
- **`toArray()`** filters partial columns; **`toRow()`** returns raw row without filtering.

Internal `ORMEntity` properties use `_oeb_*` prefix. Subclass properties must use a single `_` prefix. Custom methods must NOT start with `get` or `set`.

**Computed value slots** (`_gobl_*` protocol):

- `QBSelect::selectComputed(string $var_name, string $expression): static`
- `QBSelect::computedAlias(string $var_name): string`
- `ORMEntity::getComputedValue(string $var_name): mixed`, `hasComputedValue(string $var_name): bool`
- PDO hydration of `_gobl_*` aliases is intercepted by `__set` and stored in `$_oeb_computed` — never validated or written to DB.

**Factory methods** (no `partial_columns` parameter — call `->markAsPartial()` afterward if needed):

- `ORM::entity(Table, bool $is_new = true, bool $strict = true): ORMEntity`
- `ORM::results(Table, QBSelect): ORMResults`
- `ORM::query(Table): ORMTableQuery`
- Generated: `EntityBase::new(bool $is_new = true, bool $strict = true): static`
- Generated: `EntityBase::results(QBSelect): ORMResults`
- `ORMResults::new(QBSelect): static` — abstract, implemented by generated Results class

## Relations

For full link-type implementations see `src/DBAL/Relations/` (`LinkColumns`, `LinkMorph`, `LinkThrough`, `LinkJoin`).

**Batch loading** — `ORMController` issues a single query for all hosts:

```php
// one relative per host (many-to-one)
$map = $ctrl->getRelativeBatch(array $host_entities, Relation $relation, ?ORMSelectOptionsInterface $options = null): array;
// $map[$host->toIdentityKey()] = TargetEntity|null

// all relatives per host (one-to-many)
$map = $ctrl->getAllRelativesBatch(array $host_entities, Relation $relation, ?ORMSelectOptionsInterface $options = null): array;
// $map[$host->toIdentityKey()] = TargetEntity[]
```

Empty host list returns `[]` immediately without a DB query.

**Count**:

```php
$count = $ctrl->countRelatives(ORMEntity $host, Relation $relation): int;
$map   = $ctrl->countRelativesBatch(array $host_entities, Relation $relation): array;
// $map[$host->toIdentityKey()] = int
```

**Relation column projection** — a relation may restrict which target columns are fetched:

```php
$t->belongsTo('client')->from('clients')->select('id', 'first_name');
// or in array schema: 'select' => ['id', 'first_name']
```

`Relation::getSelect()`, `setSelect()`, `resolveSelectColumns()` — see `src/DBAL/Relations/Relation.php`. Entities loaded via a non-null projection are automatically marked as partial.

**Access control** — `CRUD::assertReadRelative()` / `assertReadAllRelatives()` wrap `assertRead()` / `assertReadAll()`. `BeforeRead` and `BeforeReadAll` both mix in `HasRelationContext` trait; call `$action->getRelation()` to distinguish direct vs relational reads.

## CRUD Events

```php
$crud = UsersCrud::new();
$crud->onBeforeCreate(function (BeforeCreate $action): bool {
    // return false + $action->stopPropagation() to deny
    return true;
});
// or implement CRUDEventListenerInterface
$crud->listen($myListener);
```

Full event list in `src/CRUD/Events/`:
`BeforeCreate`, `BeforeCreateFlush`, `AfterEntityCreation`,
`BeforeRead`, `BeforeReadAll`, `AfterEntityRead`,
`BeforeUpdate`, `BeforeUpdateAll`, `BeforeUpdateFlush`, `BeforeUpdateAllFlush`, `BeforeColumnUpdate`, `BeforeEntityUpdate`, `AfterEntityUpdate`,
`BeforeDelete`, `BeforeDeleteAll`, `BeforeDeleteFlush`, `BeforeDeleteAllFlush`, `BeforeEntityDeletion`, `AfterEntityDeletion`,
`BeforePKColumnWrite`, `BeforePrivateColumnWrite`, `BeforeSensitiveColumnWrite`.

Authorization: listener must return `false` **and** call `$action->stopPropagation()` to deny. `deleteAll` is denied by default.

## Filters

Operand form: `[table.]column[#path]`. Path uses JS-like notation — see `src/DBAL/Filters/FilterFieldNotation.php`.

Fluent filter helpers (on generated `*Query` class): `eq`, `neq`, `lt`, `lte`, `gt`, `gte`, `like`, `notLike`, `isNull`, `isNotNull`, `in`, `notIn`, `isTrue`, `isFalse`, `contains`, `containsAtPath`, `hasKey`. No `jsonContains` method.

Filter values auto-coerced to DB form via `TypeUtils::runCastValueForFilter()`. SQL placeholders optionally wrapped via `TypeUtils::runCastExpressionForQuery()`. Custom types override `castValueForFilter()` / `castExpressionForQuery()` — do not call `runCast*` directly.

## Migrations

```php
$runner = new MigrationRunner($db);
$runner->addFromFile('/path/to/migration.php'); // file must return MigrationInterface
$runner->migrate();
```

`MigrationInterface` methods: `getVersion()`, `up()`, `down()`, `beforeRun()`, `afterRun()`.

**Rename tracking** — set `oldName()` on the **new** schema object before the diff runs; remove it after migration is applied:

```php
// Table rename
$db->ns('App\Db')->table('members', fn(TableBuilder $t) => $t->oldName('users'));

// Column rename (within same table prefix)
$t->useColumn('email_address')->oldName('email');

// Column with prefix change
$t->useColumn('email')->oldName('email')->oldPrefix('');
```

Diff key defaults: `md5(namespace/name)` for tables, `md5(table_key/prefix_name)` for columns.

## Developer Workflows

| Task             | Command            |
| ---------------- | ------------------ |
| Full test suite  | `make test`        |
| Unit tests only  | `make test-unit`   |
| Docker tests     | `make test-docker` |
| Code style check | `make cs`          |
| Auto-fix style   | `make fix`         |
| Docs dev server  | `make docs-dev`    |

## Documentation Guidelines

Docs live in `docs/` (VitePress). JSON schema for IDE at `docs/public/schema.json`.

1. **No hallucination.** Verify every method name, option key, and class name in source before writing.
2. **Canonical examples.** Array schema: `tests/assets/schemas.php` (`clients`, `accounts`, `transactions`, `currencies`). Fluent schema: `tests/BaseTestCase.php::getSampleDB()`.
3. **CRUD event pattern.** Show `*Crud::new()` + `onBefore*()` — not `EventManager::listen()`.
4. **schema.json accuracy.** Keep in sync with `IndexType` enum values, constraint types, and relation link types in source.
5. **Comparison callouts** with Eloquent/Doctrine/Prisma must be accurate and concise; never claim feature parity unless verified.

## Code Style

- `declare(strict_types=1);` in every PHP file
- PSR-4: `Gobl\` maps to `src/`
- `@template` generics on `ORMController<TEntity, TQuery, TResults>` and `ORMEntityCRUD<TEntity>` — preserve when extending
- No Unicode shortcut characters in comments/docblocks — use ASCII equivalents (`->` not `→`, `>=` not `≥`, `...` not `…`, `IN` not `∈`, etc.)
- Comments should be concise; avoid over-documenting obvious code

## Key Files

| File                                          | Purpose                                                                    |
| --------------------------------------------- | -------------------------------------------------------------------------- |
| `src/Gobl.php`                                | Static entry point, forbidden name lists                                   |
| `src/bootstrap.php`                           | Constants, default type provider                                           |
| `src/DBAL/Db.php`                             | RDBMS base, `ns()`, `loadSchema()`, column ref resolution                  |
| `src/DBAL/Builders/TableBuilder.php`          | Fluent schema builder                                                      |
| `src/DBAL/Table.php`                          | Table model, soft-delete, morph                                            |
| `src/DBAL/Column.php`                         | Column model, private/sensitive flags, type binding                        |
| `src/DBAL/Types/Type.php`                     | Abstract base for all column types                                         |
| `src/DBAL/Types/Interfaces/TypeInterface.php` | Type contract                                                              |
| `src/DBAL/Types/Utils/TypeUtils.php`          | Type provider registry, filter cast helpers                                |
| `src/ORM/ORM.php`                             | Namespace registry, entity/results/query factories                         |
| `src/ORM/ORMEntity.php`                       | Entity base: magic access, dirty tracking, partial state                   |
| `src/ORM/ORMResults.php`                      | Result iterator: `fetchClass()`, `groupBy()`, `groupByKey()`, `getTotal()` |
| `src/ORM/ORMController.php`                   | CRUD ops, relative loading and counting                                    |
| `src/ORM/ORMTableQuery.php`                   | Query/filter builder, `find()`, `select()`                                 |
| `src/ORM/ORMEntityRelationController.php`     | Relation controller: `get()`, `list()`, `getBatch()`                       |
| `src/ORM/ORMEntityCRUD.php`                   | Consumer event subscription base                                           |
| `src/ORM/Generators/CSGeneratorORM.php`       | PHP ORM class generator                                                    |
| `src/CRUD/CRUD.php`                           | Per-operation event dispatcher                                             |
| `src/CRUD/CRUDEventProducer.php`              | `listen()`, `onBefore*`, `onAfter*` subscriptions                          |
| `src/DBAL/Relations/Relation.php`             | Relation model, projection (`select`), `resolveSelectColumns()`            |
| `src/DBAL/Filters/FilterFieldNotation.php`    | Path parser: bracket/dot notation                                          |
| `src/DBAL/MigrationRunner.php`                | Version-based migration runner                                             |
| `tests/assets/schemas.php`                    | Canonical array schema reference                                           |
| `tests/BaseTestCase.php`                      | Test scaffolding, fluent builder examples                                  |

## Test Coverage Notes

- `Table` columns are stored keyed by **short name** (`'password'`), not full name (`'u_password'`). `getPrivateColumns()` / `getSensitiveColumns()` return short-name keyed arrays.
- `Table::addForeignKeyConstraint(?string $name, Table $ref, array $cols_map, ...)` — first arg is constraint name (nullable).
- `ForeignKey::assertIsValid()` (called during `lock()`) requires referenced columns to be PK or UK — ensure `addPrimaryKeyConstraint()` is called on the reference table before locking.
- `ForeignKeyAction` enum values are **lowercase** (`ForeignKeyAction::CASCADE->value === 'cascade'`).
- `Index::addColumn()` for a nonexistent column throws `DBALRuntimeException`, not `DBALException`.
- Constraint and `Index` mutation methods throw `PHPUtils\Exceptions\RuntimeException` when already locked.
- `Column::lock()` throws `LogicException` — use `Column::lockWithTable(Table $table)` instead.

## Integration Test Pattern

Tests requiring a real DB connection live under `tests/Integration/` and follow `ORMLiveTestCase`:

- Abstract base class holds all test methods and static `$db` / `$setupFailed` properties.
- `setUpBeforeClass()`: call `static::getNewDbInstance(static::getDriverName())`, catch `Throwable`, set `$setupFailed = true`.
- `setUp()`: call `self::markTestSkipped(...)` when `$setupFailed || null === static::$db`.
- One concrete final subclass per driver, implementing only `getDriverName(): string`.
- PHPUnit `Unit` testsuite excludes `tests/Integration/`; `Integration` testsuite covers it exclusively.
- Reference: `tests/Integration/ORM/ORMLiveTestCase.php`.
- Do NOT use `@dataProvider` across drivers. Use one class per driver.
