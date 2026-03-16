# ORM

Gobl's ORM layer sits on top of the DBAL and turns database rows into
type-safe PHP objects.

## How it works

1. You define a **schema** (PHP array or fluent builder).
2. Gobl **generates** several PHP classes per table. For a table with `singular_name: 'user'`
   and `plural_name: 'users'`:
    - `Base/UserBase.php` - auto-generated entity base, never edit by hand
    - `Base/UsersControllerBase.php` - auto-generated controller base, never edit by hand
    - `Base/UsersCrudBase.php` - auto-generated CRUD event base, never edit by hand
    - `Base/UsersQueryBase.php` - auto-generated query base, never edit by hand
    - `Base/UsersResultsBase.php` - auto-generated results base, never edit by hand
    - `User.php` / `UsersController.php` / `UsersCrud.php` - your classes, extend the Base variants
3. Your application code works with `User` and `UsersController` instances.

---

## Declaring a namespace

The preferred way is through the `NamespaceBuilder` fluent API:

```php
$db->ns('App\\Users')
   ->schema($tables)
   ->enableORM(__DIR__ . '/src/Users/generated');
```

`enableORM()` calls `ORM::declareNamespace()` internally. The direct form is:

```php
use Gobl\ORM\ORM;

// Signature: (namespace, db, out_dir)
ORM::declareNamespace(
    'App\\Users',                      // PHP namespace for generated classes
    $db,                               // Db instance
    __DIR__ . '/src/Users/generated'   // output directory
);
```

---

## Entities

Every generated entity extends `ORMEntity` and exposes typed getters and setters derived
from **column short names** (without the prefix). For a `clients` table with `column_prefix:
'client'` and columns `id`, `first_name`, `last_name`:

```php
$client = new Client();

// Setters (fluent) - method names are derived from the column short name
$client->setFirstName('Jane')
       ->setLastName('Doe');

// Getters
echo $client->getFirstName();  // 'Jane'
echo $client->getID();         // null until persisted

// Magic property access also works (short name or full name)
$client->first_name = 'Jane'; // short name
$client->client_first_name;   // full name (same column)

// Hydration from a raw DB row
$client->hydrate($row);

// Convert to array / JSON (sensitive columns are redacted)
$client->toArray();
json_encode($client);

// Persist to the database
$client->save();
```

### Save state

The entity tracks unsaved changes via `isSaved()` and `isNew()`:

```php
$client = Client::ctrl()->getItem([...]);
$client->isNew();             // false - loaded from DB
$client->isSaved();           // true  - no pending changes

$client->setFirstName('Bob');
$client->isSaved();           // false - has unsaved changes

$client->save();
$client->isSaved();           // true  - changes persisted

// A brand-new entity (not yet persisted)
$new = Client::new();
$new->isNew();                // true
$new->isSaved();              // false

$new->save();                 // INSERT
$new->isNew();                // false
$new->isSaved();              // true

// Self-delete
$client->selfDelete();        // calls deleteOneItem internally
```

Dirty detection compares a frozen **hash snapshot** (taken at load / last save) with
the hash of the newly assigned value. This correctly detects changes even for
mutable values such as `Map` — mutating a `Map` in place and re-assigning the same
instance is treated as a change when the content differs:

```php
/** @var Map $data */
$data = $client->client_data;  // e.g. ['role' => 'admin']
$data->set('role', 'editor');  // mutate in place
$client->client_data = $data;  // re-assign: detected as dirty
$client->isSaved();            // false
```

---

## Results collection

`getAllItems()` returns an `ORMResults` iterator:

```php
$results = $userController->getAllItems($filters, max: 20, offset: 0);

foreach ($results as $user) {
    // $user is a fully-typed entity instance
    echo $user->getName();
}

echo $results->count();       // rows in this page
echo $results->totalCount();  // total matched rows (ignores LIMIT)
```

---

## Writing raw ORM queries

`ORMTableQuery` (and its generated subclasses) wraps `QBSelect` with the
table and alias pre-configured. Obtain one via `ORM::query()`:

```php
use Gobl\ORM\ORM;

$table = $db->getTableOrFail('users');
$tq    = ORM::query($table); // returns the generated UsersQuery instance

$tq->where(
    $tq->filters()
       ->eq('user_status', 'active')
       ->gte('user_created_at', '2024-01-01')
)->orderBy(['user_name ASC'])->limit(10);

$results = ORM::results($table, $tq->getQBSelect());
foreach ($results as $user) { /* ... */ }
```

---

## Type hints

`ORMTypeHint` and `ORMUniversalType` let the ORM coerce returned column
values to the correct PHP type even when the column isn't directly
declared in the schema:

```php
use Gobl\ORM\ORMTypeHint;

$hint = ORMTypeHint::int();        // cast to int
$hint = ORMTypeHint::float();      // cast to float
$hint = ORMTypeHint::list();       // value is an array
$hint = ORMTypeHint::bool();       // cast to bool
```

Useful when writing raw `SELECT` queries with computed columns.
