# Quick Start

This guide walks you from zero to a working CRUD application in five minutes.

## 1 - Define your schema

Create a file `config/schema.php`. Each key is a table name:

```php
<?php
// config/schema.php

return [
    'users' => [
        'singular_name' => 'user',
        'plural_name'   => 'users',
        'column_prefix' => 'user',   // every column will be prefixed: user_id, user_name ...
        'constraints'   => [
            ['type' => 'primary_key', 'columns' => ['id']],
            ['type' => 'unique_key',  'columns' => ['email']],
        ],
        'columns' => [
            'id' => [
                'type'           => 'bigint',
                'auto_increment' => true,
                'unsigned'       => true,
            ],
            'name' => [
                'type' => 'string',
                'min'  => 1,
                'max'  => 60,
            ],
            'email' => [
                'type' => 'string',
                'max'  => 255,
            ],
            'created_at' => [
                'type'   => 'date',
                'format' => 'timestamp',
                'auto'   => true,   // set on INSERT automatically
            ],
        ],
    ],

    'posts' => [
        'singular_name' => 'post',
        'plural_name'   => 'posts',
        'column_prefix' => 'post',
        'constraints'   => [
            ['type' => 'primary_key', 'columns' => ['id']],
            ['type' => 'foreign_key', 'reference' => 'users', 'columns' => ['author_id' => 'id']],
        ],
        'relations' => [
            'author' => ['type' => 'many-to-one', 'target' => 'users'],
        ],
        'columns' => [
            'id'        => ['type' => 'bigint', 'auto_increment' => true, 'unsigned' => true],
            'author_id' => 'ref:users.id',   // short-hand: inherit type from users.id
            'title'     => ['type' => 'string', 'max' => 128],
            'body'      => ['type' => 'string'],
            'published' => ['type' => 'bool', 'default' => false],
        ],
    ],
];
```

## 2 - Connect and build the database

```php
<?php
require 'vendor/autoload.php';

use Gobl\DBAL\Db;
use Gobl\DBAL\DbConfig;

// --- connect ---
// Note: the driver name goes to Db::newInstanceOf(), NOT inside DbConfig.
$config = new DbConfig([
    'db_host'         => '127.0.0.1',
    'db_port'         => 3306,
    'db_name'         => 'my_app',
    'db_user'         => 'root',
    'db_pass'         => 'secret',
    'db_charset'      => 'utf8mb4',
    'db_table_prefix' => 'app_',   // optional - prefixes all table names
]);

$db = Db::newInstanceOf('mysql', $config);

// --- load schema ---
$db->ns('app')
   ->schema(require 'config/schema.php');

// --- create tables (run once / in migrations) ---
$sql = $db->getGenerator()->buildDatabase('app');
$db->executeMulti($sql);
```

## 3 - Generate ORM classes

```php
use Gobl\ORM\Generators\CSGeneratorORM;

// Register the ORM namespace and output directory:
$db->ns('app')
   ->enableORM(__DIR__ . '/src/Db');

// Run code generation:
$generator = new CSGeneratorORM($db);
$generator->generate($db->getTables(), __DIR__ . '/src/Db');
// Creates:
//   src/Db/Base/UsersControllerBase.php  <- auto-generated, do not edit
//   src/Db/Base/UserBase.php             <- auto-generated, do not edit
//   src/Db/Base/UsersQueryBase.php       <- auto-generated, do not edit
//   src/Db/Base/UsersResultsBase.php     <- auto-generated, do not edit
//   src/Db/Base/UsersCrudBase.php        <- auto-generated, do not edit
//   src/Db/UsersController.php           <- your class, edit freely
//   src/Db/User.php                      <- your class, edit freely
//   ...same for Posts
```

::: tip Re-generation is safe
`Base/` classes are overwritten on every run. The top-level classes
(`UsersController.php`, `UsersEntity.php`) are created once and never
overwritten - add all custom logic there.
:::

## 4 - CRUD in three lines

```php
use Gobl\ORM\ORM;

$ctrl = ORM::ctrl($db->getTableOrFail('users'));

// Create
$user = $ctrl->addItem([
    'user_name'  => 'Alice',
    'user_email' => 'alice@example.com',
]);
echo $user->id;  // 1

// Read
$found = $ctrl->getItem(['user_email' => 'alice@example.com']);
echo $found->name;  // Alice

// Update
$ctrl->updateOneItem(['user_id' => $user->id], ['user_name' => 'Alice B.']);

// Delete
$ctrl->deleteOneItem(['user_id' => $user->id]);
```

## 5 - Raw query builder (no ORM)

```php
use Gobl\DBAL\Queries\QBSelect;

$qb = new QBSelect($db);

$qb->from('posts')
   ->cols(['post_id', 'post_title'])
   ->where(
       $qb->filters()
          ->eq('post_published', true)
          ->lt('post_author_id', 100)
   )
   ->orderBy([
	    'post_id DESC', // raw syntax
		'post_title' => 'ASC', // column => direction syntax
	    'post_published_at' => false, // falsy value treated as DESC
		'post_created_at' => true, // truthy value treated as ASC
   ])
   ->limit(10);

$stmt = $db->select($qb->getSqlQuery(), $qb->getBoundValues());
$rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
```

## Next steps

- [Schema Definition in depth](/guide/schema)
- [Column Types reference](/guide/column-types)
- [Query Builder API](/guide/query-builder)
- [ORM Controllers](/guide/controllers)
