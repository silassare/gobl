<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
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
	 *
	 * @package Gobl\ORM
	 */
	class ORMEntityBase extends ArrayCapable
	{
		/** @var array */
		private $_row = [];

		/** @var array */
		private $_row_saved = [];

		/** @var \Gobl\DBAL\Table */
		protected $_table;

		/**@var bool */
		protected $_is_new;

		/** @var bool */
		protected $_is_saved;

		/**
		 * To enable/disable strict mode.
		 *
		 * @var bool
		 */
		protected $_strict;

		/**
		 * The auto_increment column full name.
		 *
		 * @var string
		 */
		protected $_auto_increment_column = null;

		/** @var string */
		protected $_table_name;

		/** @var string */
		protected $_table_query_class;

		protected $_db;

		/**
		 * ORMEntityBase constructor.
		 *
		 * @param \Gobl\DBAL\Db $db                The database.
		 * @param bool          $is_new            True for new entity, false for entity fetched
		 *                                         from the database, default is true.
		 * @param bool          $strict            Enable/disable strict mode.
		 * @param string        $table_name        The table name.
		 * @param string        $table_query_class The table query's fully qualified class name.
		 */
		protected function __construct(Db $db, $is_new, $strict, $table_name, $table_query_class)
		{
			$this->_db                = $db;
			$this->_table_name        = $table_name;
			$this->_table_query_class = $table_query_class;
			$this->_table             = $this->_db->getTable($table_name);
			$columns                  = $this->_table->getColumns();
			$this->_is_new            = (bool)$is_new;
			$this->_is_saved          = !$this->_is_new;
			$this->_strict            = (bool)$strict;

			foreach ($columns as $column) {
				$full_name = $column->getFullName();
				$type      = $column->getTypeObject();

				if ($this->_is_new) {
					$this->_row[$full_name] = $type->getDefault();
				}

				if ($type->isAutoIncremented()) {
					$this->_auto_increment_column = $full_name;
				}
			}
		}

		/**
		 * Destructor.
		 */
		public function __destruct()
		{
			$this->_table = null;
		}

		/**
		 * To check if this entity is new
		 *
		 * @return bool
		 */
		public function isNew()
		{
			return $this->_is_new;
		}

		/**
		 * To check if this entity is saved
		 *
		 * @param bool $save If true the entity will be considered as saved.
		 *
		 * @return bool
		 */
		public function isSaved($save = false)
		{
			if ($save === true) {
				$this->_row_saved = array_replace($this->_row_saved, $this->_row);
				$this->_is_new    = false;
				$this->_is_saved  = true;
			}

			return $this->_is_saved;
		}

		/**
		 * Save modifications to database.
		 *
		 * @return int|string return `int` for affected row count on update, string for last insert id, 0 when nothing
		 *                    is done
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function save()
		{
			if ($this->isNew()) {
				// add
				$ai_column = $this->_auto_increment_column;

				if (!empty($ai_column)) {
					$ai_column_value = $this->_row[$ai_column];

					if (!is_null($ai_column_value)) {
						throw new ORMException(sprintf('Auto increment column "%s" should be set to null.', $ai_column));
					}
				}

				$columns = array_keys($this->_row);
				$values  = array_values($this->_row);
				$qb      = new QueryBuilder($this->_db);
				$qb->insert()
				   ->into($this->_table->getFullName(), $columns)
				   ->values($values);

				$result = $qb->execute();

				if (!empty($ai_column)) {
					if (is_string($result)) {
						$this->_row[$ai_column] = $result;
						$returns                = $result; // last insert id
					} else {
						throw new ORMException(sprintf('Unable to get last insert id for column "%s" in table "%s"', $ai_column, $this->_table->getName()));
					}
				} else {
					$returns = intval($result); // one row saved
				}
			} elseif (!$this->isSaved() AND !empty($this->_row_saved)) {
				// update
				$class_name = $this->_table_query_class;
				/** @var \Gobl\ORM\ORMTableQueryBase $tqb */
				$tqb     = new $class_name;
				$returns = $tqb->safeUpdate($this->_row_saved, $this->_row)
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
		 * Sets a column value.
		 *
		 * @param string $name  the column name or full name.
		 * @param mixed  $value the column new value.
		 *
		 * @return mixed
		 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
		 */
		protected function doValidation($name, $value)
		{
			$column    = $this->_table->getColumn($name);
			$full_name = $column->getFullName();
			$type      = $column->getTypeObject();

			if ($this->_row[$full_name] !== $value) {
				try {
					$value = $type->validate($value, $column->getName(), $this->_table->getName());
				} catch (TypesInvalidValueException $e) {
					// sensitive data are prefixed
					$prefix = GoblBaseException::SENSITIVE_DATA_PREFIX;

					$debug = array_replace($e->getData(), [
						'field'                => $full_name,
						$prefix . 'table_name' => $this->_table->getName(),
						$prefix . 'options'    => $type->getCleanOptions()
					]);

					$e->setData($debug);

					throw $e;
				}
			}

			return $value;
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
		 * Magic setter for column value.
		 *
		 * @param string $name  the column full name or name
		 * @param mixed  $value the column value
		 *
		 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
		 */
		public function __set($name, $value)
		{
			if ($this->_table->hasColumn($name)) {
				$full_name = $this->_table->getColumn($name)
										  ->getFullName();

				// false when we are hydrated by PDO
				if ($this->isNew() OR array_key_exists($full_name, $this->_row_saved)) {
					if (!array_key_exists($full_name, $this->_row) OR $this->_row[$full_name] !== $value) {
						$this->_row[$full_name] = $this->doValidation($full_name, $value);
						$this->_is_saved        = false;
					}
				} else { // we are hydrated by PDO
					$this->_row[$full_name]       = $value;
					$this->_row_saved[$full_name] = $value;
				}
			} elseif ($this->_strict) {
				$trace = debug_backtrace();
				$error = sprintf(
					'Could not set column "%s" value, undefined in table "%s". In "%s" on line %s.',
					$name, $this->_table->getName(),
					$trace[0]['file'], $trace[0]['line']);

				trigger_error($error, E_USER_NOTICE);
			}
		}

		/**
		 * Magic getter for column value.
		 *
		 * @param string $name the column full name or name
		 *
		 * @return mixed|null
		 */
		public function __get($name)
		{
			if ($this->_table->hasColumn($name)) {
				$full_name = $this->_table->getColumn($name)
										  ->getFullName();

				return isset($this->_row[$full_name]) ? $this->_row[$full_name] : null;
			}

			return null;
		}

		/**
		 * @inheritdoc
		 */
		public function asArray($hide_private_column = true)
		{
			$row = $this->_row;

			if ($hide_private_column) {
				$privates_columns = $this->_table->getPrivatesColumns();

				foreach ($privates_columns as $column) {
					unset($row[$column->getFullName()]);
				}
			}

			return $row;
		}

		/**
		 * Help var_dump().
		 *
		 * @return array
		 */
		public function __debugInfo()
		{
			return ['instance_of' => static::class, "data" => static::asArray()];
		}
	}