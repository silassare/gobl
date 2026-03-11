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
$client = Client::ctrl()->getItem(['client_id' => 1]);

// getAccounts() is generated on ClientBase because the relation is named 'accounts'
$accounts = $client->getAccounts();          // returns Account[]
$accounts = $client->getAccounts(max: 20);   // paginated
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
$account = Account::ctrl()->getItem(['account_id' => 5]);

// getClient() is generated on AccountBase because the relation is named 'client'
$client = $account->getClient();  // returns Client|null
```

---

## One-to-One

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
$post = Post::ctrl()->getItem(['post_id' => 1]);

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
