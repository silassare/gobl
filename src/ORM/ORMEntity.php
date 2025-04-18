<?php

/**
 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>.
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
use Gobl\Exceptions\GoblException;
use Gobl\ORM\Exceptions\ORMException;
use Gobl\ORM\Exceptions\ORMRuntimeException;
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

	/** @var bool */
	private bool $_oeb_is_saved;

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

	/** @var array */
	private array $_oeb_row_saved = [];

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
		$this->_oeb_is_saved = !$is_new;
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
		unset($this->_oeb_db, $this->_oeb_table, $this->_oeb_row, $this->_oeb_row_saved);
	}

	/**
	 * Magic isset for column.
	 *
	 * @param $name
	 *
	 * @return bool
	 */
	final public function __isset($name)
	{
		return $this->_oeb_table->hasColumn($name);
	}

	/**
	 * Magic getter for column value.
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
			$value     = $this->_oeb_row[$full_name] ?? null;
			$type      = $column->getType();

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
	 * @param string $name  the column full name or name
	 * @param mixed  $value the column value
	 *
	 * @throws TypesInvalidValueException
	 */
	final public function __set(string $name, mixed $value): void
	{
		if ($this->_oeb_table->hasColumn($name)) {
			$column    = $this->_oeb_table->getColumnOrFail($name);
			$full_name = $column->getFullName();

			// false when we are being hydrated by PDO
			if (\array_key_exists($full_name, $this->_oeb_row_saved) || $this->isNew()) {
				if (!\array_key_exists($full_name, $this->_oeb_row) || $this->_oeb_row[$full_name] !== $value) {
					$this->_oeb_row[$full_name] = $this->doValidation($full_name, $value);
					$this->_oeb_is_saved        = false;
				}
			} else { // we are being hydrated by PDO
				$type                             = $column->getType();
				$value                            = $type->dbToPhp($value, $this->_oeb_db);
				$this->_oeb_row[$full_name]       = $value;
				$this->_oeb_row_saved[$full_name] = $value;
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
	 * @param QBSelect $query
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
	 * To check if this entity is new.
	 *
	 * ```php
	 * <?php
	 *
	 * $n = new Entity();
	 *
	 * $n->isSaved(); // false
	 * $n->isNew(); // true
	 *
	 * $n->name = "Toto";
	 *
	 * $n->isSaved(); // false
	 * $n->isNew(); // true
	 *
	 * $n->save(); // will save the entity into the database
	 *
	 * $n->isSaved(); // true
	 * $n->isNew(); // false
	 *
	 * $s = new Entity(false);
	 *
	 * $s->isSaved(); // true
	 * $s->isNew(); // false
	 *
	 * $s->name = "Franck";
	 *
	 * $s->isSaved(); // true
	 * $s->isNew(); // false
	 *
	 * $s->name = "Jack";
	 *
	 * $s->isSaved(); // false
	 * $s->isNew(); // false
	 * ```
	 *
	 * @return bool
	 */
	public function isNew(): bool
	{
		return $this->_oeb_is_new;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(bool $hide_sensitive_data = true): array
	{
		$row = $this->toRow();

		if ($hide_sensitive_data) {
			$privates_columns = $this->_oeb_table->getPrivatesColumns();

			foreach ($privates_columns as $column) {
				unset($row[$column->getFullName()]);
			}
		}

		return $row;
	}

	/**
	 * This help us get row data.
	 *
	 * @return array
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

		if (!empty($this->_oeb_row_saved) && !$this->isSaved()) {
			$to_update = [];

			foreach ($this->_oeb_row as $column_name => $value) {
				if ($this->_oeb_row_saved[$column_name] !== $value) {
					$to_update[$column_name] = $value;
				}
			}

			if (!empty($to_update)) {
				$saved = static::ctrl()
					->updateOneItem($this->toIdentityFilters(), $to_update);

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
	 * To check if this entity is saved.
	 *
	 * @param bool $set_as_saved if true the entity will be considered as saved
	 *
	 * @return bool
	 */
	public function isSaved(bool $set_as_saved = false): bool
	{
		if (true === $set_as_saved) {
			$this->_oeb_row_saved = \array_replace($this->_oeb_row_saved, $this->_oeb_row);
			$this->_oeb_is_new    = false;
			$this->_oeb_is_saved  = true;
		}

		return $this->_oeb_is_saved;
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
	 * Hydrate this entity with values from an array.
	 *
	 * @param array $row map column name to column value
	 *
	 * @return $this
	 */
	public function hydrate(array $row): static
	{
		foreach ($row as $column_name => $value) {
			$this->{$column_name} = $value;
		}

		return $this;
	}

	/**
	 * Self delete the entity.
	 *
	 * @param null|bool $soft
	 *
	 * @return static
	 *
	 * @throws ORMException
	 * @throws GoblException
	 */
	public function selfDelete(?bool $soft = null): static
	{
		static::ctrl()
			->deleteOneItem($this->toIdentityFilters(), $soft);

		return $this;
	}

	/**
	 * Sets a column value.
	 *
	 * @param string $name  the column name or full name
	 * @param mixed  $value the column new value
	 *
	 * @return mixed
	 *
	 * @throws TypesInvalidValueException
	 */
	protected function doValidation(string $name, mixed $value): mixed
	{
		$column    = $this->_oeb_table->getColumnOrFail($name);
		$full_name = $column->getFullName();
		$type      = $column->getType();

		if ($this->_oeb_row[$full_name] !== $value) {
			try {
				$value = $type->validate($value);
			} catch (TypesInvalidValueException $e) {
				$debug = \array_replace($e->getData(), [
					'field'       => $full_name,
					'_table_name' => $this->_oeb_table->getName(),
					'_options'    => $type->toArray(),
				]);

				$e->setData($debug);

				throw $e;
			}
		}

		return $value;
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
				->isNullable()) {// unique constraint may be nullable
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
