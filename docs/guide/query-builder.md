# Query Builder

Gobl ships four query-builder classes covering all DML operations.
Each class is driver-aware: the same PHP code generates correct SQL for
MySQL, PostgreSQL, and SQLite.

## SELECT - `QBSelect`

```php
use Gobl\DBAL\Queries\QBSelect;

$qb = new QBSelect($db);

// FROM clause with alias
$qb->from('posts', 'p');

// LEFT JOIN: innerJoin/leftJoin/rightJoin(host_alias)->to(table, alias)->on(filters)
$qb->leftJoin('p')
   ->to('users', 'u')
   ->on($qb->filters()->eq('p.post_author_id', new \Gobl\DBAL\Queries\QBExpression('u.user_id')));

$qb->select('p', ['post_id', 'post_title'])  // columns to select from alias 'p'
   ->select('u', ['user_name'])
   ->where(
       $qb->filters()
          ->eq('p.post_published', true)
          ->gte('p.post_created_at', '2024-01-01')
   )
   ->orderBy(['p.post_created_at DESC'])
   ->limit(20)
   ->offset(40);

$stmt = $db->select($qb->getSqlQuery(), $qb->getBoundValues());
$rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
```

### Inspect the generated SQL

```php
echo $qb->getSqlQuery();
// SELECT p.post_id, p.post_title, u.user_name
// FROM gObL_posts AS p
// LEFT JOIN gObL_users AS u ON (p.post_author_id = u.user_id)
// WHERE ((p.post_published = ? AND p.post_created_at >= ?))
// ORDER BY p.post_created_at DESC LIMIT 20 OFFSET 40

print_r($qb->getBoundValues());
// [true, '2024-01-01']
```

### Join types

| Method              | SQL          |
| ------------------- | ------------ |
| `->innerJoin(host)` | `INNER JOIN` |
| `->leftJoin(host)`  | `LEFT JOIN`  |
| `->rightJoin(host)` | `RIGHT JOIN` |

The host argument is the **already-declared alias** (from `from()` or a
prior `->to()` call), not the target table name:

```php
// CORRECT - host is alias 'p' (declared via from())
$qb->leftJoin('p')->to('users', 'u')->on($condition);

// WRONG - 'users' is the target, not the host
// $qb->leftJoin('users', 'u')->on($condition);
```

---

## INSERT - `QBInsert`

```php
use Gobl\DBAL\Queries\QBInsert;

$qb = new QBInsert($db);

$qb->into('posts')
   ->values([
       'post_title'     => 'My Post',
       'post_body'      => 'Hello world',
       'post_author_id' => 42,
   ]);

$lastId = $db->insert($qb->getSqlQuery(), $qb->getBoundValues());
```

### Batch insert

```php
$qb->into('posts')
   ->values([
       ['post_title' => 'Post A', 'post_body' => 'Body A', 'post_author_id' => 1],
       ['post_title' => 'Post B', 'post_body' => 'Body B', 'post_author_id' => 2],
   ]);
```

---

## UPDATE - `QBUpdate`

```php
use Gobl\DBAL\Queries\QBUpdate;

$qb = new QBUpdate($db);

$qb->update('posts')
   ->set(['post_title' => 'Updated title', 'post_published' => true])
   ->where($qb->filters()->eq('post_id', 7))
   ->limit(1);

$affectedRows = $db->update($qb->getSqlQuery(), $qb->getBoundValues());
```

---

## DELETE - `QBDelete`

```php
use Gobl\DBAL\Queries\QBDelete;

$qb = new QBDelete($db);

$qb->from('posts')
   ->where($qb->filters()->lt('post_created_at', '2020-01-01'))
   ->limit(100)
   ->orderBy(['post_created_at ASC']);

$affectedRows = $db->delete($qb->getSqlQuery(), $qb->getBoundValues());
```

---

## Transactions

```php
$db->runInTransaction(function () use ($db) {
    // all operations here are atomic
    $db->insert(...);
    $db->update(...);
});
```

Manual control:

```php
$db->beginTransaction();
try {
    $db->insert(...);
    $db->commit();
} catch (\Throwable $e) {
    $db->rollBack();
    throw $e;
}
```

---

## Bound values & security

All user-supplied values are passed as **bound parameters** - never
interpolated into the SQL string. `getBoundValues()` returns the ordered
array that PDO binds at execution time.

```php
// Never do this:
$qb->where("post_title = '{$userInput}'");   // UNSAFE: SQL injection risk

// Always use the filter API:
$qb->where($qb->filters()->eq('post_title', $userInput));  // safe: parameterized binding
```
