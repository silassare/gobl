# Schema Definition

A Gobl schema is a plain PHP array.
Each top-level key is a **logical table name** (without prefix).

```php
return [
    'table_name' => [
        // = identity
        'singular_name' => 'item',   // used in generated class names
        'plural_name'   => 'items',
        'column_prefix' => 'item',   // prepended to every column name
        'namespace'     => 'app',    // optional, overrides Db::ns()

        // = columns
        'columns' => [ /* see Column Types */ ],

        // = constraints
        'constraints' => [ /* see below */ ],

        // = relations
        'relations' => [ /* see Relations */ ],

        // = arbitrary metadata (used by generators / the framework)
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
        'reference' => 'users',  // references the 'users' table
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

Gobl supports three ways to define a schema - all of them eventually call the same
`loadSchema()` pipeline, so they can be mixed freely.

### 1. PHP array (inline or from a file)

Pass the array directly, or `require` a PHP file that returns one:

```php
$db->ns('app')->schema(require 'config/schema.php');
```

### 2. Fluent builder

Use the `TableBuilder` API for a code-first, IDE-friendly definition:

```php
use Gobl\DBAL\Builders\TableBuilder;

$db->ns('app')->table('users', function (TableBuilder $t) {
    $t->id();
    $t->string('name');
    $t->timestamps();
    $t->meta('api.doc.description', 'A registered user account.');
});
```

See [Schema - Fluent builder](./schema.md) and [ORM](./orm.md) for the full API.

Use `useColumn($name)` to retrieve an already-defined column by its short name,
for example to set migration rename hints after the column has been created:

```php
$db->ns('app')->table('users', function (TableBuilder $t) {
    $t->string('email_address');
    $t->useColumn('email_address')->oldName('email'); // rename from 'email'
});
```

### 3. JSON file

A Gobl JSON schema file has the same structure as the PHP array schema. Load it with
`schemaFile()`, which also accepts `.php` files:

```php
// JSON
$db->ns('app')->schemaFile('/path/to/schema.json');

// PHP file returning an array (same as schema())
$db->ns('app')->schemaFile('/path/to/schema.php');
```

The file may include a `$schema` key (for IDE validation - see below) -
it is automatically stripped before loading.

#### IDE validation via `$schema`

JSON schema files can reference the [Gobl JSON Schema](./schema-editor.md) so that
editors like VS Code auto-complete and validate the file. Register the URL once at
application bootstrap:

```php
use Gobl\Gobl;

Gobl::setDefaultSchemaUrl('https://raw.githubusercontent.com/silassare/gobl/main/docs/public/schema.json');
```

Then export with `toSchemaJson()` to produce a file that includes the `$schema` key:

```php
// export - $schema key is prepended automatically
file_put_contents('config/schema.json', $db->toSchemaJson('app'));

// import - $schema key is stripped automatically
$db->ns('app')->schemaFile('config/schema.json');
```

Pass `null` to `setDefaultSchemaUrl()` to clear the default URL.

#### Exporting the current schema

`toSchemaArray()` returns the tables as a plain PHP array (no `$schema` key),
which you can pass directly back to `schema()`:

```php
$data = $db->toSchemaArray('app'); // all tables in namespace 'app'
$data = $db->toSchemaArray();      // all tables across all namespaces
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

::: warning Name uniqueness within a namespace
`Db::addTable()` enforces that table `name` and full name (name + DB prefix)
are unique — duplicate table names throw a `DBALException`.

However, `singular_name`, `plural_name`, and `column_prefix` uniqueness across
tables within a namespace is **not** enforced. Duplicate values will cause
class-name collisions in generated ORM code and ambiguous column look-ups.
Ensure they are distinct manually.
:::
