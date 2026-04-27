# Code Generation

Gobl can generate PHP ORM classes and client-side code (TypeScript, Dart)
directly from your loaded schema.

## Generate PHP ORM classes

Use `CSGeneratorORM::generate()` after loading your schema:

```php
use Gobl\DBAL\Db;
use Gobl\DBAL\DbConfig;
use Gobl\ORM\Generators\CSGeneratorORM;

// 1. Create the db instance and load the schema
$db = Db::newInstanceOf('mysql', $config);

$db->ns('My\\App\\Db')
   ->schema(require 'config/tables.php')
   ->enableORM(__DIR__ . '/src/Db');   // registers namespace + output directory

// 2. Run the generator
$generator = new CSGeneratorORM($db);
$generator->generate(
    $db->getTables(),          // array of Table objects to generate
    __DIR__ . '/src/Db'        // output directory (same as enableORM path)
);
```

`enableORM($outDir)` on the `NamespaceBuilder` calls `ORM::declareNamespace()`
internally. You only need to call `ORM::declareNamespace()` directly if you are
not using the fluent builder:

```php
// Equivalent long form (only needed when bypassing NamespaceBuilder):
use Gobl\ORM\ORM;

ORM::declareNamespace('My\\App\\Db', $db, __DIR__ . '/src/Db');
```

### Generated file structure

For a table called `users` Gobl creates:

```
src/Db/
├── Base/
│   ├── UsersControllerBase.php   <- Auto-generated.  Do NOT edit.
│   ├── UserBase.php              <- Auto-generated.  Do NOT edit.
│   ├── UsersResultsBase.php      <- Auto-generated.  Do NOT edit.
│   ├── UsersQueryBase.php        <- Auto-generated.  Do NOT edit.
│   └── UsersCrudBase.php         <- Auto-generated.  Do NOT edit.
├── UsersController.php           <- Your class. Edit freely.
├── User.php                      <- Your class. Edit freely.
├── UsersResults.php              <- Your class. Edit freely.
├── UsersQuery.php                <- Your class. Edit freely.
└── UsersCrud.php                 <- Your class. Edit freely.
```

`Base/` files are **overwritten** on every generation run.
The top-level files are created **once** and never overwritten - put
all your custom logic there.

### Generated relation getter signatures

For each declared relation, `EntityBase` gets a typed getter method:

- **Multiple** (one-to-many, many-to-many) — returns the typed `*Results` class:

    ```php
    // Relation 'accounts' on 'clients' table (one-to-many):
    public function getAccounts(?\Gobl\ORM\Interfaces\ORMOptionsInterface $request = null): AccountsResults
    ```

    Pass an `ORMOptions` to paginate, sort, or apply cursor-based pagination:

    ```php
    use Gobl\ORM\ORMOptions;

    $client  = Client::ctrl()->getItem(ORMOptions::makeFromFilters(['client_id' => 1]));
    $results = $client->getAccounts(ORMOptions::makePaginated(max: 20));

    foreach ($results as $account) { /* ... */ }
    ```

- **Single** (many-to-one, one-to-one) — returns the entity or `null`:

    ```php
    // Relation 'client' on 'accounts' table (many-to-one):
    public function getClient(): ?Client
    ```

### Re-running generation

Re-run `$generator->generate(...)` whenever your schema changes.
Your custom files are not touched.

---

## Generate TypeScript types

```php
use Gobl\ORM\Generators\CSGeneratorTS;

$generator = new CSGeneratorTS($db);
$generator->generate($db->getTables(), __DIR__ . '/assets/ts');
```

Output (`assets/ts/`):

| File              | Contents                             |
| ----------------- | ------------------------------------ |
| `MyEntityBase.ts` | Base interface with all column types |
| `MyEntity.ts`     | Extending interface - edit freely    |
| `TSBundle.ts`     | Re-exports all entities              |
| `TSEnums.ts`      | Enum type definitions                |

---

## Generate Dart classes

```php
use Gobl\ORM\Generators\CSGeneratorDart;

$generator = new CSGeneratorDart($db);
$generator->generate($db->getTables(), __DIR__ . '/assets/dart');
```

Output (`assets/dart/`):

| File                   | Contents                  |
| ---------------------- | ------------------------- |
| `my_entity_base.dart`  | Auto-generated base class |
| `my_entity_mixin.dart` | Auto-generated mixin      |
| `my_entity.dart`       | Editable class            |
| `bundle.dart`          | Barrel export             |
| `register.dart`        | Factory registrations     |

---

## Auto-generated file header

Every auto-generated file (the `Base/` files) carries:

```
Auto generated file

WARNING: please don't edit.

Proudly With: gobl/v2.0.0
Time: 2024-01-15T10:30:00+00:00
```

Editable files use:

```
Auto generated file,

INFO: you are free to edit it,
but make sure to know what you are doing.
```
