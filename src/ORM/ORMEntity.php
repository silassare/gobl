<?php

/**
 * Copyright (c) Emile Silas Sare.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Gobl\ORM;

use Gobl\CRUD\Exceptions\CRUDException;
use Gobl\DBAL\Column;
use Gobl\DBAL\Constraints\PrimaryKey;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Operator;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\DBAL\Table;
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\Interfaces\ValidationSubjectInterface;
use Gobl\Exceptions\GoblException;
use Gobl\ORM\Exceptions\ORMException;
use Gobl\ORM\Exceptions\ORMRuntimeException;
use Override;
use PHPUtils\Interfaces\ArrayCapableInterface;
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class ORMEntity.
 *
 * To prevent conflict between:
 * - entity class property name and column magic getter and setter
 * - entity class method and column method (getter and setter)
 * We only use:
 * - a prefix with a single `_` for property
 * - camelCase method name avoiding prefixing with `get` or `set`
 * So don't use:
 * - `getSomething`, `setSomething` or `our_property`
 * Use instead:
 * - `_getSomething`, `_setSomething`, `doSomething` or `_our_property`
 */
abstract class ORMEntity implements ArrayCapableInterface
{
	use ArrayCapableTrait;

	/** @var Table */
	private Table $_oeb_table;

	/** @var bool */
	private bool $_oeb_is_new;

	/**
	 * To enable/disable strict mode.
	 *
	 * @var bool
	 */
	private bool $_oeb_strict;

	/** @var RDBMSInterface */
	private RDBMSInterface $_oeb_db;

	/** @var array */
	private array $_oeb_row = [];

	/**
	 * Set of column full names populated by PDO from a DB row.
	 * Solely used to distinguish PDO mode from user-mode writes in `__set`.
	 *
	 * @var array<string, true>
	 */
	private array $_oeb_from_db = [];

	/**
	 * Columns dirtied since the last save. Keyed by full column name, value is always true.
	 *
	 * @var array<string, true>
	 */
	private array $_oeb_dirty = [];

	/**
	 * Hash snapshots frozen at the last save (or PDO load), keyed by full column name.
	 * Baseline for dirty detection; captures content before any in-place mutation
	 * of mutable values (e.g. Map) can change what `_oeb_row` points to.
	 *
	 * @var array<string, string>
	 */
	private array $_oeb_saved_hashes = [];

	/** @var array<string, ValidationSubjectInterface> */
	private array $_oeb_subjects = [];

	/**
	 * Whether this entity was loaded with a partial column projection.
	 *
	 * When true, accessing a column that was not in the projection throws
	 * {@see ORMRuntimeException} instead of silently returning a default value.
	 *
	 * @var bool
	 */
	private bool $_oeb_is_partial = false;

	/**
	 * Set of full column names that were loaded in partial mode.
	 * Only meaningful when $_oeb_is_partial is true.
	 *
	 * @var array<string, true>
	 */
	private array $_oeb_partial_columns = [];

	/**
	 * Computed values injected during PDO hydration via `_gobl_*` column aliases.
	 *
	 * These are ephemeral query-time values (e.g. batch routing keys, window
	 * function results) that have no schema column. They are never validated,
	 * never dirtied, and never written by `save()` / `toRow()`.
	 *
	 * @var array<string, mixed>
	 */
	private array $_oeb_computed = [];

	/**
	 * ORMEntity constructor.
	 *
	 * @param string $namespace  the table namespace
	 * @param string $table_name the table name
	 * @param bool   $is_new     true for new entity, false for entity fetched
	 *                           from the database, default is true
	 * @param bool   $strict     enable/disable strict mode
	 */
	protected function __construct(
		string $namespace,
		string $table_name,
		bool $is_new,
		bool $strict
	) {
		$this->_oeb_db       = ORM::getDatabase($namespace);
		$this->_oeb_table    = $this->_oeb_db->getTableOrFail($table_name);
		$columns             = $this->_oeb_table->getColumns();
		$this->_oeb_is_new   = $is_new;
		$this->_oeb_strict   = $strict;

		if ($this->_oeb_is_new) {
			foreach ($columns as $column) {
				$full_name                  = $column->getFullName();
				$type                       = $column->getType();
				$this->_oeb_row[$full_name] = $type->getDefault();
			}
		}
	}

	/**
	 * Destructor.
	 */
	public function __destruct()
	{
		unset($this->_oeb_db, $this->_oeb_table, $this->_oeb_row, $this->_oeb_from_db, $this->_oeb_dirty, $this->_oeb_saved_hashes, $this->_oeb_partial_columns, $this->_oeb_computed);
	}

	/**
	 * Magic isset for column.
	 *
	 * @param string $name
	 *
	 * @return bool
	 */
	final public function __isset(string $name)
	{
		return $this->_oeb_table->hasColumn($name);
	}

	/**
	 * Magic getter for column value.
	 *
	 * Resolution order when the stored value is `null`:
	 * 1. If the column is auto-incremented and the entity is new -> returns `null` (ID not yet assigned).
	 * 2. If the column is non-nullable -> tries `Type::getDefault()`, then `Type::getEmptyValueOfType()`;
	 *    if both return `null`, throws `ORMRuntimeException` (programming error: required value was never set).
	 * 3. Otherwise (nullable column) -> returns `null`.
	 *
	 * When `$name` is not a valid column and `$strict` mode is on, throws `ORMRuntimeException`;
	 * otherwise triggers a PHP error and returns `null`.
	 *
	 * For partially-loaded entities (see {@see markAsPartial()}) accessing a column that was not
	 * included in the projection always throws `ORMRuntimeException`. Use {@see isColumnLoaded()}
	 * to check before accessing.
	 *
	 * @param string $name the column full name or name
	 *
	 * @return null|mixed
	 */
	final public function __get(string $name)
	{
		if ($this->_oeb_table->hasColumn($name)) {
			$column    = $this->_oeb_table->getColumnOrFail($name);
			$full_name = $column->getFullName();

			// Partial-load guard: throw clearly when the column was not fetched.
			if ($this->_oeb_is_partial && !isset($this->_oeb_partial_columns[$full_name])) {
				throw new ORMRuntimeException(
					\sprintf(
						'Column "%s" was not included in the partial projection for table "%s". Use isColumnLoaded() to check before accessing.',
						$name,
						$this->_oeb_table->getName()
					)
				);
			}

			$value = $this->_oeb_row[$full_name] ?? null;
			$type  = $column->getType();

			if (null === $value) {
				if ($this->isNew() && $type->isAutoIncremented()) {
					return null;
				}

				if (!$type->isNullable()) {
					// this is an attempt to prevent returning null
					// as the property is supposed to not have null value
					$value = $type->getDefault();
					if (null === $value) {
						$value = $type->getEmptyValueOfType();
					}

					if (null === $value) {
						throw new ORMRuntimeException(
							\sprintf(
								'Missing required value for column "%s" defined in table "%s".',
								$name,
								$this->_oeb_table->getName()
							)
						);
					}
				}
			}

			return $value;
		}

		$error = \sprintf('Column "%s" not defined in table "%s".', $name, $this->_oeb_table->getName());

		if ($this->_oeb_strict) {
			throw new ORMRuntimeException($error);
		}

		\trigger_error($error  /* , \E_USER_NOTICE */);

		return null;
	}

	/**
	 * Magic setter for column value.
	 *
	 * Three modes in priority order:
	 * - **Computed mode** (name starts with `_gobl_`): stores the raw value in
	 *   `$_oeb_computed`. No validation, no
	 *   dirty tracking, never written by save(). Used by batch-routing queries
	 *   (e.g. `pivot.fk AS _gobl_batch_key`) and any custom computed SELECT
	 *   expressions added via {@see QBSelect::selectComputed()}.
	 * - **User mode** (entity is new, or column is in `_oeb_from_db`): validates via
	 *   `doValidation()`, compares hash against `_oeb_saved_hashes`, marks dirty if changed.
	 * - **PDO mode** (column not yet in `_oeb_from_db`): converts via `Type::dbToPhp()`,
	 *   records a hash snapshot, marks the column as DB-sourced.
	 *
	 * @param string $name  the column full name, short name, or a `_gobl_*` computed alias
	 * @param mixed  $value the column value
	 *
	 * @throws TypesInvalidValueException
	 */
	final public function __set(string $name, mixed $value): void
	{
		// computed slot: _gobl_{var_name} injected by a SELECT computed alias
		if (\str_starts_with($name, '_gobl_')) {
			$this->_oeb_computed[$name] = $value;

			return;
		}

		if ($this->_oeb_table->hasColumn($name)) {
			$column    = $this->_oeb_table->getColumnOrFail($name);
			$full_name = $column->getFullName();

			// false when PDO is populating the entity from a DB row
			if (\array_key_exists($full_name, $this->_oeb_from_db) || $this->isNew()) {
				$type       = $column->getType();
				$clean      = $this->doValidation($full_name, $value);
				$clean_hash = $type->hash($clean);

				if (
					!\array_key_exists($full_name, $this->_oeb_row)
					|| !\array_key_exists($full_name, $this->_oeb_saved_hashes)
					|| $this->_oeb_saved_hashes[$full_name] !== $clean_hash
				) {
					$this->_oeb_row[$full_name]   = $clean;
					$this->_oeb_dirty[$full_name] = true;
				}
			} else { // PDO is populating the entity from a DB row
				$type                                = $column->getType();
				$value                               = $type->dbToPhp($value, $this->_oeb_db);
				$this->_oeb_row[$full_name]          = $value;
				$this->_oeb_from_db[$full_name]      = true;
				$this->_oeb_saved_hashes[$full_name] = $type->hash($value);
			}
		} else {
			$error = \sprintf('Column "%s" not defined in table "%s".', $name, $this->_oeb_table->getName());

			if ($this->_oeb_strict) {
				throw new ORMRuntimeException($error);
			}

			\trigger_error($error /* , \E_USER_NOTICE */);
		}
	}

	/**
	 * Help var_dump().
	 *
	 * @return array
	 */
	public function __debugInfo(): array
	{
		return ['instance_of' => static::class, 'data' => $this->toRow()];
	}

	/**
	 * Returns new instance.
	 *
	 * @param bool $is_new true for new entity, false for entity fetched
	 *                     from the database, default is true
	 * @param bool $strict enable/disable strict mode
	 *
	 * @return static
	 */
	abstract public static function new(bool $is_new = true, bool $strict = true): static;

	/**
	 * Returns the table instance.
	 *
	 * @return Table
	 */
	abstract public static function table(): Table;

	/**
	 * Returns the table query builder instance.
	 *
	 * @return ORMTableQuery
	 */
	abstract public static function qb(): ORMTableQuery;

	/**
	 * Returns the table results instance.
	 *
	 * @param QBSelect $query the query builder instance
	 *
	 * @return ORMResults
	 */
	abstract public static function results(QBSelect $query): ORMResults;

	/**
	 * Returns the table crud event producer instance.
	 *
	 * @return ORMEntityCRUD
	 */
	abstract public static function crud(): ORMEntityCRUD;

	/**
	 * Returns true for entities not yet persisted to the DB.
	 * Becomes false after the first successful `save()`.
	 *
	 * @return bool
	 */
	public function isNew(): bool
	{
		return $this->_oeb_is_new;
	}

	/**
	 * Returns the entity data as an associative array keyed by full column names.
	 *
	 * When `$hide_sensitive_data` is true (default), private columns are removed and
	 * sensitive columns are replaced with their redacted value.
	 *
	 * For partially-loaded entities (see {@see markAsPartial()}), the result is further
	 * filtered to only the projected columns — columns that were not part of the
	 * projection are omitted even if physically present in `$_oeb_row`.
	 *
	 * {@see toRow()} always returns the raw internal row without either filter.
	 *
	 * @param bool $hide_sensitive_data removes private columns and redacts sensitive ones when true
	 *
	 * @return array<string, mixed>
	 */
	#[Override]
	public function toArray(bool $hide_sensitive_data = true): array
	{
		$row = $this->toRow();

		// we first clean sensitive data and remove private data,
		if ($hide_sensitive_data) {
			$columns = $this->_oeb_table->getColumns();

			foreach ($columns as $column) {
				if ($column->isPrivate()) {
					unset($row[$column->getFullName()]);
				} elseif ($column->isSensitive()) {
					$row[$column->getFullName()] = $column->getSensitiveRedactedValue();
				}
			}
		}

		// then we filter by partial columns if needed
		if ($this->_oeb_is_partial) {
			$partial_row = [];
			foreach ($this->_oeb_partial_columns as $key => $_) {
				if (\array_key_exists($key, $row)) {
					$partial_row[$key] = $row[$key];
				}
			}

			return $partial_row;
		}

		return $row;
	}

	/**
	 * Returns the raw internal row data keyed by full column name.
	 *
	 * Unlike {@see toArray()}, this method applies no filters: private columns are included,
	 * sensitive columns are not redacted, and partial-entity projections are not applied.
	 * Use this when you need the unfiltered row (e.g. to pass to another entity via
	 * {@see hydrate()}).
	 *
	 * @return array<string, mixed>
	 */
	final public function toRow(): array
	{
		return $this->_oeb_row;
	}

	/**
	 * Save modifications to database.
	 *
	 * @return bool `true` when an insert or update occur,
	 *              `false` when nothing is done
	 *
	 * @throws CRUDException
	 * @throws ORMException
	 * @throws GoblException
	 */
	public function save(): bool
	{
		if ($this->isNew()) {
			static::ctrl()
				->addItem($this);

			return true;
		}

		if (!empty($this->_oeb_from_db) && !$this->isSaved()) {
			$to_update = [];

			foreach ($this->_oeb_dirty as $column_name => $_) {
				$to_update[$column_name] = $this->_oeb_row[$column_name];
			}

			if (!empty($to_update)) {
				$options = new ORMOptions();
				$options->setFilters($this->toIdentityFilters());
				$options->setFormData($to_update);

				$saved = static::ctrl()->updateOneItem($options);

				return $saved && $this->hydrate($saved->toRow())
					->isSaved(true);
			}
		}

		return false;
	}

	/**
	 * Returns the table controller instance.
	 *
	 * @return ORMController
	 */
	abstract public static function ctrl(): ORMController;

	/**
	 * Returns whether the entity has been persisted to the database.
	 *
	 * When `$set_as_saved` is `true`:
	 * - snapshots hashes of all current columns into `_oeb_saved_hashes`,
	 * - marks them in `_oeb_from_db`,
	 * - clears `_oeb_dirty` and the `isNew` flag.
	 *
	 * @param bool $set_as_saved mark the entity as persisted
	 *
	 * @return bool
	 */
	public function isSaved(bool $set_as_saved = false): bool
	{
		if ($set_as_saved) {
			// Auto-detect partial load: when coming from a DB fetch (not new), at least one
			// column was hydrated, and fewer columns were hydrated than the table defines,
			// mark as partial automatically -- no need for the caller to pass partial_columns.
			if (!$this->_oeb_is_partial && !$this->_oeb_is_new && !empty($this->_oeb_row)) {
				$total = \count($this->_oeb_table->getColumns());

				if (\count($this->_oeb_row) < $total) {
					$this->markAsPartial(\array_keys($this->_oeb_row));
				}
			}

			foreach ($this->_oeb_row as $full_name => $v) {
				$this->_oeb_saved_hashes[$full_name] = $this->_oeb_table->getColumnOrFail($full_name)->getType()->hash($v);
				$this->_oeb_from_db[$full_name]      = true;
			}

			$this->_oeb_dirty  = [];
			$this->_oeb_is_new = false;
		}

		return empty($this->_oeb_dirty) && !$this->_oeb_is_new;
	}

	/**
	 * Returns filters that uniquely identify the current entity.
	 *
	 * @return array
	 *
	 * @throws ORMException
	 */
	public function toIdentityFilters(): array
	{
		if ($this->_oeb_table->hasPrimaryKeyConstraint()) {
			/** @var PrimaryKey $pk */
			$pk = $this->_oeb_table->getPrimaryKeyConstraint();

			$columns = $pk->getColumns();
			$filters = $this->_toIdentityFilters($columns, $missing);

			if (!empty($missing)) {
				throw new ORMException(\sprintf('Required identity column(s) "%s" value was not set.', \implode(', ', $missing)));
			}

			return $filters;
		}

		if ($this->_oeb_table->hasUniqueKeyConstraint()) {
			$all_missing = [];
			foreach ($this->_oeb_table->getUniqueKeyConstraints() as $uq) {
				$columns = $uq->getColumns();
				$m       = [];
				$filters = $this->_toIdentityFilters($columns, $m);

				if (empty($m)) {
					return $filters;
				}

				$all_missing += $m;
			}

			throw new ORMException(\sprintf('All or some required identity column(s) "%s" value was not set.', \implode(', ', $all_missing)));
		}

		throw new ORMException('Unable to uniquely identify the entity.');
	}

	/**
	 * Returns a stable string key that uniquely identifies this entity.
	 *
	 * Uses the same primary/unique-key columns as {@see toIdentityFilters()}.
	 * For a single-column PK the key is the column value cast to string.
	 * For a composite PK the column values are joined with `:`.
	 * The resulting key is opaque and must be treated as an implementation detail
	 * (do not persist or compare across different entity types).
	 *
	 * @return string
	 *
	 * @throws ORMException
	 */
	public function toIdentityKey(): string
	{
		$filters = $this->toIdentityFilters();
		$values  = [];

		foreach ($filters as $item) {
			if (\is_array($item)) {
				$values[] = (string) ($item[2] ?? '');
			}
		}

		return \implode(':', $values);
	}

	/**
	 * Hydrate this entity with values from an array.
	 *
	 * @param array $row map column name to column value
	 *
	 * @return static
	 */
	public function hydrate(array $row): static
	{
		foreach ($row as $column_name => $value) {
			$this->{$column_name} = $value;
		}

		return $this;
	}

	/**
	 * Marks this entity as partially loaded.
	 *
	 * After this call, accessing any column NOT in $partial_columns via {@see __get()} throws
	 * {@see ORMRuntimeException}. Use {@see isColumnLoaded()} to check first.
	 * This is to prevent:
	 * 1) accidentally treating unloaded columns as null or default values.
	 * 2) returning null or default values for columns that are actually non-nullable and required,
	 *    which would be a silent data integrity issue or violate ORM Entities generated class methods contract
	 *    (e.g. a getter that promises to return an int but returns null instead because the column was not loaded).
	 *
	 * @param list<string> $partial_columns set of column names that were loaded
	 *
	 * @return static
	 */
	public function markAsPartial(array $partial_columns): static
	{
		$list = [];

		foreach ($partial_columns as $name) {
			$full_name        = $this->_oeb_table->getColumnOrFail($name)->getFullName();
			$list[$full_name] = true;
		}

		$this->_oeb_is_partial      = true;
		$this->_oeb_partial_columns = $list;

		return $this;
	}

	/**
	 * Returns whether this entity was loaded with a partial column projection.
	 *
	 * When true, only columns returned by {@see isColumnLoaded()} are accessible
	 * via {@see __get()}.
	 *
	 * @return bool
	 */
	public function isPartial(): bool
	{
		return $this->_oeb_is_partial;
	}

	/**
	 * Returns whether a given column was included in this entity's load projection.
	 *
	 * Always returns true for new (not-yet-persisted) entities and for fully-loaded entities.
	 * For partially-loaded entities returns true only when the column was part of the projection.
	 *
	 * @param string $name column short name or full name
	 *
	 * @return bool
	 */
	public function isColumnLoaded(string $name): bool
	{
		if ($this->_oeb_is_new || !$this->_oeb_is_partial) {
			return true;
		}

		$col = $this->_oeb_table->getColumn($name);

		return null !== $col && isset($this->_oeb_partial_columns[$col->getFullName()]);
	}

	/**
	 * Returns a computed value previously injected via a `_gobl_*` SELECT alias.
	 *
	 * Computed values are set by the query layer (e.g. batch routing keys, window
	 * function results) and are never written to the database. Returns `null` when
	 * the value was not present in the result row. Use {@see hasComputedValue()} to
	 * distinguish "not set" from a value that is genuinely `null`.
	 *
	 * @param string $var_name the variable name used in the SELECT alias (without the `_gobl_` prefix)
	 *
	 * @return mixed
	 */
	final public function getComputedValue(string $var_name): mixed
	{
		$key = '_gobl_' . $var_name;

		return $this->_oeb_computed[$key] ?? null;
	}

	/**
	 * Returns whether a computed value was set during PDO hydration.
	 *
	 * @param string $var_name the variable name (without the `_gobl_` prefix)
	 *
	 * @return bool
	 */
	final public function hasComputedValue(string $var_name): bool
	{
		$key = '_gobl_' . $var_name;

		return \array_key_exists($key, $this->_oeb_computed);
	}

	/**
	 * @param null|bool $soft
	 *
	 * @return static
	 *
	 * @throws ORMException
	 * @throws GoblException
	 */
	public function selfDelete(?bool $soft = null): static
	{
		$options = new ORMOptions();
		$options->setFilters($this->toIdentityFilters());

		static::ctrl()->deleteOneItem($options, $soft);

		return $this;
	}

	/**
	 * Validates and returns the new value for a column.
	 *
	 * Only re-validates if the value actually changed from the currently stored value;
	 * identical values pass through without a `Type::validate()` call.
	 * On validation failure, the exception's data is enriched with `field`,
	 * `_table_name`, and `_options` before being re-thrown.
	 *
	 * @param string $name  the column name or full name
	 * @param mixed  $value the column new value
	 *
	 * @return mixed the validated (and possibly coerced) value
	 *
	 * @throws TypesInvalidValueException
	 */
	protected function doValidation(string $name, mixed $value): mixed
	{
		$column    = $this->_oeb_table->getColumnOrFail($name);
		$full_name = $column->getFullName();
		$type      = $column->getType();

		// Fast path: caller provides an already-accepted ValidationSubjectInterface
		if ($value instanceof ValidationSubjectInterface && $value->isValid()) {
			$this->_oeb_subjects[$full_name] = $value;

			return $value->getCleanValue();
		}

		// Extract raw value if wrapped in a non-accepted subject
		$rawValue = $value instanceof ValidationSubjectInterface ? $value->getUnsafeValue() : $value;

		// Check cached subject for same raw value (avoid re-validation when raw input is unchanged)
		$cached = $this->_oeb_subjects[$full_name] ?? null;

		if ($cached instanceof ValidationSubjectInterface && $cached->isValid()) {
			$cached->setUnsafeValue($rawValue); // resets to UNCHECKED only if value changed

			if ($cached->isValid()) {
				return $cached->getCleanValue(); // same raw value, skip re-validation
			}

			$subject = $cached; // reuse the subject (now UNCHECKED, ready for re-validation)
		} else {
			$subject                         = $type->createValidationSubject($rawValue, $column->getName(), $full_name);
			$this->_oeb_subjects[$full_name] = $subject;
		}

		if (!$type->applyValidation($subject)) {
			$ex = $subject->getRejectionException();

			if ($ex instanceof TypesInvalidValueException) {
				$debug = \array_replace($ex->getData(), [
					'field'       => $column->getName(),
					'_table_name' => $this->_oeb_table->getName(),
					'_options'    => $type->toArray(),
				]);

				$ex->setData($debug);

				throw $ex;
			}

			throw new TypesInvalidValueException('Validation failed.', [
				'field'       => $column->getName(),
				'_table_name' => $this->_oeb_table->getName(),
			], $ex);
		}

		return $subject->getCleanValue();
	}

	/**
	 * Returns filters that uniquely identify the current entity.
	 *
	 * @param string[]      $columns
	 * @param null|string[] $missing
	 *
	 * @return array
	 */
	private function _toIdentityFilters(array $columns, ?array &$missing = []): array
	{
		$filters = [];
		$head    = true;

		foreach ($columns as $entry) {
			/** @var Column $column */
			$column         = $this->_oeb_table->getColumn($entry);
			$column_name_fn = $column->getFullName();
			$value          = $this->{$column_name_fn};

			if (null === $value && !$column->getType()
				->isNullable()) { // unique constraint may be nullable
				$missing[] = $column_name_fn;

				continue;
			}

			if (!$head) {
				$filters[] = 'and';
			}

			$filters[] = [$column_name_fn, Operator::EQ, $value];

			$head = false;
		}

		return $filters;
	}
}
