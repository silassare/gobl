### Unreleased (3.0.x-dev)

- **A JSON column's patch never reached the database as paths.** `JsonPatch` exists so an UPDATE
  sends the paths a request changed and the engine applies them to the row it holds, which is what
  keeps two requests writing different keys of one document from undoing each other. On the ORM path
  it never happened: `Table::doPhpToDbConversion()` ran `phpToDb()` over every value on its way to
  the query builder, and for a patch that answers the **patched document** -- so the paths were gone
  before the builder could express them, and every save wrote the whole document from the copy the
  request had read. A patch is passed through untouched now, and the builder encodes the document
  itself in the one case it writes one.

  The tests did not catch it because `ORMController::updateOneItem()` re-reads the row at save time
  and applies the patch to what it finds, which narrows the race to the gap between that read and
  the UPDATE: wide enough to lose writes under real concurrency, narrow enough that a test holding a
  request open for a second and a half still passed. The patch integration suite now runs against
  all three engines with the paths genuinely in the SQL, which is how the two bugs below surfaced.

- **An UPDATE wrote every column of the row, not the ones that changed.**
  `ORMController::persistItem()` built its UPDATE from `$entity->toRow()`, so a save carried every
  column as the entity had read it and undid a concurrent write to any other column -- a sign-in
  landing on a session another request was saving, for instance. It writes
  `ORMEntity::toRowChanges()`: the dirty columns, a patched one as its paths. `ORMEntity::save()`
  computed the same list inline and now shares it.

- **An object member whose name is a number can be patched.** A path segment of all digits was
  refused outright, so one numeric key anywhere in a document sent the whole thing -- reachable from
  ordinary request data, a page editor keeping blocks under their numeric ids among it. The two
  readings are now told apart: a **string** segment is an object member (`$.blocks."12"`), an **int**
  segment is an array entry (`$.tags[0]`), and `set()` / `remove()` read a digit segment off the
  document -- a member when a map is already there, an entry when what is there is missing, empty or
  a list, which is the reading under which the SQL and `toArray()` agree. The container above a path
  is made of the kind that path addresses, because an engine asked for a member of an array writes
  nothing and does not say so. **Breaking**: `set('tags.0', v)` addressed nothing before and
  addresses an entry now; a caller that means a member named `0` says so with `setAt(['tags', '0'], v)`.

- **A patch that cannot be sent as paths says so.** Falling back to the whole document gives back
  the lost update the paths exist to prevent, and it was silent. The generator answers a
  `JsonPatchExpression` carrying the reason, the fallback dispatches `JsonPatchNotExpressible` with
  the column and the paths, and a column declared `patchOnly()` raises instead -- for a column
  several requests write at once, each owning part of it. A patch-only column also refuses a whole
  value in an UPDATE, since that is the same lost update by another route.

- **A whole value written before a patch of the same column was dropped.** `_oebWrite()` seeded the
  pending patch from the row it had just modified, so `setData(['a' => 1])` then
  `setDataKey('b', 2)` sent only `$.b` and the row never got `a`: the entity read back the right
  document and the database held another. The column is written whole when a value preceded a patch.

- **Two engine-specific path bugs, found once the paths were really being sent.** SQLite's
  `json_extract()` unwraps a scalar into plain text, so reading the type of a string member asked
  `json_type()` to parse `a string` and it refused the statement as malformed JSON; SQLite reads the
  type at the path instead (`json_type(X, P)`). On PostgreSQL, `#>` reads a `jsonb` and nothing
  else, so a column storing JSON as text raised `operator does not exist: text #> text[]`; the
  operand is cast.

- **The suite fails on a deprecation, and the three answers to an unknown column are one.** The
  PHPUnit config carried `failOnWarning` alone, so a deprecation, a notice or a risky test passed
  unseen: PHP 8.5 deprecates a null array offset in every form (`isset()`, `??` and `empty()`
  included), which is exactly the kind of thing a suite has to fail on to find. The gate is on
  (`failOnDeprecation`, `failOnNotice`, `failOnRisky`, with the details displayed), and the full
  suite passes it against live MySQL, PostgreSQL and SQLite with no deprecation.

  The one notice it did find was the entity's non-strict answer to an unknown column, raised by
  `__get()`, `__set()` and `_oebHydrate()` from three copies of the same message and the same
  strict / non-strict choice. The three now call one private `_oebUnknownColumn()`, and each of
  them has a test that asserts the notice it raises; before, only the read path was covered, and it
  asserted the returned `null` while letting the notice through.

- **A table with a single column reached TypeScript and Dart with no column prefix.** The client
  generators took the prefix every column of a table shares by reading one of them with
  `next($inject['columns'])`, which answers the **second** entry. With two columns or more the
  prefix is the same either way, so only a single-column table was affected: `columns_prefix` was
  never set and the templates wrote `PREFIX = ''`, which in TypeScript also types the row as
  `GoblRowOf<..., ''>`. They read the first column (`array_first()`), with a test per generator.

- **A `json` column holding something that is not JSON now raises instead of reading as `null`.**
  `TypeJson::dbToPhp()` decoded without `JSON_THROW_ON_ERROR` while `map` and `list` decoded with
  it, so a broken row read as an empty one, and a `json_of` class was handed the `null`. It raises
  `JsonException`; `null` and an empty string still read as `null`, which is what an empty column
  holds. **Breaking** for code that relied on the silent `null`.

- **A schema file that cannot be read says so.** `NamespaceBuilder::schemaFile()` passed
  `file_get_contents()` straight into `json_decode()`, so a file that exists and fails to read gave
  `false` to a string parameter and a `TypeError` under `strict_types`. The read is checked, and the
  decode uses `JSON_THROW_ON_ERROR` like the rest of the codebase instead of inspecting
  `json_last_error()` afterwards. `TypeEnum` reads the first case with `array_first()` rather than
  moving the array's internal pointer with `reset()`.

- **The 8.5 syntax, written by the shared coding standard.** The floor moved to PHP 8.5 and the
  fixer carried no version migration set, so each file kept the syntax of the year it was written.
  The standard now applies the 8.5 migration: `$x::class` over `get_class($x)`, `0o755` over
  `0755`, an arrow function over a closure that only returns, a heredoc indented with its code.
  Fourteen files here.

- **Ten crash paths where the code contradicted its own docblock.** The static analysis levels are
  now one shared file every Oliup package includes, instead of a set each package kept its own copy
  of and edited separately; the strictest of those sets was in use in two packages only, and
  adopting it here surfaced fourteen places where a value that can be null was dereferenced
  anyway. Two were plain contradictions of what was written above them:

  - `Table::hasSinglePKColumn()` documented `false` for a table with no primary key and raised a
    `TypeError` instead. It answers `false`.
  - `Table::getSinglePKColumnOrFail()` documented a throw when there is no primary key constraint
    and raised a `TypeError` instead. It throws `GoblRuntimeException`, as it says.

  The rest replace a fatal with a diagnostic that names what was wrong: an unbuildable table in
  `Db`'s lazy stage (which an `@var` had asserted rather than checked), a table or alias a filter
  operand names and the query does not, a SQLite `DELETE` with a `LIMIT` and no table, and a
  generated enum whose column is attached to no table. `Relation::toArray()` builds its array
  before writing to it.

  **Breaking.** `ORMTableQuery`'s batch identity key asked the host table's primary key constraint
  for its first column. On a composite primary key that column alone does not identify a row, so
  the grouping was wrong and silent; it now throws. A batch relation on a composite-keyed host
  table needs its key named explicitly.

- **The test suite declares its metadata in attributes.** PHPUnit 13 does not read doc-comment
  metadata and does not warn about it, so a left-behind `@dataProvider` makes a test run with no
  arguments rather than fail. Every `@dataProvider`, `@covers` and `@group` is an attribute, and
  `BaseTestCase::allDrivers()` returns the one argument its docblock always claimed -- it returned
  two, and a hundred and one tests took one and silently discarded the other. The test that wants
  the driver class has `allDriversWithClass()`.

- **A generated column is a typed property, not a docblock line.** Per column the generator wrote
  an `@property` line and two accessors, and both ends of the pair went through `__get` / `__set`:
  the column's type was something a comment claimed and nothing enforced. Each column is now a
  **virtual property with hooks** on the generated base class, typed with the column's read type
  and taking its write type where that is wider (`array|Map` over `Map`), so a wrong assignment is
  a `TypeError` at the call site. `get<Col>()` and `set<Col>()` keep their signatures and the
  setter stays fluent; the `@property` lines are gone, and an element type a PHP type cannot carry
  (`Map<\App\Thing>`) is in the property's `@var`. Both hooks reach the same code the magic
  accessors reach (`_oebRead()`, `_oebWrite()`), which the magic accessors now call too, so
  there is one implementation of a column read and one of a column write. The magic methods stay
  for the **full** column name and for anything with no generated property.

  **Breaking, at the call site.** PHP runs the `get` hook where it used to call `__isset()`, so
  `isset($entity->col)` answers a question about the value instead of about the schema:

  - `isset()` on a nullable column holding null is **false** where it was true, and `??` reaches
    its fallback where it answered null. `$table->hasColumn()` is the schema question.
  - `isset()` on a **partially loaded** entity **throws**: `isset()` runs the hook and cannot opt
    out of its exception. `isColumnLoaded()` is the guard.
  - `unset($entity->col)` is an **`Error`**, where it was a silent no-op (`ORMEntity` declares no
    `__unset`).
  - a column name appears in `get_object_vars()`, `var_dump()` and `json_encode()`, where it used
    not to. `toArray()` is still what answers with an entity's values: it redacts sensitive
    columns and filters a partial projection, which none of those three do.
  - assigning a DB-form value to a short column name through `hydrate()` is type-checked now, as
    any property write is; a row from the database belongs on `_oebHydrate()`.

  **The generated classes must be rebuilt** (`oz db build --class-only`). Needs
  `oliup/code-generator` with property-hook support on `PHPProperty`.

- **A row from the database no longer reaches the entity through its properties.** `ORMResults`
  let PDO write them (`PDO::FETCH_INTO`, `PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE`), which worked
  only because a generated property carried the column's **short** name while PDO delivers its
  **full** one. `column_prefix` defaults to empty, though, and then the two are the same word -- so
  on a table without a prefix PDO wrote straight into the hooks above, with the value still in its
  DB form: a `TypeError` on a JSON column, whose raw value is a string, and PHP's own coercion
  instead of `Type::dbToPhp()` on the others, with no hash snapshot taken. `fetchClass()` and
  `fetchAllClass()` now read `PDO::FETCH_ASSOC` and place the row with
  `ORMEntity::_oebHydrate()`, which does what `__set`'s PDO branch did and routes a `_gobl_*`
  computed alias to its slot. Hydration is explicit, it is the same on a prefixed table as on one
  without a prefix, and it costs one magic dispatch less per column on every row read. `__set`
  keeps its computed-alias mode and its column mode; it no longer has a PDO mode.

- **A column named in two characters or fewer is a column again.** A left
  operand was only read as a field notation when it was longer than two
  characters, which made the key of a table that declares no column prefix --
  named `id` -- a value rather than a column. Unscoped that produced an
  unqualified reference; under a `FiltersScopeInterface` it was a refusal, "No
  resolved column for this operand", and `save()` filters on the key, so an
  entity of such a table could be inserted and never updated. A bare column
  name is a notation of its own and no length separates one from a value: what
  the guard excludes now is a string that is already a binding (`:name`) or a
  sub-query the caller built. Right operands are unchanged -- a plain string
  there is still never resolved as a column.

- **Every class constant a value types beyond doubt is typed** (`private const
  array`, `public const string`, ...), which PHP 8.3 allows: a constant says
  what it is, and a subclass redeclaring it as something else is refused. The
  generator types the constants it writes too (`TABLE_NAME`, `TABLE_NAMESPACE`,
  `COL_*` as `string`, `FILTER_METHODS` as `array`), which needs
  `oliup/code-generator` with a type on `PHPConstant`. **The generated classes
  must be rebuilt** (`oz db build --class-only`): a query class generated before
  this redeclares `FILTER_METHODS` without a type, and PHP refuses that against
  the typed one it inherits. The
  `VERSION` file says `gobl v3.0.0`, the version the `dev-main` branch alias has
  carried for a while and the one `GOBL_VERSION` stamps into every generated
  file's header -- it still said v2. **Not a release**: no tag is cut and this
  section stays `Unreleased`.

- **PHP 8.5 is the minimum** (`"php": ">=8.5"`), the version OZone requires, so
  that every package of the suite answers for one floor. A branch below it
  cannot satisfy the advisory database `roave/security-advisories` carries
  either: 8.1 and 8.2 are end of life and their security fixes are source
  backports rather than releases, and for the live branches the advisory set
  asks for a patch level (8.5.12) above what an older branch has -- so the
  dependencies of a run on one cannot be resolved at all.
  `config.platform.php` (8.5.12), the PHPCompatibility target (`testVersion`)
  and the CI matrix say 8.5 with it, the matrix now being a single version
  since 8.6 is not out.

- **An UPDATE sends a JSON patch as the paths it changed.** A `JsonPatch`
  assigned to a column (`$entity->setDataKey(...)`, `patchData()`, or the
  object itself) was flattened to a document and written whole, so two
  transactions patching different keys of one column overwrote each other:
  each wrote every key as it was when it read the row, and whichever committed
  last won. A patch now keeps its operations, and an UPDATE asks the database
  to apply them to the row it holds -- `JSON_SET` / `JSON_REMOVE` on MySQL,
  `json_set()` / `json_remove()` on SQLite, `jsonb_set()` / `#-` on
  PostgreSQL, each over the column itself, with the objects above a written
  path made from the column's own value. Writes to different paths of one
  column no longer undo each other; writes to the same path are still the last
  one in. An INSERT writes the whole document as before (there is no row to
  patch), and so does an UPDATE whose patch cannot be expressed as paths: an
  empty path, a segment that reads as an array index (which a JSON path
  expression cannot tell from a member named `0`), two paths where one is
  inside the other, or a value that does not encode as JSON. The document the
  entity holds is the patch applied to the value it held, so it reads back what
  the row will hold; MySQL and PostgreSQL keep an object's members in an order
  of their own, so a patched document may come back with its keys in another
  order. `JsonPatch` gains `setAt()` / `removeAt()` (a path as segments, for a
  key holding a dot or a bracket), `getOperations()`, `hasOperations()` and
  `append()`, and `set()` now accepts a `bool` value -- so does the generated
  `set<Column>Key()`, whose signature rejected one under `strict_types` (a flag
  is among the first things a document holds); the generated classes need
  rebuilding for it. `ORMEntity::toRowPatches()` is what the controller writes
  them from.
- **A cast is no longer read as a named parameter.**
  `NamedToPositionalParams` matched `:(\w+)` anywhere, so the second colon of
  a PostgreSQL cast (`(data)::jsonb`) was taken for a parameter named after the
  type and the conversion threw "Missing query token". Only SQLite converts to
  positional parameters today, so nothing reached it; a cast in a converted
  query now stays a cast.

- **A migration file no longer needs the enum classes it names.** An enum
  column's default was written by `var_export()` as `\App\Enum::CASE`, an
  expression that needs the class to load the file, and `TypeEnum` refused a
  class that does not exist: deleting an enum broke every migration naming it.
  `TypeEnum::toArray()` now writes the default as its backing value and freezes
  the cases (`enum_cases`, name => value, and `enum_backing`). When the class is
  gone, a type given frozen cases loads detached (`TypeEnum::isDetached()`):
  it validates against them and reads and writes backing values, and the
  generators type the column with those. While the class exists, a default
  given as its value (as a migration gives it) is the enum case
  (`TypeEnum::getDefault()`): a default is trusted, not validated, so a new
  entity would otherwise hold the bare value. `Diff::generateMigrationFile()`
  refuses a schema holding anything but scalars and arrays
  (`MigrationSchema::assertPureData()`). `TypeEnum::dbToPhp()`,
  `phpToDb()` and `toEnumValue()` may now return a backing value (detached
  only). Migration files written before keep the old default and still need
  their enum classes.
- **The generated TypeScript bundle exports the row types.** `index.ts`
  exported the entity classes and the enums only, while a REST answer is made
  of rows: every `<Entity>Row` (`GoblRowOf<...>`) declared in a base file is
  now exported from `index.ts` too (`export type { ClientRow } from
'./db/base/ClientBase'`).
- **The generation time can be left out of generated files.**
  `Gobl::enableGeneratedAtDate(false)` drops the `Time:` line from the headers
  of the PHP, TypeScript and Dart classes and of the SQL scripts, so that a
  file generated again from an unchanged schema is byte-identical and a
  regeneration only rewrites what changed. The `Proudly With:` version line
  stays. `Gobl::getGeneratedAtDate()` now returns `?string`: `null` while
  disabled.
- **A refused flat filter says which item and why.** `Filters::fromArray()`
  names the position of the refused item (`item at [1]`, `condition at [0][2]`)
  and the reason in its message, and puts `position` (a list of indexes), and
  for a condition its `column`, `operator` and, when the column resolves, the
  `allowed_operators` of its type, in the exception's public data. A condition
  that `add()` refuses (a value the column's type refuses, a column the scope
  refuses) is wrapped the same way, the cause as its previous. The
  `ORMQueryException` of a request's filters ("Failed to apply filters to
  query.") carries that message as `reason`, with the same public data, so a
  REST client is told what to fix. Two conditions with no `and` / `or` between
  them are still refused, now saying so. The typo "will expecting" is gone
  with the old messages.
- **A boolean column takes `1`, `0`, `true` and `false` given as strings in a
  filter**, whatever its strictness (`TypeBool::castValueForFilter()`): a
  filter from a query string is always a string, and `['is_active', 'eq', '1']`
  was refused by a strict column.

- **A generated query, controller or results class may be the first one
  loaded.** Their constructors, and the CRUD class's, read the entity's
  `TABLE_NAMESPACE` and `TABLE_NAME`, which loaded the entity. A framework that
  declares the ORM namespace from its autoloader and constructs the CRUD classes
  then (OZone, registering its listeners) did so while the first class was still
  loading, and the entity's `qb()`, `ctrl()` or `results()` names that class: PHP
  stopped with a compile error no handler can catch ("Could not check
  compatibility between ...Base::qb() ... because class ...Query is not
  available"). The four constructors now write the namespace and the table name
  out. Regenerate the `Base/` classes to get it.

- **An entity's own `save()` may write its private and sensitive columns.** It
  goes through the controller, whose CRUD checks refused such a column unless a
  listener allowed it, so server code could not save an entity's own data, and
  with `softDeletable()` making `deleted` private no new row of a
  soft-deletable table could be saved. `save()` now runs its write through
  `CRUD::trusted()`, which skips the private and sensitive column checks only;
  every other check and event still runs. A request's write (a form given to
  `addItem()` / `updateOneItem()`) is checked as before.
- **Security: a request may not filter, sort or page on a private or sensitive
  column; server code may.** The check ran the wrong way round: a request's
  filters (`ORMOptions`, as a REST client sends them) were applied with no
  scope, so a client could filter on a column it is never shown and learn its
  values by trial, while the query's own methods (`filterBy()`, the generated
  `where...()`) refused a private column even to server code. A request's
  filters are now checked by a strict `FiltersTableScope`; its `order_by` and
  `cursor_column` refuse such a column (`GOBL_ORM_REQUEST_INVALID_ORDER_BY`,
  `GOBL_ORM_REQUEST_INVALID_CURSOR_COLUMN`); an `ORMTableQuery` allows private
  and sensitive columns in its own filters.
- **`softDeletable()` makes `deleted` and `deleted_at` private.** A form could
  write them: a create through a controller could make a row already deleted,
  an update could undelete one. They are now written by the ORM's soft delete
  only (a CRUD request needs a listener allowing a private column write), and
  left out of `toArray()` and of the generated TS and Dart types. The schema
  diff compares column types only: no migration follows.
- **A write that meets a unique key throws `DBALUniqueViolationException`** on
  every driver, in place of the database's own `PDOException` (kept as its
  previous): `getTable()`, `getConstraint()` (the `UniqueKey` or the
  `PrimaryKey`) and `getColumns()` (full names); message
  `GOBL_UNIQUE_KEY_VIOLATION`. A check made before a write ("this email is not
  registered") cannot replace it: two writes may both pass the check first. A
  key the schema does not declare keeps the `PDOException`.
- **SQLite transactions begin with `BEGIN IMMEDIATE`**: a transaction takes the
  write lock as it starts and waits for it. With a plain `BEGIN`, two
  transactions that read then write (check, then insert) met, and SQLite failed
  one at once with "database is locked". Read-only transactions are serialized
  too. Drivers override `beginOuterTransaction()`, `commitOuterTransaction()`
  and `rollBackOuterTransaction()`; `SQLDriverBase` gains the abstract
  `readUniqueViolation()`.
- **A controller's form names a column by its name or its full name.**
  `ORMController::addItem()` took a form keyed by column names (`email`) through
  the CRUD checks, then looked for its required fields by full name
  (`user_email`) and refused it with `GOBL_ORM_REQUEST_MISSING_FIELDS`; and
  `ORMOptions::getFormData($table)` silently dropped every key that was not a
  full name, so an update by column names failed with
  `GOBL_ORM_REQUEST_NO_FIELDS_TO_UPDATE`. A form is now keyed by full names
  (`ORM::formByFullNames()`) before the CRUD checks and again after them (a
  listener may edit it), in `addItem()`, `updateOneItem()` and
  `updateAllItems()`. A form that names one column by both names is refused with
  `GOBL_ORM_REQUEST_FIELD_GIVEN_TWICE`. A private column is guarded whichever
  name the form gives it.
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
