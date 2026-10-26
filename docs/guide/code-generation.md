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

### Loading order

The controller, CRUD, query and results classes give their table's namespace
and name to their parent written out, as the entity's `TABLE_NAMESPACE` and
`TABLE_NAME` constants hold them: constructing one never loads the entity. So
any generated class may be the first one a request loads, even when a framework
declares the ORM namespace lazily, from its autoloader, and constructs a CRUD
class then (registering listeners): the entity's `qb()`, `ctrl()` and
`results()` return the query, controller and results classes, and PHP cannot
check them against the parent's while one of them is still loading.

A framework that attaches listeners while declaring the namespace does so
through a `CRUDEventProducer` keyed by the table, not the generated CRUD class:
both listen on the same channel (the table's full name), and the producer loads
no generated class, while the CRUD class cannot be constructed when it is the
one loading:

```php
use Gobl\CRUD\CRUDEventProducer;

(new CRUDEventProducer('App\Db', 'users'))
    ->onBeforeCreate(fn (BeforeCreate $action) => $this->canCreate($action));
```

### Generated column properties

Per column, `EntityBase` declares a typed virtual property named after the column's short name,
with `get` and `set` hooks over the entity's row, plus the getter and the fluent setter:

```php
public \PHPUtils\Store\Map $data {
    get => $this->_oebRead(self::COL_DATA);
    set(\PHPUtils\Store\Map|array|\Gobl\DBAL\Types\Utils\JsonPatch $value) {
        $this->_oebWrite(self::COL_DATA, $value);
    }
}
```

The property type is the column's **read** type and the hook parameter its **write** type, which is
the wider of the two where they differ: PHP requires a `set` hook parameter to be a supertype of
the property type, which is the direction a column needs. An element type a PHP type cannot carry
(`Map<\App\Thing>`) goes in the property's `@var`.

A column no longer gets an `@property` line on the class docblock: the type is in the code. See
[Columns are typed properties](./orm.md#columns-are-typed-properties) for what changes at the call
site.

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

| File                           | Contents                                                      |
| ------------------------------ | ------------------------------------------------------------- |
| `gobl/db/base/MyEntityBase.ts` | Base class with all column types, and the `MyEntityRow` type  |
| `gobl/db/MyEntity.ts`          | Extending class - edit freely                                 |
| `gobl/index.ts`                | Registers and exports every entity, its `Row` type, the enums |
| `gobl/enums.ts`                | Enum type definitions                                         |

A REST answer is made of rows (prefixed column names), not entities: import the row types from
the bundle (`import type { MyEntityRow } from './gobl'`).

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

`Gobl::enableGeneratedAtDate(false)` leaves the `Time:` line out (of the SQL scripts too), so that a
file generated again from an unchanged schema is identical and a regeneration rewrites only what
changed. The version line stays.

Editable files use:

```
Auto generated file,

INFO: you are free to edit it,
but make sure to know what you are doing.
```
