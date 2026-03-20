# TODO

## Morph Relation Integrity Events

### Context

Gobl's morph relations use a `LinkMorph` object that stores a `morph_type` (a string identifying the
parent table, e.g. `'post'`) and a `morph_id` column (the parent PK). Unlike a regular FK, there is
no DB-level cascade or restrict constraint. Any referential integrity for morph relations must be
enforced in application code.

### Proposal: `BeforeMorphLink*` Events + `morph_link` Options

Three new CRUD events, fired by `ORMController` at key points:

| Event                     | Fired when                                             |
| ------------------------- | ------------------------------------------------------ |
| `BeforeMorphLinkCreation` | A morph child row is inserted with non-null type + id  |
| `BeforeMorphLinkUpdate`   | The type or id morph column changes on an existing row |
| `BeforeMorphLinkDeletion` | A morph parent entity is about to be hard-deleted      |

These slot into the existing event sequences:

- `BeforeMorphLinkCreation` fires between `BeforeCreateFlush` and the actual INSERT.
- `BeforeMorphLinkUpdate` fires between `BeforeEntityUpdate` and the actual UPDATE.
- `BeforeMorphLinkDeletion` fires between `BeforeEntityDeletion` and the actual DELETE.

**Bulk operations** (`updateAllItems`, `deleteAllItems`) fire **no** per-entity morph events by
design -- morph integrity for bulk operations is the caller's responsibility. Document clearly.
(`deleteAll` is already denied by default unless explicitly allowed.)

### Proposed `morph_link` Options

New options on the relation definition (alongside the existing `morph_link` key):

```php
'morph_link' => [
    'on_parent_delete'      => 'restrict',   // restrict | cascade | set_null
    'on_parent_type_change' => 'restrict',   // restrict | allow
    'validate_parent'       => true,         // bool: check parent exists on link creation/update
]
```

- `on_parent_delete`:
    - `restrict` (default) -- deny deletion of the morph parent if children reference it.
    - `cascade` -- delete all child rows when the parent is deleted.
    - `set_null` -- null out the morph columns on child rows when the parent is deleted.
- `on_parent_type_change`: whether to allow changing the `morph_type` column on an existing row.
- `validate_parent`: when `true`, verify the referenced parent row actually exists on creation and
  update.

### Files to Create/Modify

| File                                          | Change                                                                                                   |
| --------------------------------------------- | -------------------------------------------------------------------------------------------------------- |
| `src/CRUD/Events/BeforeMorphLinkCreation.php` | New event class                                                                                          |
| `src/CRUD/Events/BeforeMorphLinkUpdate.php`   | New event class                                                                                          |
| `src/CRUD/Events/BeforeMorphLinkDeletion.php` | New event class                                                                                          |
| `src/DBAL/Relations/LinkMorph.php`            | Accept and expose new `morph_link` options                                                               |
| `src/CRUD/CRUDEventProducer.php`              | `onBeforeMorphLinkCreation`, `onBeforeMorphLinkUpdate`, `onBeforeMorphLinkDeletion` subscription methods |
| `src/ORM/ORMController.php`                   | Fire morph events at appropriate CRUD lifecycle points                                                   |

### How Other Frameworks Handle Polymorphic Integrity

#### Eloquent (Laravel)

No database-level cascade for polymorphic relations. Eloquent provides `static::deleting()` /
`static::deleted()` model lifecycle hooks on the parent model. Developers manually cascade or
restrict in these observers. No built-in `morph_link` options. Very commonly cited as a gotcha.

#### Doctrine (PHP)

Doctrine maps morph-style relations via `@TargetEntity` (single-type) or
`ResolveTargetEntity` at the metadata level. True polymorphism (multiple possible target types)
is not a first-class feature; the community typically uses a `Discriminator` pattern + abstract
entity hierarchy. Cascade options (`cascade={"remove"}` etc.) work at the PHP ORM level (not
always as DB constraints). Orphan removal (`orphanRemoval=true`) is available for owned relations.

#### Prisma (TypeScript / Node)

Prisma does not support polymorphic relations directly in its schema language. The recommended
workaround is a single `type` + `id` pair (exactly Gobl's approach) but with an explicit
`@@index` and no DB constraint. Cascade/restrict must be implemented in middleware or application
logic (`$use()` Prisma extensions or service-layer checks). Tracked as a long-standing open
feature request.

#### TypeORM (TypeScript / Node)

TypeORM has a `@PolymorphicRelation` concept (community extension: `typeorm-polymorphic`).
Without the extension, developers model it the same way as Gobl. Cascade options exist for
regular relations but not for polymorphic ones. The `typeorm-polymorphic` library fires
`find/save/remove` hooks on the abstract parent but does not enforce at the DB level.

#### SQLAlchemy (Python)

Single-table inheritance and joined-table inheritance are native. For abstract polymorphism
(morph-style), developers use a `discriminator_on_exception` pattern and add `@event.listens_for`
listeners on `Session` events (`before_flush`, `after_bulk_delete`). No built-in morph_link
options; all integrity is application-level.

### Notes on Bulk Deletion Without Per-Entity Events

All mainstream ORMs face the same tradeoff: per-row events require fetching every affected row
into memory. For bulk operations this is prohibitive.

Common patterns other frameworks use:

1. **Soft-delete all + background sweep** (Eloquent `SoftDeletes` + scheduled pruning): never
   hard-delete in bulk; mark deleted and sweep orphan morph children asynchronously.
2. **Raw SQL with subquery cascade**: issue `DELETE FROM children WHERE morph_type = ? AND
morph_id IN (SELECT id FROM parents WHERE ...)` before the parent bulk delete.
3. **Trigger-based integrity** (MySQL / PostgreSQL): a `BEFORE DELETE` trigger on the parent
   table queries the morph child table and raises an error or cascades. Fully transparent to
   the application but tied to a specific driver.

For Gobl the recommended approach is: keep `deleteAll` denied by default (already the case),
and require callers to either iterate and call `deleteOneItem()` (which will fire morph events
once implemented) or issue a raw subquery cascade before calling `deleteAllItems()`.
