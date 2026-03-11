# Schema Definition

A Gobl schema is a plain PHP array.
Each top-level key is a **logical table name** (without prefix).

```php
return [
    'table_name' => [
        // ── identity ───────────────────────────────────────────────────
        'singular_name' => 'item',       // used in generated class names
        'plural_name'   => 'items',
        'column_prefix' => 'item',       // prepended to every column name
        'namespace'     => 'app',        // optional, overrides Db::ns()

        // ── columns ────────────────────────────────────────────────────
        'columns' => [ /* see Column Types */ ],

        // ── constraints ────────────────────────────────────────────────
        'constraints' => [ /* see below */ ],

        // ── relations ──────────────────────────────────────────────────
        'relations' => [ /* see Relations */ ],

        // ── arbitrary metadata (used by generators / the framework) ────
        'meta' => [
            'api.doc.description' => 'A short description.',
        ],
    ],
];
```

## Columns

Column names are **logical** names - the `column_prefix` is prepended when
the name is written to the database. A column named `id` with prefix `user`
becomes `user_id` in SQL.

```php
'columns' => [
    'id' => [
        'type'           => 'bigint',
        'auto_increment' => true,
        'unsigned'       => true,
        'nullable'       => false,   // default false
    ],
    'email' => [
        'type' => 'string',
        'max'  => 255,
    ],
    // Short-hand: copy type from another column
    'author_id' => 'ref:users.id',
],
```

## Constraints

```php
'constraints' => [
    // Primary key
    ['type' => 'primary_key', 'columns' => ['id']],

    // Unique key
    ['type' => 'unique_key', 'columns' => ['email']],

    // Foreign key
    [
        'type'      => 'foreign_key',
        'reference' => 'users',        // references the 'users' table
        'columns'   => ['author_id' => 'id'],  // local_col => remote_col
    ],

    // Index
    ['type' => 'index', 'columns' => ['created_at']],
],
```

## Relations

Relations describe how tables connect. They inform code generators and the
relation controller - they do **not** create SQL constraints (those are set
in `constraints`).

```php
'relations' => [
    'author' => ['type' => 'many-to-one',  'target' => 'users'],
    'posts'  => ['type' => 'one-to-many',  'target' => 'posts'],
    'tags'   => [
        'type'   => 'many-to-many',
        'target' => 'tags',
        'link'   => [
            'type'         => 'through',
            'pivot_table'  => 'post_tags',  // pivot table with FK constraints to both sides
        ],
    ],
],
```

## Loading a schema

```php
$db->ns('app')
   ->schema(require 'config/schema.php');
```

Call `enableORM($outDir)` to also register the namespace for ORM code generation:

```php
$db->ns('app')
   ->schema(require 'config/schema.php')
   ->enableORM(__DIR__ . '/generated');
```

## Multiple namespaces

Large applications can split their schema across namespaces:

```php
$db->ns('billing')->schema(require 'config/billing.php')->enableORM($outDir);
$db->ns('content')->schema(require 'config/content.php')->enableORM($outDir);
```

Each namespace gets its own set of generated classes.

::: warning Column prefix uniqueness
Gobl does not enforce globally-unique prefixes, but the ORM generates class
names from the `singular_name`. Use distinct singular names across namespaces
to avoid class-name collisions.
:::
