### Unreleased (3.0.x-dev)

-   `TypeString` no longer truncates in the middle of a character. `max` counts
    bytes, as the column's own limit does, but the cut used `substr()`, so a
    multi-byte character sitting on the boundary was split and the clean value
    was invalid UTF-8. `json_encode()` answers `false` for malformed UTF-8, so
    a single truncated value broke the encoding of the whole response carrying
    it, not merely that field. The cut is now `mb_strcut()`, which stays within
    the byte limit and never splits a character: a value simply loses one more
    character when the boundary falls inside one. Reachable by any `TypeString`
    with both `max()` and `truncate()`. **Gobl now requires `ext-mbstring`.**
-   A `string` pattern must be portable: `TypeString::pattern()` (and the
    `pattern` option of a schema) refuses a pattern JavaScript would read
    differently or not at all (possessive quantifiers, `\A`, POSIX classes,
    flags other than `i`, `m`, `s`, `u`, ...), saying why and where, since a
    client checks the same pattern in the browser (the check itself lives in
    php-utils, `PHPUtils\PortablePattern`, which needs php-utils >= the release
    carrying it). A pattern now runs in
    Unicode mode, and `$` without `m` matches only at the very end of the
    value, not before a final newline: `"abc\n"` no longer matches `~^abc$~`,
    and a value that is not valid UTF-8 matches nothing.
-   `ORMResults::getItems()` (and `lazy()`) no longer answers every row of a
    limited query. It reads in chunks, and each chunk replaced the `LIMIT` the
    query carried, so an offset page (`max`, `page`) returned the whole table;
    the chunks now stay inside the window the query asks for.
-   `ORMEntity::save()` no longer validates the row the database gives back
    after an update: those values are not user input, and a validation that
    reads the database rejected the row's own stored values (changing a
    password failed with "email already registered")

### v3.0.0 (unreleased, since 2026-03-09)

Nothing was tagged after v1.5.0. The two entries below summarize what the
`2.0.x` and `3.0.x` lines added since then; from here on the changelog is
kept per change.

-   SQLite and PostgreSQL drivers added next to MySQL, suggested by Composer
-   lazy schema loading: a table is built on first use
-   partial entity loading, batch loading of relations, cursor pagination
-   `LinkJoin` relations (multi hop through pivot tables)
-   schema files can be loaded and exported, with a JSON schema for editors
-   types reworked: `listOf()`, `mapOf()` and `jsonOf()` column builders,
    typed revival, `medium` / `long` / `big` options for large values, date
    precision and min/max, backed enums, values coerced to their type
-   metadata on tables and columns, merged lazily
-   query logging (`QueriesLogger`)
-   PHP 8.1 is the floor; classes are `final` where they are not meant to be
    extended, `#[Override]` is used throughout, Psalm runs on the sources

### v2.0.0 (unreleased, since 2022-12-01)

-   the ORM rewritten around entities: `ORMEntity`, `ORMController`,
    `ORMResults`, `ORMTableQuery`
-   fluent schema definition next to arrays: `NamespaceBuilder`,
    `TableBuilder`, `RelationBuilder`
-   query builders `QBSelect`, `QBInsert`, `QBUpdate` and `QBDelete`, with
    multi insert, derived tables, `order by` and `limit` on update and
    delete, and lazy iteration of large result sets
-   filters with a full operator set and type aware right operands
-   schema diff and migrations: `MigrationMode`, `beforeRun()` /
    `afterRun()`, table and column renames
-   CRUD rewritten: events per table and per action, private and sensitive
    columns
-   relations: soft deletion, morph relations with morph types, virtual
    relations, lazy definition of foreign keys, indexes and relations
-   `runInTransaction()` moved to `RDBMSInterface`

### v1.5.0 (2021-03-26)

-   Dart class generator added
-   TypeScript and ORM classes generator added
-   Generator class is now abstract

### v1.4.1 (2020-01-09)

-   fix foreign key table alter bug (MySQLGenerator)
-   all alter are now moved after all table creation (MySQLGenerator)
-   src/ORM/Sample directory moved to root

### v1.4.0 (2020-20-08)

-   TS EntityBase class added
-   TS using directory structure for entities classes
-   bug fix and code optimization

### v1.3.1 (2020-29-03)

-   using phpcs for linting

### v1.3.0 (2020-27-03)

-   ORMFilters added
-   DbConfig added
-   Interface suffix consistency
-   code clean up

### v1.2.0 (2020-20-03)

-   ORMRequestBase bug fix
-   ORMController db write/delete are now in transaction
-   OZone service class bug fix

### v1.1.0 (2019-24-12)

-   ORM optimized, bug fix

### v1.0.9 (2019-19-09)

-   start using gobl-utils-ts

### v1.0.8 (2019-26-08)

-   class names consistency
-   code reorganized

### v1.0.7 (2019-15-03)

-   ORM optimized to reduce generated code size

### v1.0.3 (2018-18-12)

-   Transaction implemented

### v1.0.2 (2018-15-10)

-   Some optimization

### v1.0.1 (2018-16-10)

-   Some bug fixed
-   CRUD rules added

### v1.0.0 (2017-10-08)

-   First stable version of Gobl.
