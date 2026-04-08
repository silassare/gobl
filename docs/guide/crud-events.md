# CRUD Events

Every controller method fires a sequence of events before and after the
database operation. Listeners can inspect data, mutate the form, reject
the action, or react to the result.

## Event lifecycle

### Create

```
BeforeCreate        <- validate / mutate the form
    BeforeCreateFlush   <- final chance to abort before INSERT
INSERT ...
    AfterEntityCreation <- row persisted; entity available
```

### Update (one row)

```
BeforeUpdate         <- validate / mutate new values
    BeforeEntityUpdate  <- entity already loaded from DB
    BeforeUpdateFlush   <- final chance before UPDATE
UPDATE ...
    AfterEntityUpdate   <- row updated; new entity available
```

### Update (many rows - `updateAllItems`)

```
BeforeUpdateAll       <- validate / mutate
    BeforeUpdateAllFlush
UPDATE ...
```

### Delete (one row)

```
BeforeDelete          <- inspect what will be deleted
    BeforeEntityDeletion
    BeforeDeleteFlush
DELETE ...
    AfterEntityDeletion
```

### Delete (many rows - `deleteAllItems`)

```
BeforeDeleteAll
    BeforeDeleteAllFlush
DELETE ...
```

### Read

```
BeforeRead
    (SELECT ...)
    AfterEntityRead
```

### Read all

```
BeforeReadAll
    (SELECT ...)
```

---

## Registering a listener

Every generated table comes with a `*Crud` class that is the subscription API.
Obtain an instance via `::new()` and register listeners with the `on*()` methods:

```php
use App\Db\UsersCrud;
use Gobl\CRUD\Events\BeforeCreate;
use Gobl\CRUD\Events\BeforeCreateFlush;

$crud = UsersCrud::new();

// Authorization listener - return true to allow, false + stopPropagation() to deny.
$crud->onBeforeCreate(function (BeforeCreate $action): bool {
    $table = $action->getTable();
    $form  = $action->getForm();

    // Reject if email is missing
    if (empty($form['user_email'])) {
        return false; // thrown as CRUDException
    }

    return true;
});

// Mutation listener - called after BeforeCreate is allowed, just before INSERT.
// BeforeCreateFlush has setField() / setForm() to modify the payload.
$crud->onBeforeCreateFlush(function (BeforeCreateFlush $action): void {
    $form = $action->getForm();

    // Hash the password before it is written to the database.
    if (isset($form['user_password'])) {
        $action->setField(
            'user_password_hash',
            password_hash($form['user_password'], \PASSWORD_BCRYPT)
        );
    }
});
```

### Implementing `CRUDEventListenerInterface`

For a strongly-typed, single-class listener implement `CRUDEventListenerInterface`
and pass it to `listen()`:

```php
use App\Db\UsersCrud;
use Gobl\CRUD\Interfaces\CRUDEventListenerInterface;
use Gobl\CRUD\Events\BeforeCreate;
use Gobl\CRUD\Events\BeforeCreateFlush;
// ... (all other event imports required by the interface)

class UsersPolicy implements CRUDEventListenerInterface
{
    public function onBeforeCreate(BeforeCreate $action): bool
    {
        // return false to deny
        return true;
    }

    // ... implement all interface methods
}

UsersCrud::new()->listen(new UsersPolicy());
```

---

## Column-level events

| Event                        | Fires                                       |
| ---------------------------- | ------------------------------------------- |
| `BeforeColumnUpdate`         | Any column is about to be written           |
| `BeforePKColumnWrite`        | A primary-key column is about to be written |
| `BeforePrivateColumnWrite`   | A private column is about to be written     |
| `BeforeSensitiveColumnWrite` | A sensitive column is about to be written   |

```php
use App\Db\UsersCrud;
use Gobl\CRUD\Events\BeforePrivateColumnWrite;

UsersCrud::new()->onBeforePrivateColumnWrite(
    function (BeforePrivateColumnWrite $action): bool {
        // return true to allow the write, false to deny (throws CRUDException)
        return isInternalRequest();
    }
);
```

---

## Entity events (`EntityEvent`)

`AfterEntityCreation`, `AfterEntityUpdate`, `AfterEntityDeletion`, and
`AfterEntityRead` all extend `EntityEvent` and expose the fully
hydrated entity:

```php
use App\Db\User;
use App\Db\UsersCrud;
use Gobl\CRUD\Events\AfterEntityCreation;

UsersCrud::new()->onAfterEntityCreation(
    function (User $entity, AfterEntityCreation $action): void {
        sendWelcomeEmail($entity->getUserEmail());
    }
);
```

---

## Relation context in read events

Both `BeforeRead` and `BeforeReadAll` carry an optional `Relation` when
the read is triggered by a relation traversal (e.g. `getRelative()` or
`getAllRelatives()`). Use `getRelation()` to distinguish a direct entity
fetch from a relation-scoped one inside a single listener:

```php
use App\Db\UsersCrud;
use Gobl\CRUD\Events\BeforeRead;

UsersCrud::new()->onBeforeRead(function (BeforeRead $action): bool {
    $relation = $action->getRelation();

    if ($relation !== null) {
        // Called via getRelative() / getRelativeBatch() — relation traversal.
        return canReadRelation($relation->getName());
    }

    // Direct entity read.
    return true;
});
```

When `getRelation()` returns `null` the read is a direct, non-relational
query. The same applies to `BeforeReadAll::getRelation()`.

::: info
`assertReadRelative()` and `assertReadAllRelatives()` delegate to
`assertRead()` / `assertReadAll()` with the `Relation` injected — so
you need only one `onBeforeRead` listener to handle both cases.
:::

---

## Complete event reference

| Event class                  | When                                                                 |
| ---------------------------- | -------------------------------------------------------------------- |
| `BeforeCreate`               | Before INSERT form is processed                                      |
| `BeforeCreateFlush`          | Immediately before INSERT                                            |
| `AfterEntityCreation`        | After INSERT                                                         |
| `BeforeRead`                 | Before SELECT one; `getRelation()` non-null for relation traversals  |
| `BeforeReadAll`              | Before SELECT many; `getRelation()` non-null for relation traversals |
| `AfterEntityRead`            | After each row is hydrated                                           |
| `BeforeUpdate`               | Before UPDATE form is processed                                      |
| `BeforeEntityUpdate`         | Entity loaded, before write                                          |
| `BeforeUpdateFlush`          | Immediately before UPDATE                                            |
| `AfterEntityUpdate`          | After UPDATE                                                         |
| `BeforeUpdateAll`            | Before bulk UPDATE                                                   |
| `BeforeUpdateAllFlush`       | Immediately before bulk UPDATE                                       |
| `BeforeDelete`               | Before DELETE one                                                    |
| `BeforeEntityDeletion`       | Entity loaded, before DELETE                                         |
| `BeforeDeleteFlush`          | Immediately before DELETE                                            |
| `AfterEntityDeletion`        | After DELETE                                                         |
| `BeforeDeleteAll`            | Before bulk DELETE                                                   |
| `BeforeDeleteAllFlush`       | Immediately before bulk DELETE                                       |
| `BeforeColumnUpdate`         | Any column write                                                     |
| `BeforePKColumnWrite`        | PK column write                                                      |
| `BeforePrivateColumnWrite`   | Private column write                                                 |
| `BeforeSensitiveColumnWrite` | Sensitive column write                                               |
