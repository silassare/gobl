### Unreleased (3.0.x-dev)

- `TypeDate::microseconds()` keeps what was set before it. It replaces the
  base type with a decimal one, and the new base knew nothing of the bounds and
  nullability set on the old: `min(10)->max(20)->microseconds()` still
  reported `min` and `max` but accepted any value. The bounds (with their
  messages) and nullability are set again on the new base, so the order of the
  calls no longer matters. A type built from an array was not affected
  (`configure()` applies the precision first).
- `ORMUniversalType::isValidValue()` checks `DECIMAL` and `BIGINT` with `$`
  meaning the very end (`D`): a trailing newline passed. A `BIGINT` element is
  now what a bigint column accepts (`TypeBigint::BIGINT_REG`): no leading zeros,
  an optional sign.
- A `bigint` is a **whole integer**. Its patterns were unanchored, so any value
  that merely contained digits matched: `1.5` and `1e5` were accepted and
  stored as they came, and an **unsigned** bigint accepted `-5`, which a strict
  MySQL then refused on insert (a 500). A value, and a bound or default a
  schema declares, must now be an integer written in digits, with an optional
  sign and no leading zeros.
- **`bigint` and `decimal` bounds are compared exactly.** Both sides went
  through `sprintf('%F', ...)` before `bccomp()`, which made floats of them and
  kept six places: a bigint `max` of `9007199254740992` accepted
  `9007199254740993`, and a decimal `min` of `0.0000005` accepted
  `0.0000001`. They are now written out as exact decimals, exponents included.
- An `int` **refuses a fractional value** instead of cutting it: `(int)` turned
  `3.9` into `3` without a word, and `-0.5` into `0`, which then passed the
  unsigned check. A whole value is still accepted however it is written (`3`,
  `3.0`, `1e3`).
- An **int-backed enum reads the text of an integer** (`"2"`), which is what an
  HTML form and a multipart body send; `::from()` wants an int, so it used to
  be refused and such an enum could only be submitted as JSON. Only a plain
  integer is read (`2.0`, ` 2`, `02` stay refused), and a string-backed enum is
  unchanged.
- The TypeScript bundle types an **enum column with its enum**. `ts-bundle`
  already generated `enums.ts`, but an entity typed such a column as a bare
  `string`: the enum class travels on the PHP type hint
  (`TypeEnum::getReadTypeHint()`), which only the PHP generator read. An
  entity now imports the enums its own columns use and types them with those,
  so a case added in PHP reaches the client on the next build instead of
  passing as any string.
- The TypeScript bundle declares a **row type per entity** (`EntityRow` beside
  `Entity`): its columns with the table prefix, which is what a REST answer
  and a form submission hold, while the entity exposes them unprefixed. It is
  derived from the entity with `GoblRowOf`, imported from gobl-utils-ts, so a
  generated bundle needs **gobl-utils-ts > 2.0.0**, the first release that
  exports it.
- `TypeString` no longer truncates in the middle of a character. `max` counts
  bytes, as the column's own limit does, but the cut used `substr()`, so a
  multi-byte character sitting on the boundary was split and the clean value
  was invalid UTF-8. `json_encode()` answers `false` for malformed UTF-8, so
  a single truncated value broke the encoding of the whole response carrying
  it, not merely that field. The cut is now `mb_strcut()`, which stays within
  the byte limit and never splits a character: a value simply loses one more
  character when the boundary falls inside one. Reachable by any `TypeString`
  with both `max()` and `truncate()`. **Gobl now requires `ext-mbstring`.**
- A `string` pattern must be portable: `TypeString::pattern()` (and the
  `pattern` option of a schema) refuses a pattern JavaScript would read
  differently or not at all (possessive quantifiers, `\A`, POSIX classes,
  flags other than `i`, `m`, `s`, `u`, ...), saying why and where, since a
  client checks the same pattern in the browser (the check itself lives in
  php-utils, `PHPUtils\PortablePattern`, which needs php-utils >= the release
  carrying it). A pattern now runs in
  Unicode mode, and `$` without `m` matches only at the very end of the
  value, not before a final newline: `"abc\n"` no longer matches `~^abc$~`,
  and a value that is not valid UTF-8 matches nothing.
- `ORMResults::getItems()` (and `lazy()`) no longer answers every row of a
  limited query. It reads in chunks, and each chunk replaced the `LIMIT` the
  query carried, so an offset page (`max`, `page`) returned the whole table;
  the chunks now stay inside the window the query asks for.
- `ORMEntity::save()` no longer validates the row the database gives back
  after an update: those values are not user input, and a validation that
  reads the database rejected the row's own stored values (changing a
  password failed with "email already registered")

### v3.0.0 (unreleased, since 2026-03-09)

Nothing was tagged after v1.5.0. The two entries below summarize what the
`2.0.x` and `3.0.x` lines added since then; from here on the changelog is
kept per change.

- SQLite and PostgreSQL drivers added next to MySQL, suggested by Composer
- lazy schema loading: a table is built on first use
- partial entity loading, batch loading of relations, cursor pagination
- `LinkJoin` relations (multi hop through pivot tables)
- schema files can be loaded and exported, with a JSON schema for editors
- types reworked: `listOf()`, `mapOf()` and `jsonOf()` column builders,
  typed revival, `medium` / `long` / `big` options for large values, date
  precision and min/max, backed enums, values coerced to their type
- metadata on tables and columns, merged lazily
- query logging (`QueriesLogger`)
- PHP 8.1 is the floor; classes are `final` where they are not meant to be
  extended, `#[Override]` is used throughout, Psalm runs on the sources

### v2.0.0 (unreleased, since 2022-12-01)

- the ORM rewritten around entities: `ORMEntity`, `ORMController`,
  `ORMResults`, `ORMTableQuery`
- fluent schema definition next to arrays: `NamespaceBuilder`,
  `TableBuilder`, `RelationBuilder`
- query builders `QBSelect`, `QBInsert`, `QBUpdate` and `QBDelete`, with
  multi insert, derived tables, `order by` and `limit` on update and
  delete, and lazy iteration of large result sets
- filters with a full operator set and type aware right operands
- schema diff and migrations: `MigrationMode`, `beforeRun()` /
  `afterRun()`, table and column renames
- CRUD rewritten: events per table and per action, private and sensitive
  columns
- relations: soft deletion, morph relations with morph types, virtual
  relations, lazy definition of foreign keys, indexes and relations
- `runInTransaction()` moved to `RDBMSInterface`

### v1.5.0 (2021-03-26)

- Dart class generator added
- TypeScript and ORM classes generator added
- Generator class is now abstract

### v1.4.1 (2020-01-09)

- fix foreign key table alter bug (MySQLGenerator)
- all alter are now moved after all table creation (MySQLGenerator)
- src/ORM/Sample directory moved to root

### v1.4.0 (2020-20-08)

- TS EntityBase class added
- TS using directory structure for entities classes
- bug fix and code optimization

### v1.3.1 (2020-29-03)

- using phpcs for linting

### v1.3.0 (2020-27-03)

- ORMFilters added
- DbConfig added
- Interface suffix consistency
- code clean up

### v1.2.0 (2020-20-03)

- ORMRequestBase bug fix
- ORMController db write/delete are now in transaction
- OZone service class bug fix

### v1.1.0 (2019-24-12)

- ORM optimized, bug fix

### v1.0.9 (2019-19-09)

- start using gobl-utils-ts

### v1.0.8 (2019-26-08)

- class names consistency
- code reorganized

### v1.0.7 (2019-15-03)

- ORM optimized to reduce generated code size

### v1.0.3 (2018-18-12)

- Transaction implemented

### v1.0.2 (2018-15-10)

- Some optimization

### v1.0.1 (2018-16-10)

- Some bug fixed
- CRUD rules added

### v1.0.0 (2017-10-08)

- First stable version of Gobl.
