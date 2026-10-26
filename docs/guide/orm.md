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

Every generated entity extends `ORMEntity` and exposes, per column, a **typed property** named
after the column's short name (without the prefix) plus a getter and a fluent setter. For a
`clients` table with `column_prefix: 'client'` and columns `id`, `first_name`, `last_name`:

```php
$client = new Client();

// Setters (fluent) - method names are derived from the column short name
$client->setFirstName('Jane')
       ->setLastName('Doe');

// Getters
echo $client->getFirstName();  // 'Jane'
echo $client->getID();         // null until persisted

// The property is the same column, with the same type
$client->first_name = 'Jane';
echo $client->first_name;

// The full name goes through magic access, as it always did
$client->client_first_name = 'Jane';

// Convert to array / JSON (sensitive columns are redacted)
$client->toArray();

// Persist to the database
$client->save();
```

### Columns are typed properties

A column is a **virtual property with hooks**, which is what the generated base class declares:

```php
public string $first_name {
    get => $this->_oebRead(self::COL_FIRST_NAME);
    set(string $value) {
        $this->_oebWrite(self::COL_FIRST_NAME, $value);
    }
}
```

So the column's type is enforced by PHP, at the assignment, instead of being described by a
docblock the generator writes. The property holds no value of its own: both hooks reach the
entity's row, through the same code the magic getter and setter reach.

Where a column accepts more than it answers with, the setter is wider than the property. A `map`
column reads as a `Map` and accepts an `array` or a `JsonPatch`:

```php
$client->data = ['theme' => 'dark'];     // accepted
$client->data->get('theme');             // read back as a Map
$client->first_name = 42;                // TypeError
```

Three things answer differently than they did under magic access, because PHP runs the `get` hook
instead of `__isset()`:

| | with a typed property |
| --- | --- |
| `isset($client->note)` on a nullable column holding null | **`false`** -- it is a question about the value now, not about the schema. Use `$table->hasColumn()` for the schema question |
| `isset($client->note)` on a partially loaded entity | **throws** -- `isset()` runs the hook and cannot opt out of its exception. Use `isColumnLoaded()` |
| `unset($client->note)` | **`Error`** -- a hooked property cannot be unset. It used to be a silent no-op |

And a column name now appears in `get_object_vars()`, `var_dump()` and `json_encode()`, where it
used not to. `toArray()` is still the way to get an entity's values: it redacts sensitive columns
and filters a partial projection, which none of those three do.

### Reading a row

A row the database answers with carries DB-form values, which `ORMResults` places with
`_oebHydrate()` -- where `Type::dbToPhp()` runs. It does not write the entity's properties, so a
raw JSON string never meets a column's PHP type. `hydrate()` is the other direction: it assigns
PHP-form values as a caller would, and is type-checked like any property write.

### Save state

The entity tracks unsaved changes via `isSaved()` and `isNew()`:

```php
$client = Client::ctrl()->getItem(ORMOptions::makeFromFilters([...]));
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

`save()` goes through the controller (`addItem()` / `updateOneItem()`), so the CRUD events
fire, but it is server code: it may write the entity's
[private and sensitive columns](./schema.md#private-and-sensitive-columns), which a request's
form may not (`CRUD::trusted()`; only those two checks are skipped).

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

### Patching a JSON column

Assigning a whole document to a `json`, `map` or `list` column writes that document: the column
ends up holding exactly what was assigned, so a value read before another transaction wrote to the
column carries its keys back as they were. Assigning a **patch** says what changed instead, and the
UPDATE asks the database to apply it to the row it holds:

```php
// Both forms build a JsonPatch over the column's current value.
$client->setDataKey('prefs.theme', 'dark')     // generated per JSON column
    ->removeDataKey('prefs.beta');

$client->data = $client->patchData()
    ->set('prefs.theme', 'dark')
    ->remove('prefs.beta');

$client->save();
// UPDATE ... SET client_data = JSON_REMOVE(JSON_SET(..., '$.prefs.theme', ...), '$.prefs.beta')
```

Two transactions patching different paths of one column therefore keep each other's writes, where
two writing the document would not. Two writing the same path are still the last one in.

The objects above a written path are made from the column's own value, so a path the document does
not have yet is created, and an intermediate holding something other than an object is replaced --
as `set()` does in PHP. The entity reads back the patch applied to the value it held, which is what
the row will hold.

The whole document is written instead of the paths when:

- the write is an INSERT -- there is no row to apply a patch to;
- a path is empty, or a segment reads as an array index (`tags.0`), which a JSON path expression
  cannot tell from a member named `0`;
- two of the patch's paths are the same or one is inside the other, so the result would depend on
  the order they are applied in;
- a value does not encode as JSON.

The result is the same in each case; what is lost is only the merge the database would have done.
MySQL and PostgreSQL keep an object's members in an order of their own, so a patched document may
come back with its keys in another order than it was written in.

Use `JsonPatch::setAt()` / `removeAt()` to address a key that holds a dot or a bracket: they take
the path as segments, where `set()` and `remove()` read it as a dot path.

---

## Results collection

`getAllItems()` returns an `ORMResults` iterator. You can pass an
`ORMOptions` (or `null` for defaults) to control pagination:

```php
use Gobl\ORM\ORMOptions;

// Paginated request
$request = ORMOptions::makePaginated(max: 20, page: 1, order_by: ['user_name ASC']);
$results = $userController->getAllItems($request);

foreach ($results as $user) {
    echo $user->getName();
}

echo $results->count();       // rows in this page
echo $results->getTotal($request);  // total matched rows (ignores LIMIT)
```

`ORMResults` carries metadata alongside the rows:

| Method                                                     | Description                                                                                                     |
| ---------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------- |
| `fetchAllClass()`                                          | Returns all rows as a typed entity array                                                                        |
| `count()`                                                  | Number of rows in this page                                                                                     |
| `getTotal(?WithPaginationInterface $options, bool $force)` | Runs a COUNT(\*) query if needed; returns total matched rows (ignores LIMIT)                                    |
| `groupByKey(bool)`                                         | Returns a generator that yields entities keyed by their identity key                                            |
| `groupBy(callable, bool)`                                  | Returns a generator that yields entities grouped by a key generated from the entity using the provided callable |

---

## Writing raw ORM queries

`ORMTableQuery` (and its generated subclasses) wraps `QBSelect` with the
table and alias pre-configured. Obtain one via `ORM::query()`:

```php
use Gobl\ORM\ORM;
use Gobl\ORM\ORMOptions;

$table = $db->getTableOrFail('users');
$tq    = ORM::query($table); // returns the generated UsersQuery instance

$tq->where(
    $tq->filters()
       ->eq('user_status', 'active')
       ->gte('user_created_at', '2024-01-01')
);

// find() returns ORMResults directly
$request = ORMOptions::makePaginated(max: 10, order_by: ['user_name ASC']);
$results = $tq->find($request);

foreach ($results as $user) { /* ... */ }

// select() returns a raw QBSelect (for custom composition)
$qb = $tq->select($request);
$results = ORM::results($table, $qb);
```

---

## Cursor-based pagination

Use `ORMOptions::makeCursorBased()` with `ORMTableQuery::find()` for
stable cursor pagination over large data-sets or infinite scroll:

```php
use Gobl\ORM\ORMOptions;

$qb = Account::qb();   // generated AccountsQuery instance
$max = 25;           // items per page

$options = ORMOptions::makeCursorBased(
    cursor_column: 'id',
    max: $max,
    cursor: null,        // null = start from the beginning
    direction: 'ASC',
);

$results = $qb->find($options);

$data      = $results->getItemsWithCursorMeta($options);
$items        = $data['items'];         // Account[]
$nextCursor   = $data['next_cursor'];   // string|null — value to pass as $cursor on the next request
$cursorColumn = $data['cursor_column']; // string|null — full column name used for cursor pagination
$hasMore      = $data['has_more'];      // bool

// Fetch the next page by passing the cursor back:
$next = ORMOptions::makeCursorBased('id', $max, $nextCursor, 'ASC');
$results2 = $qb->find($next);
```

When `$hasMore` is `false` and `$nextCursor` is `null` you have reached
the last page. `$options->isCursorBased()` returns `true` when a cursor-based
request is in use.

`cursor_column` is the full column name (including table prefix) used for pagination, or `null` when `$max` is not set. It is useful when building generic API layers that need to relay pagination metadata to clients.

Throws `ORMQueryException` when `$direction` is not `'ASC'`/`'DESC'`.

---

## Column projection (field selection)

`ORMTableQuery::find()` accepts an optional `$restrict_to_columns` array that
restricts the `SELECT` clause to a specific set of columns. This is the
building block for GraphQL field selection, sparse fieldset APIs, and any
caller that knows in advance which columns it needs:

```php
$table = $db->getTableOrFail('clients');
$tq    = ORM::query($table);

// find() with a column restriction: only 'id' and 'first_name' are fetched.
$results = $tq->find(null, ['id', 'first_name']);

foreach ($results->fetchAllClass() as $client) {
    // Entities are automatically marked partial when fewer columns are loaded.
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
`ORMController` calls `find()` with the restricted column list internally and
automatically marks the returned entities as partial. Accessing an unloaded
column on a partial entity throws `ORMRuntimeException`.
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

$request = ORMOptions::makePaginated(max: 100);
$qb = $tq->select($request);

// Inject a computed expression under the alias "rank"
$qb->selectComputed('rank', 'RANK() OVER (ORDER BY account_balance DESC)');

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
| `QBSelect::selectComputed(string $var_name, string $expression): static` | Appends `expr AS _gobl_var` to the SELECT list |
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
