# Filters

`Filters` provides a fluent API for building SQL `WHERE` conditions.
All comparison methods bind values safely - no raw string interpolation.

## Basic comparisons

```php
use Gobl\DBAL\Filters\Filters;
use Gobl\DBAL\Queries\QBSelect;

$qb = new QBSelect($db);

$qb->from('users', 'u')
   ->where(
       $qb->filters()
          ->eq('u.user_status', 'active')       // =
          ->neq('u.user_role', 'banned')         // !=
          ->lt('u.user_age', 18)                 // <
          ->lte('u.user_created_at', '2024-12-31') // <=
          ->gt('u.user_score', 100)              // >
          ->gte('u.user_score', 0)               // >=
          ->like('u.user_name', 'jo%')           // LIKE
          ->notLike('u.user_email', '%spam%')    // NOT LIKE
   );
```

Conditions added in sequence are combined with **AND** by default.

---

## NULL checks

```php
$filters->isNull('u.user_deleted_at');       // IS NULL
$filters->isNotNull('u.user_confirmed_at'); // IS NOT NULL
```

---

## Boolean checks

```php
$filters->isTrue('u.user_active');   // = 1 / = true
$filters->isFalse('u.user_banned');  // = 0 / = false
```

---

## IN / NOT IN

```php
$filters->in('u.user_role', ['admin', 'editor', 'author']);
$filters->notIn('u.user_id', [1, 2, 3]);

// Sub-select as right operand
$sub = new QBSelect($db);
$sub->from('banned_users', 'b')->select('b', ['user_id']);
$filters->notIn('u.user_id', $sub);
```

---

## JSON containment

Three helpers cover JSON column queries. The column must use a
`native_json`-enabled type (`map`, `list`, or `json` with `native_json: true`).

```php
// Whole-column containment: does the JSON value contain this fragment?
// MySQL: JSON_CONTAINS(col, value)   PostgreSQL: col @> value::jsonb
$filters->contains('u.user_meta', ['role' => 'admin']);

// Sub-path containment: extracted via path notation (table.column#path)
// column#foo.bar extracts col->'$.foo.bar' before checking containment
$filters->containsAtPath('u.user_meta#permissions', 'orders:write');

// Key existence: does the top-level JSON object have this key?
// MySQL: JSON_CONTAINS_PATH(col, 'one', '$.key')   PostgreSQL: jsonb_exists(col, 'key')
$filters->hasKey('u.user_meta', 'preferences');
```

::: warning SQLite limitation
`contains()` and `containsAtPath()` are not supported on SQLite.
`hasKey()` is supported via `json_extract`.
:::

---

## OR / AND - combining conditions

By default all conditions are ANDed. Use `->or()` to switch the next
condition to OR, and `->and()` to explicitly switch back to AND.

### Inline OR

```php
$filters
    ->eq('u.user_role', 'admin')
    ->or()     // next condition joined with OR
    ->eq('u.user_role', 'superadmin');
// (user_role = ? OR user_role = ?)
```

### Grouped OR with a callable

Pass a callable that receives a `Filters` sub-group and **must return it**:

```php
$filters
    ->eq('u.user_status', 'active')
    ->or(function (Filters $g) {
        return $g->eq('u.user_role', 'admin')
                 ->or()
                 ->eq('u.user_role', 'superadmin');
    });
// (user_status = ? AND (user_role = ? OR user_role = ?))
```

### Grouped AND with a callable

```php
$filters->and(function (Filters $g) {
    return $g->isNotNull('u.user_email')
             ->like('u.user_email', '%@example.com');
});
```

### Merging another `Filters` instance

```php
$base  = $qb->filters()->eq('u.user_status', 'active');
$extra = $qb->filters()->gt('u.user_score', 500);

$base->where($extra);   // AND (default)
$base->or($extra);      // OR
```

> **Important:** `or()` / `and()` callables **must** return the same `$g` instance
> passed in. Returning a different object throws a `DBALRuntimeException`.

---

## Column-to-column comparisons

Use `QBExpression` to compare two columns rather than a column to a value:

```php
use Gobl\DBAL\Queries\QBExpression;

$filters->eq('p.post_author_id', new QBExpression('u.user_id'));
// -> p.post_author_id = u.user_id   (no binding)
```

You can also use shortcut for expressions like this:

```php
$filters->eq('p.post_author_id', $qb->expr('u.user_id'));
```

This is the correct way to express join conditions:

```php
$qb->leftJoin('p')
   ->to('users', 'u')
   ->on($qb->filters()->eq('p.post_author_id', $qb->expr('u.user_id')));

```

---

## Automatic type coercion

When the left operand is a resolvable column reference - `alias.column_full_name` - Gobl
looks up the column's type and converts the right-operand value to its DB-compatible form
before binding it to the PDO statement via `TypeUtils::runCastValueForFilter()`.
This matches exactly the conversion applied when
writing entity properties, so filter values can be written in the same "PHP-friendly" format
used elsewhere in your application.

```php
// Date string is automatically converted to a Unix timestamp integer
$qb->where(fn($f) => $f->lte('u.user_created_at', '2024-12-31'));
// Bound as: 1735603200 (not the raw string)

// bool true is automatically converted to 1
$qb->where(fn($f) => $f->isTrue('u.user_active'));
// Bound as: 1

// String '42' on an int column is automatically cast to int 42
$qb->where(fn($f) => $f->eq('u.user_age', '42'));
// Bound as: 42

// IN list: each element is individually converted
$qb->where(fn($f) => $f->in('u.user_created_at', ['2024-01-01', '2024-06-01']));
// Bound as: [1704067200, 1717200000]
```

**Format:**

- Left operand must be `alias.column_full_name` (e.g., `'u.user_created_at'`).
  A bare column name without an alias prefix is not resolved - no coercion happens.
- Right operand can be `null` for nullable columns, an array for `in()`/`notIn()`,
  a `QBExpression` (column-to-column comparison), or a `QBSelect` sub-query - all
  of these bypass coercion.
- `contains()` / `containsAtPath()` / `hasKey()` bypass coercion: their right-operand
  is handled by the JSON-specific serializer.

If the value cannot be coerced to the expected type (e.g., `'banana'` on an `int` column),
a `TypesInvalidValueException` is thrown immediately, before the query is executed.

---

## Array-based filters (`fromArray`)

For programmatic or serialized filter definitions:

```php
$filters = Filters::fromArray(
    [
        'user_status', 'eq', 'active',
        'AND',
        'user_age', 'gte', 18,
        'AND',
        ['user_role', 'eq', 'admin', 'OR', 'user_role', 'eq', 'moderator'],
    ],
    $qb
);
```

Supported operator strings map to `Gobl\DBAL\Operator` values:
`eq`, `neq`, `lt`, `lte`, `gt`, `gte`, `like`, `not_like`, `in`, `not_in`,
`is_null`, `is_not_null`, `is_true`, `is_false`, `contains`, `has_key`.

---

## Low-level `add()`

When you need an operator not covered by the helper methods:

```php
use Gobl\DBAL\Operator;

$filters->add(Operator::LIKE, 'u.user_name', 'jo%');
```

---

## Note on `(( ... ))` wrapping

Generated SQL often contains double parentheses:

```sql
WHERE ((user_status = ? AND user_age > ?))
```

This is intentional. The outer `()` comes from `Filters::__toString()` wrapping
the root group; the inner `()` is the group itself. For compound expressions the
extra grouping guarantees operator precedence is never ambiguous.
