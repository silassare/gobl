# Controllers

Each generated entity comes with a companion `*Controller` class that
wraps the common CRUD operations in a single, type-safe API.

## Instantiation

```php
use App\Models\UserController;

$controller = new UserController($db);
```

---

## Create

```php
// From an associative array:
$user = $controller->addItem([
    'user_name'  => 'Jane',
    'user_email' => 'jane@example.com',
    'user_age'   => 30,
]);

// Or from an already-populated entity:
$entity = new User();
$entity->setUserName('Bob')->setUserEmail('bob@example.com');
$user = $controller->addItem($entity);
```

`addItem()` fires the CRUD event pipeline, validates the data, inserts
the row, and returns the persisted `User` entity with its auto-increment
id set.

---

## Read one

```php
// By primary key:
$user = $controller->getItem(['user_id' => 1]);

// Returns null if not found.
```

---

## Read many

```php
use Gobl\DBAL\Filters\Filters;

$filters = (new Filters($db))
    ->eq('user_status', 'active')
    ->gte('user_score', 100);

$results = $controller->getAllItems($filters, max: 20, offset: 0);

foreach ($results as $user) {
    echo $user->getUserName();
}
```

`getAllItems()` returns an `ORMResults` iterator. Use `count()` to get
the number of results in the current page.

### Custom SELECT query

```php
$qb = User::qb();  // returns a UsersQuery (generated ORMTableQuery subclass)
$qb->where($qb->filters()->like('user_name', 'j%'))
   ->orderBy(['user_name ASC'])
   ->limit(10);

$rows = $controller->getAllItemsCustom($qb->getQBSelect());
```

---

## Update one

```php
$updated = $controller->updateOneItem(
    ['user_id' => 1],                    // filters (identifies the row)
    ['user_name' => 'Jane Doe']          // new values
);
```

Returns the updated entity, or `null` if no row matched.

---

## Update many

```php
$count = $controller->updateAllItems(
    ['user_status' => 'active'],         // new values
    $filters,                            // which rows
    max: 100                             // optional row limit
);
```

Returns the number of affected rows.

---

## Delete one

```php
$deleted = $controller->deleteOneItem(['user_id' => 1]);
// Returns the deleted entity, or null.
```

### Soft delete

If the table has soft-delete columns (`deleted` + `deleted_at`):

```php
$controller->deleteOneItem(['user_id' => 1], soft: true);
```

Soft-deleted rows are excluded from results by default. To include them,
call `includeSoftDeletedRows()` on the query before fetching:

```php
$qb = User::qb();
$qb->includeSoftDeletedRows();
$controller->getAllItemsCustom($qb->getQBSelect());
```

---

## Delete many

```php
$count = $controller->deleteAllItems($filters, max: 50);
```

---

## Relation helpers

Relation methods are generated on the **entity**, not on the controller.
The entity calls `getAllRelatives()` / `getRelative()` on the target
controller internally.

```php
// one-to-many: defined as relation 'accounts' on the clients table
$client   = Client::ctrl()->getItem(['client_id' => 1]);
$accounts = $client->getAccounts();          // Account[]
$accounts = $client->getAccounts(max: 20);   // paginated

// many-to-one: defined as relation 'client' on the accounts table
$account = Account::ctrl()->getItem(['account_id' => 5]);
$client  = $account->getClient();            // Client|null
```

For advanced use-cases call the controller helpers directly:

```php
use Gobl\ORM\ORM;

$ctrl     = Account::ctrl();
$relation = Client::table()->getRelation('accounts');

// getAllRelatives($host, $relation, $filters, $max, $offset, $order_by, &$total)
$list = $ctrl->getAllRelatives($client, $relation, max: 50);

// getRelative($host, $relation, $filters, $order_by) - returns single or null
$first = $ctrl->getRelative($client, $relation);
```

---

## Request-based CRUD

`ORMRequest` parses an incoming request into column values, filters,
ordering, pagination, and relation hints. It is a higher-level helper
for HTTP API endpoints - its exact interface is documented in the
API reference (see `src/ORM/ORMRequest.php`).
