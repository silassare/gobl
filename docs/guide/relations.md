# Relations

Gobl supports four relation types between tables, declared inside the
schema array and resolved at ORM code-generation time.

Relations do **not** create SQL constraints - those go under `constraints`.
The `link` key is optional; Gobl auto-detects the join columns from FK
constraints when possible.

## One-to-Many

A single row in table A relates to many rows in table B.

```php
// In the schema array of the "clients" table:
'relations' => [
    'accounts' => [
        'type'   => 'one-to-many',
        'target' => 'accounts',
        // 'link' is omitted - auto-detected from the FK constraint on accounts.client_id => clients.id
    ],
],
```

The generated **entity** exposes a typed getter method named after the relation:

```php
use Gobl\ORM\ORMOptions;

$client = Client::ctrl()->getItem(ORMOptions::makeFromFilters(['client_id' => 1]));

// getAccounts() returns ORMResults
$results  = $client->getAccounts();
$results  = $client->getAccounts(ORMOptions::makePaginated(max: 20));

foreach ($results as $account) { /* ... */ }
```

---

## Many-to-One

The inverse of one-to-many - the FK lives in this table.

```php
// In the "accounts" table schema:
'relations' => [
    'client' => [
        'type'   => 'many-to-one',
        'target' => 'clients',
    ],
],
```

```php
$account = Account::ctrl()->getItem(ORMOptions::makeFromFilters(['account_id' => 5]));

// getClient() is generated on AccountBase because the relation is named 'client'
$client = $account->getClient();  // returns Client|null
```

Same FK structure as many-to-one, but with a semantically unique join.

```php
'relations' => [
    'profile' => [
        'type'   => 'one-to-one',
        'target' => 'user_profiles',
    ],
],
```

---

## Many-to-Many (via pivot table)

Two tables linked through a pivot (junction) table. Gobl uses a `through`
link; auto-detection reads FK constraints on the pivot table.

```php
// In the "posts" table schema:
'relations' => [
    'tags' => [
        'type'   => 'many-to-many',
        'target' => 'tags',
        'link'   => [
            'type'        => 'through',
            'pivot_table' => 'post_tags',   // table with FK to both posts and tags
            // host_to_pivot and pivot_to_target are auto-detected from FK constraints
        ],
    ],
],
```

```php
$post = Post::ctrl()->getItem(ORMOptions::makeFromFilters(['post_id' => 1]));

// getTags() is generated on PostBase because the relation is named 'tags'
$tags = $post->getTags();
```

---

## Accessing relations

All relation helpers are generated on the **entity base class** (e.g.
`ClientBase`), not on the controller. They delegate to `getAllRelatives()`
or `getRelative()` on the target controller internally.

Generated method naming follows the relation name:

| Relation name | Relation type            | Generated method   |
| ------------- | ------------------------ | ------------------ |
| `accounts`    | `one-to-many`            | `getAccounts(...)` |
| `client`      | `many-to-one`            | `getClient(...)`   |
| `profile`     | `one-to-one`             | `getProfile(...)`  |
| `tags`        | `many-to-many` (through) | `getTags(...)`     |

---

## Using the `ref:` shorthand for FK columns

When a column should share the type of another table's PK column, use
`ref:table.column` in the column definition:

```php
'account_id' => 'ref:accounts.id',   // inherits type from accounts.id (shared instance)
```

This is equivalent to explicitly declaring the same `bigint unsigned`
type. The FK **constraint** still needs to be declared separately under
`constraints`.

---

## Eager vs lazy loading

By default relation data is **lazily** fetched - the extra query runs only
when you call `$entity->getAccounts()`. Gobl does not issue N+1 queries
automatically; use `getAllRelatives()` directly when you need to bulk-load:

```php
$clientCtrl = Client::ctrl();
$accounts   = Account::ctrl();
$relation   = Client::table()->getRelation('accounts');

foreach ($clients as $client) {
    // Each call issues one SELECT - batch manually when needed
    $list = $accounts->getAllRelatives($client, $relation);
}
```

---

## Batch loading (avoiding N+1)

When you need relatives for multiple host entities at once, use the batch
methods. They issue a single `IN (...)` query instead of one query per host.

```php
$accounts  = Account::ctrl();
$relation  = Client::table()->getRelation('accounts');

// One-to-many: all accounts per client, keyed by toIdentityKey()
$map = $accounts->getAllRelativesBatch($clients, $relation);
// $map[$client->toIdentityKey()] = Account[]

// Many-to-one: single relative per host
$clientsCtrl = Client::ctrl();
$clientRel   = Account::table()->getRelation('client');
$map = $clientsCtrl->getRelativeBatch($accounts, $clientRel);
// $map[$account->toIdentityKey()] = Client|null
```

::: info All link types support single-query batching
Since Gobl 3.x, `LinkThrough` (pivot-table many-to-many) and `LinkJoin`
(multi-hop) both issue a **single** query using a JOIN + `IN (...)` strategy
with a computed `_gobl_batch_key` alias to route results back to their host
entity. Composite FK columns are also fully supported in `LinkColumns`;
the batch key becomes `_gobl_batch_key_0`, `_gobl_batch_key_1`, etc.
:::

---

## Counting relatives

```php
$accounts = Account::ctrl();
$relation = Client::table()->getRelation('accounts');

// Single host
$count = $accounts->countRelatives($client, $relation);

// Multiple hosts in one batch query
$map = $accounts->countRelativesBatch($clients, $relation);
// $map[$client->toIdentityKey()] = int (0 when none)
```

---

## Per-relation column projection

Restrict which columns are fetched for a relation target to reduce data
transfer over the wire.

```php
// Fluent builder: return only id + first_name for the 'client' side
$t->belongsTo('client')
    ->from('clients')
    ->select('id', 'first_name');   // returns the Relation object
```

```php
// Array schema
'relations' => [
    'client' => [
        'type'   => 'many-to-one',
        'target' => 'clients',
        'select' => ['id', 'first_name'],
    ],
],
```

At runtime you can also override the projection:

```php
$relation = Account::table()->getRelation('client');
$relation->setSelect(['id', 'first_name', 'last_name']); // narrower set
$relation->setSelect(null);                               // back to all columns
```

**Rules:**

- Column names must exist in the **target** table; unknown names are caught by
  `Relation::assertIsValid()` (called during `lock()`) and throw `DBALRuntimeException`.
- `setSelect()` does not validate column names at assignment time — validation only
  runs when the relation is locked, or at query time via `resolveSelectColumns()`.
- Private columns cannot be included in the generated SQL; they are silently filtered
  by `ORMTableQuery::find()` at query time.
- When the entire projection reduces to zero columns (all were private), Gobl falls
  back to selecting all columns.

**Partial entity awareness:**

Entities loaded via a projection are marked as "partial". Accessing an
unloaded column on a partial entity throws `ORMRuntimeException`. Use
`isColumnLoaded()` as a guard:

```php
$client = $accounts->getRelative($account, $relation);

if ($client->isColumnLoaded('client_email')) {
    echo $client->client_email;
}

// Check whether the entity is partial at all
if ($client->isPartial()) {
    // only projected columns are available
}
```

You can also create partial entities manually via the factory:

```php
$db     = ORM::getDatabase('App\Db');
$table  = $db->getTableOrFail('clients');
$entity = ORM::entity($table, false, true)->markAsPartial(['client_id', 'client_first_name']);

$entity->isPartial();                    // true
$entity->isColumnLoaded('client_id');    // true
$entity->isColumnLoaded('client_email'); // false
```

`ORM::results($table, $qb)` achieves
the same for a full result set — every entity yielded by `fetchClass()` and
`fetchAllClass()` will be automatically marked partial when the query selects
fewer columns than the table defines.

---

## Virtual relation batch via `RelationControllerInterface`

`ORMEntityRelationController` implements `RelationControllerInterface`,
which now includes a `getBatch()` method for fetching all relatives for a
list of host entities through the controller abstraction layer:

```php
use Gobl\ORM\ORMEntityRelationController;
use Gobl\ORM\ORMOptions;

$ctrl = new ORMEntityRelationController(
    Account::table()->getRelation('client')
);

$map = $ctrl->getBatch($accounts, new ORMOptions());
// $map[$account->toIdentityKey()] = Client[]
```

An empty host list returns `[]` without touching the database.
