# Gobl - AI Coding Instructions

IMPORTANT: no hallucination or invention. Go through the entire code base to understand before generating code, the `.github/copilot-instructions.md` or docs. Focus on what can be directly observed in the codebase, not on idealized practices or assumptions.
When bug or issue is found in the codebase, do not fix it directly, but rather ask for feedback and approval.
`AGENTS.md`, `CLAUDE.md`, and `GEMINI.md` are symlinks to `.github/copilot-instructions.md` -- keep them that way.

## Project Overview

Gobl is a PHP 8.1+ library providing a **DBAL** (Database Abstraction Layer), an **ORM**, and a **CRUD event system**. It supports MySQL, PostgreSQL, and SQLite via PDO. Code generation produces typed entity/controller/query classes for PHP, TypeScript, and Dart. It is designed to be embedded in larger frameworks. The TypeScript/Dart generated classes auto-convert API JSON responses to typed entity instances on frontends.

## Architecture

Three distinct layers, each building on the previous:

```
DBAL (Db, Table, Column, Types, Queries, Relations, Migrations, Diff)
  +--> ORM (ORMEntity, ORMController, ORMTableQuery, ORMResults, ORMEntityCRUD, Generators)
         +--> CRUD (CRUDEventProducer: BeforeCreate, AfterEntityCreation, BeforeColumnUpdate, ...)
```

- `src/DBAL/` - schema model, query builders (`QBSelect`, `QBInsert`, `QBUpdate`, `QBDelete`), drivers, diff/migration engine
- `src/ORM/` - entity base classes, controller, code generators (`CSGeneratorORM`, `CSGeneratorTS`, `CSGeneratorDart`)
- `src/CRUD/` - before/after event system via `CRUD` and `CRUDEventProducer`
- `src/Gobl.php` - static entry point: project cache dir, templates management, forbidden name lists
- `src/bootstrap.php` - defines `GOBL_ROOT`, `GOBL_VERSION`, `GOBL_ASSETS_DIR` and registers the default type provider

## Schema Definition - Two Approaches

**Array-based** (see [tests/assets/schemas.php](../tests/assets/schemas.php)):

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

**Fluent builder** (see [tests/BaseTestCase.php](../tests/BaseTestCase.php)):

```php
$db->ns('App\Db')->table('users', function (TableBuilder $t) {
    $t->id();                        // bigint auto-increment PK
    $t->string('name');
    $t->timestamps();                // created_at + updated_at
    $t->softDeletable();             // adds deleted + deleted_at columns
    $t->foreign('role_id', 'roles', 'id');
    $t->belongsTo('role')->from('roles');
});
```

`TableBuilder` fluent column methods: `id()`, `int()`, `bigint()`, `string()`, `bool()`, `float()`, `decimal()`, `date()`, `enum()`, `json()`, `jsonOf()`, `list()`, `listOf()`, `map()`, `mapOf()`, `timestamp()`, `timestamps()`, `softDeletable()`, `morph()`, `foreign()`, `sameAs()`, `unique()`, `primary()`, `index()`.
Accessor method: `useColumn($name)` — returns an already-defined `Column` by name (throws `DBALRuntimeException` if not found); shorthand for `getTable()->getColumnOrFail($name)`.
Relation methods: `hasMany()`, `hasOne()`, `belongsTo()`, `belongsToMany()`.

Rules:

- Table and column names: `[a-z][a-z0-9_]*` only
- `column_prefix` is prepended to every column's DB name (prefix `user` + name `id` = full name `user_id`)
- `ref:table.column` inherits type (shared instance); `cp:table.column` copies/clones the type independently
- Column/relation names forbidden: `save`, `saved`, `is_saved`, `new`, `is_new`, `table`, `crud`, `qb`, `ctrl`, `results`
- Supported types (SQL standard only, no vendor-specific): `bigint`, `int`, `bool`, `string`, `decimal`, `float`, `date`, `enum`, `json`, `list`, `map`
- `json`, `list`, and `map` columns use native JSON storage by default (`native_json=true`): MySQL JSON / PostgreSQL JSONB. Use `->nativeJson(false)` or `'native_json' => false` in the array schema to opt out and store as TEXT.
- `json` supports `json_of` option / `->jsonOf($class)` for revival of a single value; `list` supports `list_of` / `->listOf($class)` for per-element revival; `map` supports `map_of` / `->mapOf($class)` for per-value revival. All three accept either a FQCN implementing `JsonOfInterface` (runtime revival) or an `ORMUniversalType` enum name (hint-only, no revival).

## ORM Namespace Registration and Generation

```php
// via fluent builder (preferred - schema + ORM in one chain)
$db->ns('App\Db')->schema($tables)->enableORM('/path/to/output');

// or manually
ORM::declareNamespace('App\Db', $db, '/path/to/output');
$generator = new CSGeneratorORM($db);
$generator->generate($db->getTables('App\Db'), '/path/to/output');
```

Per table, 5 class pairs are generated (`Base/` = auto-generated, parent dir = user-editable once):

| Generated file              | Extends                 | Purpose                                                                              |
| --------------------------- | ----------------------- | ------------------------------------------------------------------------------------ |
| `Base/Entity.php`           | `ORMEntity`             | column constants (`COL_*`), `TABLE_NAME`, `TABLE_NAMESPACE`, typed property docblock |
| `Entity.php`                | `Base\Entity`           | user customization placeholder                                                       |
| `Base/EntityController.php` | `ORMController`         | wires namespace + table name                                                         |
| `EntityController.php`      | `Base\EntityController` | user customization placeholder                                                       |
| `Base/EntityCrud.php`       | `ORMEntityCRUD`         | wires namespace + table for CRUD event subscription                                  |
| `EntityCrud.php`            | `Base\EntityCrud`       | user customization placeholder                                                       |
| `Base/EntityQuery.php`      | `ORMTableQuery`         | filter/query builder                                                                 |
| `Base/EntityResults.php`    | `ORMResults`            | result iteration                                                                     |

**Never edit `Base/` files** - they are overwritten on every generation run.

TypeScript/Dart generators: `CSGeneratorTS`, `CSGeneratorDart` - produce typed frontend entity classes.

## ORM Entity Usage

`ORMEntity` uses magic `__get`/`__set` keyed by full column name (prefix + column name):

```php
$account->account_id;           // read
$account->account_label = 'x';  // write
$account->save();               // persist
```

**Dirty tracking**: `__set` computes `Type::hash()` of the new (validated) value and
compares it against a frozen snapshot taken at the last save or PDO load
(`_oeb_saved_hashes`). A column is marked dirty only when the hashes differ.
This handles mutable values (e.g. `Map`) correctly: mutating a `Map` in place and
re-assigning it is detected because the hash reflects current content, not object identity.

**`isSaved(bool $set_as_saved = false): bool`** — returns `true` when no columns are
dirty and the entity is not new. Calling `isSaved(true)` snapshots all current hashes,
clears the dirty set, and unsets the `isNew` flag.

**`isNew(): bool`** — `true` for entities created with `new()` that have not yet been
persisted; `false` for entities loaded from the DB or after the first successful `save()`.

Name collision rules for subclasses:

- Internal `ORMEntity` properties use `_oeb_*` prefix
- Subclass properties must use a single `_` prefix (e.g., `_myProp`)
- Custom methods must NOT start with `get` or `set` -- use `_get...` / `_set...` or a verb

## CRUD Event System

`CRUD` (used internally by `ORMController`) dispatches events. The generated `*Crud` class (extends `ORMEntityCRUD extends CRUDEventProducer`) is the consumer-facing subscription API:

```php
$crud = AccountsCrud::new();

// single listener
$crud->onBeforeCreate(function (BeforeCreate $action): bool {
    // return false + stopPropagation() to deny
    return true;
});

// or implement CRUDEventListenerInterface, methods named onBeforeCreate, onAfterCreate, etc.
$crud->listen($myListener);
```

Full event list (`Gobl\CRUD\Events\`):
`BeforeCreate`, `BeforeCreateFlush`, `AfterEntityCreation`,
`BeforeRead`, `BeforeReadAll`, `AfterEntityRead`,
`BeforeUpdate`, `BeforeUpdateAll`, `BeforeUpdateFlush`, `BeforeUpdateAllFlush`, `BeforeColumnUpdate`, `BeforeEntityUpdate`, `AfterEntityUpdate`,
`BeforeDelete`, `BeforeDeleteAll`, `BeforeDeleteFlush`, `BeforeDeleteAllFlush`, `BeforeEntityDeletion`, `AfterEntityDeletion`,
`BeforePKColumnWrite`, `BeforePrivateColumnWrite`, `BeforeSensitiveColumnWrite`.

Authorization events use **stop-on-first-denial**: listener must return `false` AND call `$action->stopPropagation()`. `deleteAll` is denied by default unless a listener explicitly allows it.

## Filter Field Notation

Filter operands use the form `[table.]column[#path]`. The `#path` portion is parsed into segments using JS-like notation (implemented in `FilterFieldNotation`):

- **Plain segment**: `foo` - identifier chars only (no `.`, `[`, `'`, `"`).
- **Bracket-integer segment**: `[0]` - non-negative integer index.
- **Bracket-quoted segment**: `['...']` or `["..."]` - any key; use `\'` / `\"` to escape quotes inside.
- Segments are separated by `.` (optional after `]`).
- Empty segments (consecutive dots) throw an exception.

```
column#foo.bar              -> ['foo', 'bar']
column#foo[0].bar           -> ['foo', '0', 'bar']
column#foo['bar.baz'].qux   -> ['foo', 'bar.baz', 'qux']
column#['it\'s'].key        -> ["it's", 'key']
column#foo["bar"]["baz"]    -> ['foo', 'bar', 'baz']
```

`getPathSegmentsAsString()` serializes segments back: plain dot-notation for simple identifiers, `['...']` for segments containing special characters.

## Filter Type Coercion

When a filter value is bound to a column, the DBAL automatically casts the PHP value to its DB-compatible form via the column type. Hook points on `TypeInterface`:

- `shouldCastValueForFilter(Operator, RDBMSInterface): bool` - return `false` to skip casting for a specific operator+RDBMS combination (default: `true`).
- `castValueForFilter(mixed, Operator, RDBMSInterface): float|int|string|null` - perform the cast (e.g., JSON-encode arrays, serialize enum labels).

Two additional hook points exist on `BaseTypeInterface` (implemented by `BaseType`, which all direct-column built-in types extend):

- `shouldCastExpressionForQuery(RDBMSInterface): bool` - return `true` to wrap the SQL placeholder.
- `castExpressionForQuery(string, RDBMSInterface): string` - wrap the placeholder in a DB-level expression (e.g., `CAST(? AS JSONB)` for PostgreSQL).

`TypeUtils::runCastValueForFilter(Column, mixed, Operator, RDBMSInterface)` and
`TypeUtils::runCastExpressionForQuery(Column, string, RDBMSInterface)` are the static helpers called by the query builders. Custom type implementations should override `castValueForFilter` / `castExpressionForQuery` rather than calling `runCast*` directly.

## Custom Type Providers

Built-in types come from `TypeProviderDefault` (registered automatically in `bootstrap.php`). Extend with custom types:

```php
// implements TypeProviderInterface: getTypeInstance($name, $options), hasType($name)
TypeUtils::addTypeProvider(new MyCustomTypeProvider());
```

## Migrations

`Diff` engine compares two schema states and generates migration SQL. `MigrationRunner` applies them:

```php
$runner = new MigrationRunner($db);
$runner->addFromFile('/path/to/001_migration.php'); // file must return MigrationInterface
$runner->run(MigrationMode::UP);
```

Migration files are anonymous classes implementing `MigrationInterface` with `getVersion()`, `up()`, `down()`, `beforeRun()`, `afterRun()`. See `tests/tmp/output/migration_mysql_*.php` for generated examples.

### Rename tracking (`oldName` / `oldPrefix`)

By default the diff key for a table is `md5(namespace/name)` and for a column is `md5(table_key/prefix_name)`. Renaming without extra hints causes the engine to emit a DROP + CREATE instead of an ALTER RENAME.

To teach the diff engine about a rename, set `oldName()` on the **new** schema object before the diff runs, then remove it after the migration has been applied. The option is **not** written by `toArray()`.

```php
// Table rename: 'users' -> 'members'
$db->ns('App\Db')->table('members', function (TableBuilder $t) {
    $t->oldName('users');   // derive diff key from old name for this migration
    // ... column definitions ...
});

// Column rename: 'user_email' -> 'user_email_address'
$t->string('email_address');
$t->useColumn('email_address')->oldName('email'); // current prefix ('user') is reused

// Column that also changed prefix: previously 'email' (no prefix), now 'user_email'
$t->string('email');
$col = $t->useColumn('email');
$col->oldName('email');
$col->oldPrefix('');         // old prefix was empty
```

In the array schema:

```php
'members' => [
    'old_name'   => 'users',      // remove after migration
    'columns'    => [
        'email_address' => ['type' => 'string', 'old_name' => 'email'],
    ],
]
```

**Edge case**: leaving `old_name` across two separate rename cycles causes the diff key to stay anchored to the first old name. Always remove `old_name` from the schema once the migration has been applied.

## Developer Workflows

| Task                             | Command                 |
| -------------------------------- | ----------------------- |
| Run full test suite              | `make test`             |
| Run unit tests only (no live DB) | `make test-unit`        |
| Run tests via Docker             | `make test-docker`      |
| Tear down Docker test containers | `make test-docker-down` |
| Check code style                 | `make cs`               |
| Auto-fix code style              | `make fix`              |
| Docs dev server                  | `make docs-dev`         |
| Generate PHP API docs            | `make docs-api`         |
| Build docs site                  | `make docs-build`       |

## Documentation Guidelines

The docs live in `docs/` and use [VitePress](https://vitepress.dev/). The JSON schema for IDE
auto-complete/validation is at `docs/public/schema.json`.

### Where things live

| Path                          | Purpose                                                          |
| ----------------------------- | ---------------------------------------------------------------- |
| `docs/.vitepress/config.ts`   | Site config, nav, sidebar (source of truth for page order)       |
| `docs/guide/*.md`             | All narrative / guide pages                                      |
| `docs/index.md`               | Site home page                                                   |
| `docs/public/schema.json`     | JSON Schema for IDE integration and the in-browser schema editor |
| `docs/.vitepress/components/` | Vue components used in guide pages (e.g. `SchemaEditor`)         |
| `docs/.vitepress/theme/`      | Theme overrides                                                  |

### Documentation rules

1. **No hallucination.** Every code example, method name, option key, and class name must be
   verified against the source code before writing. If you are unsure, search the source.

2. **Use real examples from tests.** The schemas in `tests/assets/schemas.php` (`clients`,
   `accounts`, `transactions`, `currencies`) are the canonical reference. The fluent-builder
   examples in `tests/BaseTestCase.php::getSampleDB()` are the canonical fluent examples.

3. **Accurate method names.** Generated entity getter names follow `CSGeneratorORM::propertyGetterName()`:
    - Columns starting with `is_` or ending with `ed` -> `is{Name}()` (e.g., `isDeleted()`)
    - All others -> `get{Name}()` (e.g., `getUserName()`)
    - Setters are always `set{Name}()` (e.g., `setUserName()`)
    - Magic property access also works: `$entity->user_name` (full column name)

4. **Accurate generated file names.** `ORMClassKind::getClassName()` drives file names:
    - ENTITY: `{SingularName}` -> `User.php`
    - BASE_ENTITY: `{SingularName}Base` -> `UserBase.php`
    - CONTROLLER: `{PluralName}Controller` -> `UsersController.php`
    - BASE_CONTROLLER: `{PluralName}ControllerBase` -> `UsersControllerBase.php`
    - QUERY: `{PluralName}Query` -> `UsersQuery.php`
    - BASE_QUERY: `{PluralName}QueryBase` -> `UsersQueryBase.php`
    - RESULTS: `{PluralName}Results` -> `UsersResults.php`
    - BASE_RESULTS: `{PluralName}ResultsBase` -> `UsersResultsBase.php`
    - CRUD: `{PluralName}Crud` -> `UsersCrud.php`
    - BASE_CRUD: `{PluralName}CrudBase` -> `UsersCrudBase.php`
    - All `Base*` files go into `Base/` subdirectory; all others are in the output root.

5. **CRUD event API.** The docs must show the `*Crud` class pattern, not `EventManager::listen()`:

    ```php
    $crud = UsersCrud::new();
    $crud->onBeforeCreate(function (BeforeCreate $action): bool { ... });
    ```

6. **Filter methods.** The fluent filter helpers are: `eq`, `neq`, `lt`, `lte`, `gt`, `gte`,
   `like`, `notLike`, `isNull`, `isNotNull`, `in`, `notIn`, `isTrue`, `isFalse`,
   `contains`, `containsAtPath`, `hasKey`. There is no `jsonContains` method.
   Filter values are automatically coerced to their DB form via `TypeUtils::runCastValueForFilter()`
   and SQL placeholders may be wrapped via `TypeUtils::runCastExpressionForQuery()`.

7. **Relation schema format.** In the PHP array schema, relations are simple:

    ```php
    'relations' => [
        'client' => ['type' => 'many-to-one', 'target' => 'clients'],
        'accounts' => ['type' => 'one-to-many', 'target' => 'accounts'],
    ]
    ```

    Column mapping is usually auto-detected from FK constraints. The `link` key is used only
    when the default detection is insufficient (morph, through, custom join).

8. **schema.json accuracy.** The JSON Schema at `docs/public/schema.json` must stay in sync
   with all column options, constraint types, index types, and relation link types defined in
   the source. `IndexType` enum values (`BTREE`, `HASH`, `MYSQL_FULLTEXT`, `MYSQL_SPATIAL`,
   `PGSQL_GIN`, `PGSQL_GIST`, `PGSQL_BRIN`, `PGSQL_SPGIST`) are the only valid index types.

9. **AI-agent usability.** The docs should help AI agents / code editors discover features:
    - The schema.json `description` fields must be precise and give enough context to generate
      valid schema definitions without reading PHP source.
    - Each guide page should open with a one-paragraph conceptual summary.
    - Code examples should be self-contained and runnable with minimal setup.
    - The `schema-editor.md` page should explain how to use the JSON schema in VS Code / Cursor
      / Zed via `$schema` reference and the `json.schemas` setting.

10. **Good learning curve.** Pages are ordered from easy to hard:
    Introduction -> DBAL -> ORM -> CRUD events -> Drivers. Within pages, show simple cases
    first, advanced options last. Cross-link liberally: column-types <-> schema, crud-events
    <-> controllers, etc.

11. **Comparison with popular ORMs.** Where helpful, add a callout or note box showing how a Gobl concept maps to a familiar ORM (Eloquent, Doctrine, Prisma). Keep these concise and accurate, never claim feature parity unless it exists.

## Code Style Conventions

- All PHP files must have `declare(strict_types=1);`
- PSR-4 autoloading: `Gobl\` -> `src/`
- **No Unicode shortcut characters in comments or docblocks.** Always use plain ASCII equivalents:

| use      | don't use   |
| -------- | ----------- |
| `->`     | `→`         |
| `<-`     | `←`         |
| `<->`    | `↔`         |
| `-->`    | `───▶`      |
| `>=`     | `≥`         |
| `<=`     | `≤`         |
| `!=`     | `≠`         |
| `*`      | `×`         |
| `/`      | `÷`         |
| `-`      | ` —` or `–` |
| `IN`     | `∈`         |
| `NOT IN` | `∉`         |
| `...`    | `…`         |

- Comments should be concise and human; avoid over-documenting obvious code
- `@template` generics on `ORMController<TEntity, TQuery, TResults>` and `ORMEntityCRUD<TEntity>` -- preserve when extending

## Key Files

| File                                                                                                  | Purpose                                                                                        |
| ----------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------- |
| [src/Gobl.php](../src/Gobl.php)                                                                       | Static entry point, cache/template management, forbidden name lists                            |
| [src/bootstrap.php](../src/bootstrap.php)                                                             | Defines `GOBL_ROOT`, `GOBL_VERSION`, `GOBL_ASSETS_DIR`; registers default type provider        |
| [src/DBAL/Db.php](../src/DBAL/Db.php)                                                                 | Abstract RDBMS base, `ns()`, `loadSchema()`, `newInstanceOf()`, column ref resolution          |
| [src/DBAL/Builders/TableBuilder.php](../src/DBAL/Builders/TableBuilder.php)                           | Fluent schema builder API                                                                      |
| [src/DBAL/Table.php](../src/DBAL/Table.php)                                                           | Schema table model, soft-delete constants, morph support                                       |
| [src/DBAL/Column.php](../src/DBAL/Column.php)                                                         | Column model, private/sensitive flags, type binding                                            |
| [src/DBAL/Types/Type.php](../src/DBAL/Types/Type.php)                                                 | Abstract base for all column types                                                             |
| [src/DBAL/Types/BaseType.php](../src/DBAL/Types/BaseType.php)                                         | Abstract base for direct-column types; implements `BaseTypeInterface` (expression-cast hooks)  |
| [src/DBAL/Types/Interfaces/TypeInterface.php](../src/DBAL/Types/Interfaces/TypeInterface.php)         | Type contract: `castValueForFilter()`, `shouldCastValueForFilter()`, validation hooks          |
| [src/DBAL/Types/Interfaces/BaseTypeInterface.php](../src/DBAL/Types/Interfaces/BaseTypeInterface.php) | Extends `TypeInterface`: `shouldCastExpressionForQuery()`, `castExpressionForQuery()`          |
| [src/DBAL/Types/Utils/TypeUtils.php](../src/DBAL/Types/Utils/TypeUtils.php)                           | `addTypeProvider()`, type resolution, `runCastValueForFilter()`, `runCastExpressionForQuery()` |
| [src/ORM/ORM.php](../src/ORM/ORM.php)                                                                 | Namespace registry, `declareNamespace()`                                                       |
| [src/ORM/ORMEntity.php](../src/ORM/ORMEntity.php)                                                     | Entity base, magic column access, `save()`, `isSaved()`, `isNew()`, hash-based dirty tracking  |
| [src/ORM/ORMController.php](../src/ORM/ORMController.php)                                             | `addItem()`, `updateOneItem()`, `deleteOneItem()`, `getItem()`, `getAllItems()`                |
| [src/ORM/ORMEntityCRUD.php](../src/ORM/ORMEntityCRUD.php)                                             | Consumer event subscription base (extends `CRUDEventProducer`)                                 |
| [src/ORM/Generators/CSGeneratorORM.php](../src/ORM/Generators/CSGeneratorORM.php)                     | PHP ORM class generator                                                                        |
| [src/CRUD/CRUD.php](../src/CRUD/CRUD.php)                                                             | Per-operation event dispatcher (used internally by `ORMController`)                            |
| [src/CRUD/CRUDEventProducer.php](../src/CRUD/CRUDEventProducer.php)                                   | `listen()`, `onBefore*`, `onAfter*` subscription methods                                       |
| [src/DBAL/Filters/FilterFieldNotation.php](../src/DBAL/Filters/FilterFieldNotation.php)               | Operand path parser: bracket/dot notation, segment serialization                               |
| [src/DBAL/MigrationRunner.php](../src/DBAL/MigrationRunner.php)                                       | Version-based migration runner                                                                 |
| [tests/assets/schemas.php](../tests/assets/schemas.php)                                               | Reference array schema used throughout tests                                                   |
| [tests/BaseTestCase.php](../tests/BaseTestCase.php)                                                   | Test scaffolding: DB bootstrap, fluent builder usage, multi-driver helpers                     |
| [tests/DBAL/TableTest.php](../tests/DBAL/TableTest.php)                                               | Table schema model tests (columns, constraints, indexes, lock, soft-delete)                    |
| [tests/DBAL/Filters/FilterFieldNotationTest.php](../tests/DBAL/Filters/FilterFieldNotationTest.php)   | FilterFieldNotation: parse, bracket/dot segments, round-trip, resolve                          |
| [tests/DBAL/Constraints/ConstraintsTest.php](../tests/DBAL/Constraints/ConstraintsTest.php)           | PrimaryKey, UniqueKey, ForeignKey constraint tests                                             |
| [tests/DBAL/Indexes/IndexTest.php](../tests/DBAL/Indexes/IndexTest.php)                               | Index construction, addColumn, toArray, lock, assertIsValid                                    |
| [tests/ORM/ORMClassKindTest.php](../tests/ORM/ORMClassKindTest.php)                                   | ORMClassKind enum: getClassName, isBaseClass, getBaseKind, getClassFQN                         |

## Test Coverage Notes

- `Table` columns are stored keyed by **short name** (e.g., `'password'`), not full name (`'u_password'`). `getPrivateColumns()` / `getSensitiveColumns()` return arrays with short-name keys.
- `Table::addForeignKeyConstraint(?string $name, Table $ref, array $cols_map, ...)` - first arg is the constraint name (nullable), not the reference table.
- `ForeignKey::assertIsValid()` (called during `lock()`) requires the referenced columns to be PK or UK in the reference table, ensure the reference table has a `addPrimaryKeyConstraint()` before locking.
- `ForeignKeyAction` enum values are **lowercase** (e.g., `ForeignKeyAction::CASCADE->value === 'cascade'`).
- `Index::addColumn()` for a nonexistent column throws `DBALRuntimeException` (via `Table::getColumnOrFail()`), not `DBALException`.
- Constraint (`PrimaryKey`, `UniqueKey`, `ForeignKey`) and `Index` mutation methods (e.g., `addColumn()`, `onUpdate()`, `onDelete()`) throw `PHPUtils\Exceptions\RuntimeException` when the object is already locked.
- `Column::lock()` throws `LogicException` — use `Column::lockWithTable(Table $table)` instead.

## Live DB Test Pattern

Tests that require a real database connection MUST live under `tests/Integration/` and follow the `ORMLiveTestCase` pattern:

- **Abstract base class** (e.g. `NativeJsonMigrationTestCase`) contains all test methods, shared static `$db` / `$setupFailed` properties, `setUpBeforeClass()`, `tearDownAfterClass()`, and `setUp()`.
- `setUpBeforeClass()`: call `static::getNewDbInstance(static::getDriverName())`, catch `Throwable`, set `$setupFailed = true` on failure.
- `setUp()`: call `self::markTestSkipped(...)` when `$setupFailed || null === static::$db`.
- **Concrete final subclasses** (one per driver) only implement `getDriverName(): string`, e.g. `NativeJsonMigrationMySQLTest`, `NativeJsonMigrationPostgreSQLTest`.
- Place all integration test files under `tests/Integration/`. PHPUnit's `Unit` testsuite excludes this directory; the `Integration` testsuite covers it exclusively.
- Reference implementation: [tests/Integration/ORM/ORMLiveTestCase.php](../tests/Integration/ORM/ORMLiveTestCase.php) and [tests/Integration/ORM/ORMMySQLLiveTest.php](../tests/Integration/ORM/ORMMySQLLiveTest.php).
- **Do NOT** use `@dataProvider` with `driversForNativeJson()` or hand-roll per-test teardown loops over all drivers. Use one class per driver.
