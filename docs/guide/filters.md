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

`eq` / `neq` take `true`, `false`, `1`, `0`, and the strings `'1'`, `'0'`, `'true'`, `'false'`
(any case) whatever the column's strictness: a filter from a query string is always a string.

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

## Text search

`matches()` searches text, ignoring case and -- where the engine can -- accents. It answers the
same whatever the column stores, which `like()` does not: a MySQL JSON value carries a binary
collation, so `like` on a `map` column folds nothing while the same call on a `string` column
beside it folds both.

```php
// A term, never a pattern: the wildcards and the escaping are the compiler's.
$filters->matches('p.page_name', 'economie');

// On a JSON column, a path searches the value at that path.
$filters->matches('p.page_title#fr', 'economie');

// With no path, the whole document text is searched: a word of a page's body finds the page.
$filters->matches('p.page_title', 'economie');
```

`startsWith()` is the same search anchored at the start of the text:

```php
$filters->startsWith('p.page_name', 'econ');   // a name beginning with "econ"
```

The generated query methods take the term, and on a JSON column an optional path:

```php
PagesQuery::new()->whereNameMatches('economie');
PagesQuery::new()->whereTitleMatches('economie', 'fr');
PagesQuery::new()->whereTitleMatches('economie');       // the whole document
PagesQuery::new()->whereNameStartsWith('econ');
```

**It takes a term and not a pattern.** `'economie'`, never `'%economie%'`: `matches()` wraps the
term and escapes `%`, `_` and the escape character, so a term holding one of them searches for
that character instead of being a wildcard by accident. An empty term is refused, since it would
match every row.

**What folds is not the same on every engine**, so it is written down rather than discovered:

| engine | compiled as | case | accents |
| --- | --- | --- | --- |
| MySQL | `CONVERT(expr USING <charset>) COLLATE <collate> LIKE ? ESCAPE '!'` | yes | yes |
| PostgreSQL | `lower(expr::text) LIKE lower(?) ESCAPE '!'` | yes | no |
| SQLite | `lower(expr) LIKE lower(?) ESCAPE '!'` | ASCII only | no |

The charset and the collation are the table's own, or the database's when the table declares
none. PostgreSQL cannot do better in this shape: `LIKE` against a nondeterministic ICU collation
is refused before PostgreSQL 18, so folding accents there needs the `unaccent` extension and a
column of folded text to search.

::: warning This is a full table scan
`LIKE '%term%'` cannot use a B-tree index on any engine, JSON or not. Measured on MySQL 8 over
262 144 rows holding one small JSON document each, one row matching: 1 104 ms against the JSON
column, 460 ms against a plain column holding the same text, 0.5 ms with a `FULLTEXT` index on
it. The first two are both full scans and the difference between them is a constant factor; only
an index changes the shape.

So `matches()` is the right answer into the tens of thousands of rows, and it is not a substitute
for a search index beyond that.
:::

With no path the document *text* is searched, which carries the document's keys and any stored
id: a search for `body` matches a document having a `body` key. With a path it cannot.

---

## Searching at scale

Declaring a column [searchable](./column-types.md#searchable-text-string-enum-map-list-json) is what
makes the search above indexable -- and, as a side effect, what makes it answer the **same on every
engine**, since the folding then happens once in PHP instead of per driver in SQL:

```php
$t->map('title')->searchable();                        // a term anywhere
$t->string('name')->searchable(SearchMatch::PREFIX);   // a term at the start
```

Nothing changes at the call site. `matches()` and `startsWith()` read the private companion column
`<column>__search`, which the ORM writes in the same statement that writes the column it folds:

| engine | a term anywhere | a term at the start |
| --- | --- | --- |
| MySQL | `FULLTEXT` with the `ngram` parser, read with `MATCH ... AGAINST` | B-tree over the first 255 characters |
| PostgreSQL | GIN with `gin_trgm_ops`, read with `LIKE` | B-tree with `text_pattern_ops` |
| SQLite | no equivalent: a scan of the folded column | a plain index |

What it costs, said plainly:

- **The folded text is stored.** A searchable column roughly doubles the text the row holds, and the
  folded column is read with the row.
- **PostgreSQL needs `pg_trgm`.** The generated schema creates the extension beside the index, so a
  role that may not `CREATE EXTENSION` fails there, at the migration, naming it. `unaccent` is not
  needed: the folding is done in PHP, which is also why no index here depends on a rules file
  somebody can edit underneath it.
- **MySQL's `ngram_token_size` is server-wide** (default 2), needs a restart and an index rebuild.
  A term shorter than it is searched with `LIKE` over the folded column instead of through the
  index -- slower, never an empty answer. A server configured *higher* is the one case to know
  about: a term between the two sizes then finds nothing, so leave it at the default.
- **MySQL matches n-grams, not a literal substring**, so where the two would disagree the index
  decides. A term holding anything but letters, digits and single spaces is searched with `LIKE`
  instead, which is why a term with a `%` in it still means that character.
- **A JSON path is not served by the index.** `matches('p.page_title#fr', ...)` searches the path
  where it stands, because the folded column holds the whole column's text and cannot answer for
  one path of it.
- **A bulk update refuses what it cannot fold.** `updateAllItems()` writes the folded column in the
  same statement; handed a JSON patch or a SQL expression for a searchable column it refuses by
  name, rather than leaving the folded text behind and the search quietly wrong.

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
        $g->eq('u.user_role', 'admin')
                 ->or()
                 ->eq('u.user_role', 'superadmin');
    });
// (user_status = ? AND (user_role = ? OR user_role = ?))
```

### Grouped AND with a callable

```php
$filters->and(function (Filters $g) {
    $g->isNotNull('u.user_email')
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
- `matches()` and `startsWith()` bypass coercion too: the right operand is a search term and not a
  value of the column's type, so a date column does not turn the term `2026` into a timestamp.

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
`is_null`, `is_not_null`, `is_true`, `is_false`, `contains`, `has_key`, `matches`, `starts_with`.

Two conditions need `'AND'` or `'OR'` between them. A refused item is named by its position, in
the message and in the exception's public data (`getData()`):

```php
// [['kind', 'eq', 'a'], ['kind', 'eq', 'b']]
// DBALRuntimeException: item at [1]: expected "and" or "or" between two conditions, found "array".
// getData(): ['position' => [1]]

// ['is_active', 'eq', 'maybe']
// DBALRuntimeException: condition at [0] ("is_active" eq) refused: ...
// getData(): ['position' => [0], 'column' => 'is_active', 'operator' => 'eq',
//             'allowed_operators' => ['eq', 'neq', 'is_false', 'is_true']]
```

---

### Filters from a request

What a client sends (`ORMOptions` filters, as a REST request carries them) is applied with a
strict `FiltersTableScope`: a [private or sensitive column](./schema.md#private-and-sensitive-columns)
is refused, as are an `order_by` or a `cursor_column` on one
(`GOBL_ORM_REQUEST_INVALID_ORDER_BY`, `GOBL_ORM_REQUEST_INVALID_CURSOR_COLUMN`). A query's own
methods (`filterBy()`, the generated `where...()`) are server code and may use them. A scope of
your own can allow them with `allowPrivateColumnInFilters()` / `allowSensitiveColumnInFilters()`.

A request's filters that are refused throw an `ORMQueryException` ("Failed to apply filters to
query.") whose public data carries the refusal: `reason` (the message above), `position`, and for a
condition `column`, `operator` and `allowed_operators`.

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
