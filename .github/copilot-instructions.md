# Gobl - AI Coding Instructions

IMPORTANT: no hallucination or invention. Go through the entire code base to understand before generating code, the `.github/copilot-instructions.md` or docs. Focus on what can be directly observed in the codebase, not on idealized practices or assumptions.
when bug or issue is found in the codebase, do not fix it directly, but rather ask for feedback and approval.

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
- `src/Gobl.php` - static entry point: project cache dir, OTpl template management, forbidden name lists
- `src/bootstrap.php` - defines `GOBL_ROOT`, `GOBL_VERSION`, `GOBL_ASSETS_DIR` and registers the default type provider

## Schema Definition - Two Approaches

**Array-based** (see [tests/tables.php](../tests/tables.php)):

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

`TableBuilder` fluent column methods: `id()`, `int()`, `bigint()`, `string()`, `bool()`, `float()`, `decimal()`, `date()`, `enum()`, `json()`, `list()`, `map()`, `timestamp()`, `timestamps()`, `softDeletable()`, `morph()`, `foreign()`, `sameAs()`, `unique()`, `primary()`, `index()`.
Relation methods: `hasMany()`, `hasOne()`, `belongsTo()`, `belongsToMany()`.

Rules:

- Table and column names: `[a-z][a-z0-9_]*` only
- `column_prefix` is prepended to every column's DB name (prefix `user` + name `id` = full name `user_id`)
- `ref:table.column` inherits type (shared instance); `cp:table.column` copies/clones the type independently
- Column/relation names forbidden: `save`, `saved`, `is_saved`, `new`, `is_new`, `table`, `crud`, `qb`, `ctrl`, `results`
- Supported types (SQL standard only, no vendor-specific): `bigint`, `int`, `bool`, `string`, `decimal`, `float`, `date`, `enum`, `json`, `list`, `map`

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

See [tests/tmp/output/ORM_Db/](../tests/tmp/output/ORM_Db/) for the expected output shape.

TypeScript/Dart generators: `CSGeneratorTS`, `CSGeneratorDart` - produce typed frontend entity classes.

## ORM Entity Usage

`ORMEntity` uses magic `__get`/`__set` keyed by full column name (prefix + column name):

```php
$account->account_id;           // read
$account->account_label = 'x';  // write
$account->save();               // persist
```

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

## Custom Type Providers

Built-in types come from `TypeProviderDefault` (registered automatically in `bootstrap.php`). Extend with custom types:

```php
// implements TypeProviderInterface: getTypeInstance($name, $options), hasType($name)
TypeUtils::addTypeProvider(new MyCustomTypeProvider());
```

## Custom Templates

`Gobl::addTemplate()` / `Gobl::addTemplates()` register OTpl templates used by code generators. Templates are compiled and cached under `.gobl/cache/`. `GOBL_TEMPLATES_DIR` no longer exists as a constant.

```php
Gobl::setProjectCacheDir('/your/app/root');  // must be a writable directory
Gobl::addTemplate('my-tpl', '/absolute/path/to/source.php', ['MY_TOKEN' => '<%$value%>']);
```

## Migrations

`Diff` engine compares two schema states and generates migration SQL. `MigrationRunner` applies them:

```php
$runner = new MigrationRunner($db);
$runner->addFromFile('/path/to/001_migration.php'); // file must return MigrationInterface
$runner->run(MigrationMode::UP);
```

Migration files are anonymous classes implementing `MigrationInterface` with `getVersion()`, `up()`, `down()`, `beforeRun()`, `afterRun()`. See `tests/tmp/output/migration_mysql_*.php` for generated examples.

## Developer Workflows

| Task                             | Command                     |
| -------------------------------- | --------------------------- |
| Run full test suite              | `./run_test` or `make test` |
| Run unit tests only (no live DB) | `make test-unit`            |
| Check code style                 | `make cs`                   |
| Auto-fix code style              | `make cs-fix`               |
| Docs dev server                  | `make docs-dev`             |
| Generate PHP API docs            | `make docs-api`             |
| Build docs site                  | `make docs-build`           |

Live DB tests require `.env.test` (see `.env.test.example`). `./run_test` cleans `tests/tmp/` before each run. SQLite tests use `:memory:` by default.

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

| File                                                                              | Purpose                                                                                 |
| --------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------- |
| [src/Gobl.php](../src/Gobl.php)                                                   | Static entry point, cache/template management, forbidden name lists                     |
| [src/bootstrap.php](../src/bootstrap.php)                                         | Defines `GOBL_ROOT`, `GOBL_VERSION`, `GOBL_ASSETS_DIR`; registers default type provider |
| [src/DBAL/Db.php](../src/DBAL/Db.php)                                             | Abstract RDBMS base, `ns()`, `loadSchema()`, `newInstanceOf()`, column ref resolution   |
| [src/DBAL/Builders/TableBuilder.php](../src/DBAL/Builders/TableBuilder.php)       | Fluent schema builder API                                                               |
| [src/DBAL/Table.php](../src/DBAL/Table.php)                                       | Schema table model, soft-delete constants, morph support                                |
| [src/DBAL/Column.php](../src/DBAL/Column.php)                                     | Column model, private/sensitive flags, type binding                                     |
| [src/DBAL/Types/Type.php](../src/DBAL/Types/Type.php)                             | Abstract base for all column types                                                      |
| [src/DBAL/Types/Utils/TypeUtils.php](../src/DBAL/Types/Utils/TypeUtils.php)       | `addTypeProvider()`, type resolution                                                    |
| [src/ORM/ORM.php](../src/ORM/ORM.php)                                             | Namespace registry, `declareNamespace()`                                                |
| [src/ORM/ORMEntity.php](../src/ORM/ORMEntity.php)                                 | Entity base, magic column access, `save()`, dirty tracking                              |
| [src/ORM/ORMController.php](../src/ORM/ORMController.php)                         | `addItem()`, `updateOneItem()`, `deleteOneItem()`, `getItem()`, `getAllItems()`         |
| [src/ORM/ORMEntityCRUD.php](../src/ORM/ORMEntityCRUD.php)                         | Consumer event subscription base (extends `CRUDEventProducer`)                          |
| [src/ORM/Generators/CSGeneratorORM.php](../src/ORM/Generators/CSGeneratorORM.php) | PHP ORM class generator                                                                 |
| [src/CRUD/CRUD.php](../src/CRUD/CRUD.php)                                         | Per-operation event dispatcher (used internally by `ORMController`)                     |
| [src/CRUD/CRUDEventProducer.php](../src/CRUD/CRUDEventProducer.php)               | `listen()`, `onBefore*`, `onAfter*` subscription methods                                |
| [src/DBAL/MigrationRunner.php](../src/DBAL/MigrationRunner.php)                   | Version-based migration runner                                                          |
| [tests/tables.php](../tests/tables.php)                                           | Reference array schema used throughout tests                                            |
| [tests/BaseTestCase.php](../tests/BaseTestCase.php)                               | Test scaffolding: DB bootstrap, fluent builder usage, multi-driver helpers              |
| [tests/tmp/output/ORM_Db/](../tests/tmp/output/ORM_Db/)                           | Generated ORM PHP classes (reference for expected output shape)                         |
