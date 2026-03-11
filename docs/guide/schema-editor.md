# Schema Editor

Write and validate a Gobl schema definition directly in the browser.
The editor validates your JSON against the [Gobl JSON Schema](/schema.json)
and shows inline hints for column types, constraint options, and relation fields.

::: info JSON vs PHP arrays
Gobl schemas are defined as **PHP arrays** (see [Schema Definition](./schema.md)).
This editor uses **JSON** - the structure is identical. Copy the validated JSON
and convert it to a PHP array with `json_decode($json, true)` or by hand.
:::

<ClientOnly>
  <SchemaEditor />
</ClientOnly>

## Using schema.json in VS Code

Add a `$schema` reference to any JSON schema file in your project to get
full auto-complete and validation in VS Code:

```json
{
	"$schema": "https://raw.githubusercontent.com/silassare/gobl/main/docs/public/schema.json",
	"users": {
		"singular_name": "user",
		"columns": {
			"id": { "type": "bigint", "unsigned": true, "auto_increment": true }
		},
		"constraints": [{ "type": "primary_key", "columns": ["id"] }]
	}
}
```

Or configure VS Code globally by adding to `.vscode/settings.json`:

```json
{
	"json.schemas": [
		{
			"fileMatch": ["**/schema/*.json", "**/tables/*.json"],
			"url": "https://raw.githubusercontent.com/silassare/gobl/main/docs/public/schema.json"
		}
	]
}
```

## Loading a JSON schema in PHP

```php
$schema = json_decode(file_get_contents(__DIR__ . '/schema.json'), true);

$db->ns('App\\Models')
   ->schema($schema)
   ->enableORM(__DIR__ . '/generated');
```

## Schema reference

For a full description of every option see [Column Types](./column-types.md)
and [Schema Definition](./schema.md).

| Section        | Keys                                                                                                                                      |
| -------------- | ----------------------------------------------------------------------------------------------------------------------------------------- |
| **Table**      | `singular_name`, `plural_name`, `column_prefix`, `prefix`, `namespace`, `charset`, `collate`, `private`, `meta`, `morph_type`, `diff_key` |
| **Column**     | `type`, `nullable`, `auto_increment`, `default`, `prefix`, `private`, `sensitive`, `meta`, ... + type-specific options                    |
| **Constraint** | `type` (`primary_key` / `unique_key` / `foreign_key`), `columns`, `reference`, `update`, `delete`                                         |
| **Relation**   | `type` (`one-to-one` / `one-to-many` / `many-to-one` / `many-to-many`), `target`, `link`                                                  |
| **Index**      | `columns`, `type` (`BTREE`, `HASH`, `MYSQL_FULLTEXT`, `PGSQL_GIN`, ...)                                                                   |
