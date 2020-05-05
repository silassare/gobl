<?php

/**
 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gobl\ORM;

use Gobl\DBAL\Db;
use Gobl\DBAL\QueryBuilder;
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\Exceptions\GoblBaseException;
use Gobl\ORM\Exceptions\ORMException;

/**
 * Class ORMEntityBase
 *
 * To prevent conflict between:
 * - entity class property name and column magic getter and setter
 * - entity class method and column method (getter and setter)
 * We only use:
 * - a prefix with a single `_` for property
 * - camelCase method name avoiding prefixing with `get` or `set` so
 * So don't use:
 * - `getSomething`, `setSomething` or `our_property`
 * Use instead:
 * - `_getSomething`, `_setSomething`, `doSomething` or `_our_property`
 *
 * ```php
 * <?php
 *
 * $n = new Entity();
 *
 * $n->isSaved() // false
 * $n->isNew() // true
 *
 * $n->name = "Toto";
 *
 * $n->isSaved() // false
 * $n->isNew() // true
 *
 * $n->save() // will save the entity into the database
 *
 * $n->isSaved() // true
 * $n->isNew() // false
 *
 * $s = new Entity(false);
 *
 * $s->isSaved()// true
 * $s->isNew()// false
 *
 * $s->name = "Franck";
 *
 * $s->isSaved()// true
 * $s->isNew()// false
 *
 * $s->name = "Jack";
 *
 * $s->isSaved()// false
 * $s->isNew()// false
 * ```
 */
class ORMEntityBase extends ArrayCapable
{
	/** @var \Gobl\DBAL\Table */
	protected $oeb_table;

	/** @var bool */
	protected $oeb_is_new;

	/** @var bool */
	protected $oeb_is_saved;

	/**
	 * To enable/disable strict mode.
	 *
	 * @var bool
	 */
	protected $oeb_strict;

	/**
	 * The auto_increment column full name.
	 *
	 * @var string
	 */
	protected $oeb_auto_increment_column;

	/** @var string */
	protected $oeb_table_name;

	/** @var string */
	protected $oeb_table_query_class;

	protected $oeb_db;

	/** @var array */
	private $oeb_row = [];

	/** @var array */
	private $oeb_row_saved = [];

	/**
	 * ORMEntityBase constructor.
	 *
	 * @param \Gobl\DBAL\Db $db                the database
	 * @param bool          $is_new            true for new entity, false for entity fetched
	 *                                         from the database, default is true
	 * @param bool          $strict            enable/disable strict mode
	 * @param string        $table_name        the table name
	 * @param string        $table_query_class the table query's fully qualified class name
	 */
	protected function __construct(Db $db, $is_new, $strict, $table_name, $table_query_class)
	{
		$this->oeb_db                = $db;
		$this->oeb_table_name        = $table_name;
		$this->oeb_table_query_class = $table_query_class;
		$this->oeb_table             = $this->oeb_db->getTable($table_name);
		$columns                     = $this->oeb_table->getColumns();
		$this->oeb_is_new            = (bool) $is_new;
		$this->oeb_is_saved          = !$this->oeb_is_new;
		$this->oeb_strict            = (bool) $strict;

		foreach ($columns as $column) {
			$full_name = $column->getFullName();
			$type      = $column->getTypeObject();

			if ($this->oeb_is_new) {
				$this->oeb_row[$full_name] = $type->getDefault();
			}

			if ($type->isAutoIncremented()) {
				$this->oeb_auto_increment_column = $full_name;
			}
		}
	}

	/**
	 * Destructor.
	 */
	public function __destruct()
	{
		$this->oeb_table = null;
	}

	/**
	 * To check if this entity is new
	 *
	 * @return bool
	 */
	public function isNew()
	{
		return $this->oeb_is_new;
	}

	/**
	 * To check if this entity is saved
	 *
	 * @param bool $save if true the entity will be considered as saved
	 *
	 * @return bool
	 */
	public function isSaved($save = false)
	{
		if ($save === true) {
			$this->oeb_row_saved = \array_replace($this->oeb_row_saved, $this->oeb_row);
			$this->oeb_is_new    = false;
			$this->oeb_is_saved  = true;
		}

		return $this->oeb_is_saved;
	}

	/**
	 * Save modifications to database.
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 * @throws \Gobl\ORM\Exceptions\ORMException
	 *
	 * @return int|string return `int` for affected row count on update, string for last insert id, 0 when nothing
	 *                    is done
	 */
	public function save()
	{
		if ($this->isNew()) {
			// add
			$ai_column = $this->oeb_auto_increment_column;

			if (!empty($ai_column)) {
				$ai_column_value = $this->oeb_row[$ai_column];

				if (null !== $ai_column_value) {
					throw new ORMException(\sprintf('Auto increment column "%s" should be set to null.', $ai_column));
				}
			}

			$columns = \array_keys($this->oeb_row);
			$values  = \array_values($this->oeb_row);
			$qb      = new QueryBuilder($this->oeb_db);
			$qb->insert()
			   ->into($this->oeb_table->getFullName(), $columns)
			   ->values($values);

			$result = $qb->execute();

			if (!empty($ai_column)) {
				if (\is_string($result)) {
					$this->oeb_row[$ai_column] = $result;
					$returns                   = $result; // last insert id
				} else {
					throw new ORMException(\sprintf(
						'Unable to get last insert id for column "%s" in table "%s"',
						$ai_column,
						$this->oeb_table->getName()
					));
				}
			} else {
				$returns = (int) $result; // one row saved
			}
		} elseif (!$this->isSaved() && !empty($this->oeb_row_saved)) {
			// update
			$class_name = $this->oeb_table_query_class;
			/** @var \Gobl\ORM\ORMTableQueryBase $tqb */
			$tqb     = new $class_name();
			$returns = $tqb->safeUpdate($this->oeb_row_saved, $this->oeb_row)
						   ->execute();
		} else {
			// nothing to do
			$returns = 0;
		}

		// we set this entity as saved
		$this->isSaved(true);

		return $returns;
	}

	/**
	 * Hydrate this entity with values from an array.
	 *
	 * @param array $row map column name to column value
	 *
	 * @return $this
	 */
	public function hydrate(array $row)
	{
		foreach ($row as $column_name => $value) {
			$this->$column_name = $value;
		}

		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function asArray($hide_private_column = true)
	{
		$row = $this->oeb_row;

		if ($hide_private_column) {
			$privates_columns = $this->oeb_table->getPrivatesColumns();

			foreach ($privates_columns as $column) {
				unset($row[$column->getFullName()]);
			}
		}

		return $row;
	}

	/**
	 * Sets a column value.
	 *
	 * @param string $name  the column name or full name
	 * @param mixed  $value the column new value
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
	 *
	 * @return mixed
	 */
	protected function doValidation($name, $value)
	{
		$column    = $this->oeb_table->getColumn($name);
		$full_name = $column->getFullName();
		$type      = $column->getTypeObject();

		if ($this->oeb_row[$full_name] !== $value) {
			try {
				$value = $type->validate($value, $column->getName(), $this->oeb_table->getName());
			} catch (TypesInvalidValueException $e) {
				// sensitive data are prefixed
				$prefix = GoblBaseException::SENSITIVE_DATA_PREFIX;

				$debug = \array_replace($e->getData(), [
					'field'                => $full_name,
					$prefix . 'table_name' => $this->oeb_table->getName(),
					$prefix . 'options'    => $type->getCleanOptions(),
				]);

				$e->setData($debug);

				throw $e;
			}
		}

		return $value;
	}

	/**
	 * Magic setter for column value.
	 *
	 * @param string $name  the column full name or name
	 * @param mixed  $value the column value
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
	 */
	public function __set($name, $value)
	{
		if ($this->oeb_table->hasColumn($name)) {
			$full_name = $this->oeb_table->getColumn($name)
										 ->getFullName();

			// false when we are hydrated by PDO
			if ($this->isNew() || \array_key_exists($full_name, $this->oeb_row_saved)) {
				if (!\array_key_exists($full_name, $this->oeb_row) || $this->oeb_row[$full_name] !== $value) {
					$this->oeb_row[$full_name] = $this->doValidation($full_name, $value);
					$this->oeb_is_saved        = false;
				}
			} else { // we are hydrated by PDO
				$this->oeb_row[$full_name]       = $value;
				$this->oeb_row_saved[$full_name] = $value;
			}
		} elseif ($this->oeb_strict) {
			$trace = \debug_backtrace(\DEBUG_BACKTRACE_PROVIDE_OBJECT, 2);
			$error = \sprintf(
				'Could not set value, column "%s" is not defined in table "%s". Found in "%s" on line %s.',
				$name,
				$this->oeb_table->getName(),
				$trace[0]['file'],
				$trace[0]['line']
			);

			\trigger_error($error, \E_USER_ERROR);
		}
	}

	/**
	 * Magic getter for column value.
	 *
	 * @param string $name the column full name or name
	 *
	 * @return null|mixed
	 */
	public function __get($name)
	{
		if ($this->oeb_table->hasColumn($name)) {
			$full_name = $this->oeb_table->getColumn($name)
										 ->getFullName();

			return isset($this->oeb_row[$full_name]) ? $this->oeb_row[$full_name] : null;
		}

		return null;
	}

	/**
	 * Help var_dump().
	 *
	 * @return array
	 */
	public function __debugInfo()
	{
		return ['instance_of' => static::class, 'data' => static::asArray()];
	}
}
