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

## Cursor-based pagination

`ORMTableQuery::cursorFind()` provides stable cursor pagination over a
single column, useful for large data-sets or infinite scroll:

```php
$qb = Account::qb();

$page1 = $qb->cursorFind(
    cursor_column: 'id',
    cursor: null,   // start from beginning
    max: 25,
    direction: 'asc',
);
// [
//   'items'       => Account[],
//   'next_cursor' => string|null,
//   'has_more'    => bool,
// ]

// Fetch the next page:
$page2 = $qb->cursorFind('id', $page1['next_cursor'], 25, 'asc');
```

Pass `null` as `$cursor` to start from the first page; pass
`$result['next_cursor']` for subsequent pages. When `has_more` is `false`
and `next_cursor` is `null` you have reached the end.

Throws `ORMQueryException` when `$max < 1` or `$direction` is not
`'asc'`/`'desc'`.

---

## Column projection (field selection)

`ORMTableQuery::selectWithColumns()` restricts the `SELECT` clause to a
specific set of columns. This is the building block for GraphQL field
selection, sparse fieldset APIs, and any caller that knows in advance
which columns it needs:

```php
$table = $db->getTableOrFail('clients');
$tq    = ORM::query($table);

// Returns a QBSelect limited to the two requested columns.
$qb = $tq->selectWithColumns(['id', 'first_name'], max: 25);

// Pass the projected column list so each entity is marked partial automatically.
$results = ORM::results($table, $qb, ['id', 'first_name']);
foreach ($results->fetchAllClass() as $client) {
    // $client->isPartial() === true; accessing other columns throws ORMRuntimeException.
    echo $client->client_id;
    echo $client->client_first_name;
}
```

**Rules:**

- Column names must exist in the table; unknown names throw
  `DBALRuntimeException`.
- Private columns are silently excluded from the projection.
- When every requested column is private the method falls back to a full
  `SELECT *`.
- Both short names (`id`) and full names (`client_id`) are accepted.

::: tip
When fetching relatives via a relation that has a `select` projection
(see [Per-relation column projection](./relations.md#per-relation-column-projection)),
`ORMController` calls `selectWithColumns()` internally and automatically
marks the returned entities as partial. Accessing an unloaded column on a
partial entity throws `ORMRuntimeException`.
:::

---

## Computed values

`QBSelect::selectComputed()` adds an extra `expression AS _gobl_<var_name>`
column to any `SELECT` query. When PDO hydrates the resulting row into an
`ORMEntity`, any column alias that begins with `_gobl_` is transparently
intercepted and stored in a separate, schema-independent slot instead of
being validated as a real column.

```php
use Gobl\DBAL\Queries\QBSelect;

$qb = $tq->select(max: 100);

// Inject a computed expression under the alias "rank"
$qb->selectComputed('RANK() OVER (ORDER BY account_balance DESC)', 'rank');

foreach ($results->fetchAllClass() as $account) {
    if ($account->hasComputedValue('rank')) {
        echo $account->getComputedValue('rank'); // e.g. "3"
    }
}
```

`QBSelect::computedAlias(string $var_name): string` returns the prefixed
alias string (`_gobl_{var_name}`) so you can reference it safely in `ORDER
BY` or outer queries:

```php
$alias = QBSelect::computedAlias('rank'); // "_gobl_rank"
$qb->orderBy([$alias => 'ASC']);
```

**API summary:**

| Method                                                                   | Description                                    |
| ------------------------------------------------------------------------ | ---------------------------------------------- |
| `QBSelect::computedAlias(string $var_name): string`                      | Returns `_gobl_{var_name}`                     |
| `QBSelect::selectComputed(string $expression, string $var_name): static` | Appends `expr AS _gobl_var` to the SELECT list |
| `ORMEntity::getComputedValue(string $var_name): mixed`                   | Returns the stored value, or `null` if absent  |
| `ORMEntity::hasComputedValue(string $var_name): bool`                    | Returns `true` when the slot was populated     |

**Behaviour notes:**

- Computed slots are never validated against the schema, never marked as
  dirty, and never written to the DB.
- The `column_prefix` is irrelevant: the var name you pass to
  `selectComputed` / `getComputedValue` is used as-is.
- The column name `computed_value` is a forbidden schema column name to
  prevent conflicts with `getComputedValue()` / `hasComputedValue()`.

::: info Internal use
Computed slots are also what enables single-query batch loading for
`through` and `join` relation link types. The orm controller injects a
`_gobl_batch_key` alias to carry the pivot / intermediate FK value back
alongside each result row, without needing a separate lookup.
:::

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
