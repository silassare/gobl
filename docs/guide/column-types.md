# Column Types

Every column in a Gobl schema requires a `type` key. The remaining keys
depend on the type. All types share a set of **universal options**.

---

## Universal options (all types)

| Option           | PHP type | Default  | Description                                                                                    |
| ---------------- | -------- | -------- | ---------------------------------------------------------------------------------------------- |
| `nullable`       | `bool`   | `false`  | Accept `null` as a valid value                                                                 |
| `auto_increment` | `bool`   | `false`  | Column is auto-incremented by the RDBMS                                                        |
| `default`        | mixed    | _(none)_ | Default value used when `null` is supplied                                                     |
| `validator:pre`  | `string` | _(none)_ | FQCN of a `TypeValidatorInterface` class; runs before the core validation                      |
| `validator:post` | `string` | _(none)_ | FQCN of a `TypeValidatorInterface` class; runs after core validation (always, even on failure) |

---

## `bigint`

Stores a 64-bit integer as a string (preserves values beyond PHP's `int` range).

| Option     | Type          | Default  | Description            |
| ---------- | ------------- | -------- | ---------------------- |
| `unsigned` | `bool`        | `false`  | Reject negative values |
| `min`      | `int\|string` | _(none)_ | Minimum allowed value  |
| `max`      | `int\|string` | _(none)_ | Maximum allowed value  |

```php
'user_id' => [
    'type'           => 'bigint',
    'unsigned'       => true,
    'auto_increment' => true,
]
```

---

## `int`

Stores a 32-bit integer.

| Option     | Type   | Default  | Description                                     |
| ---------- | ------ | -------- | ----------------------------------------------- |
| `unsigned` | `bool` | `false`  | Reject negative values (range: 0-4 294 967 295) |
| `min`      | `int`  | _(none)_ | Minimum (must be within signed/unsigned range)  |
| `max`      | `int`  | _(none)_ | Maximum (must be within signed/unsigned range)  |

Signed range: -2 147 483 648 ... 2 147 483 647.

```php
'post_score' => ['type' => 'int', 'unsigned' => true, 'default' => 0]
```

---

## `float`

Stores a floating-point number.

| Option     | Type    | Default  | Description                            |
| ---------- | ------- | -------- | -------------------------------------- |
| `unsigned` | `bool`  | `false`  | Reject negative values                 |
| `min`      | `float` | _(none)_ | Minimum value                          |
| `max`      | `float` | _(none)_ | Maximum value                          |
| `mantissa` | `int`   | _(none)_ | Digits after the decimal (schema hint) |

```php
'user_rating' => ['type' => 'float', 'unsigned' => true, 'min' => 0.0, 'max' => 5.0]
```

---

## `decimal`

Stores an exact decimal number as a string (preserves precision).

| Option      | Type            | Default  | Description                                              |
| ----------- | --------------- | -------- | -------------------------------------------------------- |
| `unsigned`  | `bool`          | `false`  | Reject negative values                                   |
| `min`       | `float\|string` | _(none)_ | Minimum value                                            |
| `max`       | `float\|string` | _(none)_ | Maximum value                                            |
| `precision` | `int`           | _(none)_ | Total significant digits (>= 1)                          |
| `scale`     | `int`           | _(none)_ | Digits after the decimal point (0 <= scale <= precision) |

```php
'product_price' => [
    'type'      => 'decimal',
    'unsigned'  => true,
    'precision' => 10,
    'scale'     => 2,
]
```

---

## `bool`

Stores `0` or `1`. Returns `bool` in PHP.

| Option   | Type   | Default | Description                                                                                                                                      |
| -------- | ------ | ------- | ------------------------------------------------------------------------------------------------------------------------------------------------ |
| `strict` | `bool` | `true`  | When `true`: only `true`, `false`, `1`, `0` accepted. When `false`: also accepts `'yes'/'no'`, `'on'/'off'`, `'y'/'n'`, `'true'/'false'` strings |

```php
'is_active'  => ['type' => 'bool', 'default' => true]
'is_deleted' => ['type' => 'bool', 'strict' => false, 'nullable' => true]
```

---

## `string`

Stores a text value.

| Option      | Type       | Default  | Description                                              |
| ----------- | ---------- | -------- | -------------------------------------------------------- |
| `min`       | `int`      | _(none)_ | Minimum byte length (`strlen`)                           |
| `max`       | `int`      | _(none)_ | Maximum byte length                                      |
| `pattern`   | `string`   | _(none)_ | Regex the value must match (`preg_match`)                |
| `one_of`    | `string[]` | `[]`     | Allowlist of exact values                                |
| `truncate`  | `bool`     | `false`  | Silently truncate to `max` instead of raising an error   |
| `multiline` | `bool`     | `false`  | When `false`: collapse whitespace runs to a single space |
| `trim`      | `bool`     | `false`  | Trim leading/trailing whitespace before validation       |
| `medium`    | `bool`     | `false`  | Schema hint: use `MEDIUMTEXT` in MySQL                   |
| `long`      | `bool`     | `false`  | Schema hint: use `LONGTEXT` in MySQL                     |

```php
'user_name'   => ['type' => 'string', 'min' => 1, 'max' => 60]
'user_gender' => ['type' => 'string', 'one_of' => ['male', 'female', 'unknown']]
'user_bio'    => ['type' => 'string', 'multiline' => true, 'long' => true, 'nullable' => true]
```

---

## `enum`

Stores a PHP 8.1+ `BackedEnum` value. The underlying storage uses `TypeString(0, 128)`.

| Option       | Type     | Default      | Description                                  |
| ------------ | -------- | ------------ | -------------------------------------------- |
| `enum_class` | `string` | _(required)_ | Fully-qualified name of a `BackedEnum` class |

```php
// PHP enum:
enum Status: string {
    case Active   = 'active';
    case Inactive = 'inactive';
    case Banned   = 'banned';
}

// Schema:
'user_status' => [
    'type'       => 'enum',
    'enum_class' => Status::class,
    'default'    => Status::Active->value,
]
```

---

## `date`

Stores dates as unix timestamps internally (avoids Y2038 via 64-bit bigint or decimal).
Returns a formatted string or raw timestamp in PHP.

| Option      | Type          | Default     | Description                                                                             |
| ----------- | ------------- | ----------- | --------------------------------------------------------------------------------------- |
| `auto`      | `bool`        | `false`     | Use current time when value is empty/null on write                                      |
| `format`    | `string`      | `DATE_ATOM` | PHP `date()` format string, or `'timestamp'` to return the raw unix timestamp           |
| `min`       | `int\|string` | _(none)_    | Minimum date (any `strtotime`-parseable string or timestamp)                            |
| `max`       | `int\|string` | _(none)_    | Maximum date                                                                            |
| `precision` | `string`      | _(none)_    | Set to `'microseconds'` to store sub-second precision (uses `decimal(20,6)` internally) |

```php
'created_at' => ['type' => 'date', 'auto' => true, 'format' => 'timestamp']
'updated_at' => ['type' => 'date', 'auto' => true, 'format' => DATE_ATOM]
'birth_date' => ['type' => 'date', 'nullable' => true, 'format' => 'Y-m-d']
```

---

## `map`

Stores an associative array (JSON object) in the database. Returns a `Map` wrapper in PHP.

| Option        | Type   | Default | Description                                                                                        |
| ------------- | ------ | ------- | -------------------------------------------------------------------------------------------------- |
| `native_json` | `bool` | `false` | Use the native `JSON` column type (MySQL >= 5.7, PostgreSQL). Also enables `JSON_CONTAINS` filter. |
| `big`         | `bool` | `false` | Hint: use `MEDIUMTEXT` in MySQL when `native_json=false`                                           |

```php
'user_meta'     => ['type' => 'map', 'default' => [], 'nullable' => true]
'user_settings' => ['type' => 'map', 'native_json' => true]
```

---

## `list`

Stores a sequential array (JSON array). Re-indexes with `array_values()` on write.
Returns `array` in PHP.

| Option        | Type     | Default  | Description                                                                                                                                                                   |
| ------------- | -------- | -------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `native_json` | `bool`   | `false`  | Use native `JSON` column type. Also enables `JSON_CONTAINS` filter.                                                                                                           |
| `big`         | `bool`   | `false`  | Hint: use `MEDIUMTEXT` in MySQL when `native_json=false`                                                                                                                      |
| `list_of`     | `string` | _(none)_ | FQCN implementing `JsonOfInterface` - each element is revived via `::revive()` on read. Or an `ORMUniversalType` enum name (e.g. `'STRING'`) for TS/Dart code-gen type hints. |

```php
'user_tags' => ['type' => 'list', 'default' => [], 'nullable' => true]
'items'     => ['type' => 'list', 'list_of' => MyItem::class]  // elements revived as MyItem
```

---

## `json`

Stores any JSON-serialisable PHP value (scalars, arrays, objects). Base type for `map` and `list`.
`phpToDb()` runs `json_encode()` and `dbToPhp()` runs `json_decode()`, so the decoded PHP value is returned on read.
When `json_of` is set, shape or type constraints are applied (see option table).

| Option        | Type     | Default  | Description                                                                                                                                                                                                                                                                                     |
| ------------- | -------- | -------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `native_json` | `bool`   | `false`  | Use native `JSON` column type                                                                                                                                                                                                                                                                   |
| `big`         | `bool`   | `false`  | Hint: use `MEDIUMTEXT` in MySQL                                                                                                                                                                                                                                                                 |
| `json_of`     | `string` | _(none)_ | FQCN implementing `JsonOfInterface` - decoded value is revived via `MyClass::revive($decoded)` on read. Or an `ORMUniversalType` enum name (e.g. `'MAP'`, `'LIST'`) for shape enforcement + code-generation hint: `LIST` accepts only sequential arrays, `MAP` only associative arrays/objects. |

```php
'raw_payload' => ['type' => 'json', 'nullable' => true]
'event'       => ['type' => 'json', 'json_of' => MyEvent::class]  // revived as MyEvent on read
'meta'        => ['type' => 'json', 'json_of' => 'MAP']           // decoded array, MAP hint in TS/Dart
```

---

## Column reference shorthand

When a column's type should be derived from another table's column (e.g. a
foreign key), use `ref:table.column` or `cp:table.column`:

```php
// ref: inherits type from users.id - auto_increment is NOT carried over
'author_id' => 'ref:users.id'

// cp: inherits type from users.id - auto_increment IS carried over
'author_id' => 'cp:users.id'

// With extra overrides merged in (works with both ref: and cp:):
'author_id' => ['type' => 'ref:users.id', 'nullable' => true]
```

Both shorthands resolve the target column's type options at schema-loading time
and create an independent column. The only difference is what is carried over:

|                                | `ref:`            | `cp:`         |
| ------------------------------ | ----------------- | ------------- |
| Type (`bigint`, `string`, ...) | yes               | yes           |
| `unsigned`, `min`, `max`, ...  | yes               | yes           |
| `auto_increment`               | **no** (stripped) | yes           |
| `diff_key`                     | no (stripped)     | no (stripped) |

Use `ref:` for foreign key columns (they should not auto-increment).
Use `cp:` only when the target column's `auto_increment` should be preserved.
