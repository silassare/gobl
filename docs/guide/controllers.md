# Controllers

Each generated entity comes with a companion `*Controller` class that
wraps the common CRUD operations in a single, type-safe API.

## Instantiation

```php
use App\Db\UsersController;

$controller = UsersController::new();
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
use Gobl\ORM\ORMOptions;

// By primary key:
$user = $controller->getItem(ORMOptions::makeFromFilters(['user_id' => 1]));

// Returns null if not found.
```

---

## Read many

```php
use Gobl\ORM\ORMOptions;

$request = ORMOptions::makePaginated(max: 20, offset: 0, order_by: ['user_name ASC']);
$results = $controller->getAllItems($request);

foreach ($results as $user) {
    echo $user->getUserName();
}
```

`getAllItems()` returns an `ORMResults` iterator. Use `count()` for the
current page size or `getTotal()` for the total matched rows.

To filter programmatically, use `ORMTableQuery` and `getAllItemsCustom()`:

```php
use App\Db\UsersQuery;
use Gobl\ORM\ORMOptions;

$tq = UsersQuery::new();
$tq->where($tq->filters()->eq('user_status', 'active'));

$request = ORMOptions::makePaginated(max: 20, offset: 0, order_by: ['user_name ASC']);
$results = $controller->getAllItemsCustom($tq->select($request));
```

### Custom SELECT query

```php
use App\Db\UsersQuery;
use Gobl\ORM\ORMOptions;

$tq = UsersQuery::new();
$tq->where($tq->filters()->like('user_name', 'j%'));

$request = ORMOptions::makePaginated(max: 10, order_by: ['user_name ASC']);
$results = $controller->getAllItemsCustom($tq->select($request));
```

---

## Update one

```php
use Gobl\ORM\ORMOptions;

$req = ORMOptions::makeFromFilters(['user_id' => 1]);
$req->setFormData(['user_name' => 'Jane Doe']);
$updated = $controller->updateOneItem($req);
```

Returns the updated entity, or `null` if no row matched.

---

## Update many

```php
use Gobl\ORM\ORMOptions;

$req = ORMOptions::makeFromFilters(['user_role' => 'guest']);
$req->setFormData(['user_status' => 'inactive']);
$req->setMax(100);
$count = $controller->updateAllItems($req);
```

Returns the number of affected rows.

---

## Delete one

```php
use Gobl\ORM\ORMOptions;

$deleted = $controller->deleteOneItem(ORMOptions::makeFromFilters(['user_id' => 1]));
// Returns the deleted entity, or null.
```

### Soft delete

If the table has soft-delete columns (`deleted` + `deleted_at`):

```php
$controller->deleteOneItem(ORMOptions::makeFromFilters(['user_id' => 1]), soft: true);
```

Soft-deleted rows are excluded from results by default. To include them,
call `includeSoftDeletedRows()` on the query before fetching:

```php
use App\Db\UsersQuery;

$tq = UsersQuery::new();
$tq->includeSoftDeletedRows();
$controller->getAllItemsCustom($tq->select());
```

---

## Delete many

```php
use Gobl\ORM\ORMOptions;

$req = ORMOptions::makeFromFilters(['user_status' => 'inactive']);
$req->setMax(50);
$count = $controller->deleteAllItems($req);
```

---

## Relation helpers

Relation methods are generated on the **entity**, not on the controller.
The entity calls `getAllRelatives()` / `getRelative()` on the target
controller internally.

```php
use Gobl\ORM\ORMOptions;

// one-to-many: defined as relation 'accounts' on the clients table
$client   = Client::ctrl()->getItem(ORMOptions::makeFromFilters(['client_id' => 1]));
$results  = $client->getAccounts();                          // ORMResults (all)
$results  = $client->getAccounts(ORMOptions::makePaginated(max: 20)); // paginated

foreach ($results as $account) { /* ... */ }

// many-to-one: defined as relation 'client' on the accounts table
$account = Account::ctrl()->getItem(ORMOptions::makeFromFilters(['account_id' => 5]));
$client  = $account->getClient();            // Client|null
```

For advanced use-cases call the controller helpers directly:

```php
use Gobl\ORM\ORMOptions;

$ctrl     = Account::ctrl();
$relation = Client::table()->getRelation('accounts');

// getAllRelatives returns ORMResults - pass an ORMOptions for pagination/ordering
$request = ORMOptions::makePaginated(max: 50);
$results = $ctrl->getAllRelatives($client, $relation, $request);
$list    = $results->fetchAllClass();

// getRelative - returns a single entity or null
$first = $ctrl->getRelative($client, $relation);
```

---

## Request-based CRUD

`ORMOptions` parses an incoming request into column values, filters,
ordering, pagination, and relation hints. It is a higher-level helper
for HTTP API endpoints - its exact interface is documented in the
API reference (see `src/ORM/ORMOptions.php`).
