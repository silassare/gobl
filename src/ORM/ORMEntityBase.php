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

	use Gobl\DBAL\QueryBuilder;
	use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
	use Gobl\ORM\Exceptions\ORMException;

	abstract class ORMEntityBase extends ArrayCapable
	{
		/** @var \Gobl\DBAL\Table */
		protected $table;

		/** @var  array */
		protected $row;

		/** @var  array */
		protected $row_saved;

		/**@var bool */
		protected $is_new;

		/** @var bool */
		protected $is_saved;

		/**
		 * To enable/disable strict mode.
		 *
		 * @var bool
		 */
		protected $strict;

		/**
		 * The auto_increment column full name.
		 *
		 * @var string
		 */
		protected $auto_increment_column = null;

		/** @var string */
		protected $table_name;

		/**
		 * ORMEntityBase constructor.
		 *
		 * @param string $table_name The table name.
		 * @param bool   $is_new     True for new entity false for entity fetched
		 *                           from the database, default is true.
		 * @param bool   $strict     Enable/disable strict mode
		 *
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		protected function __construct($table_name, $is_new, $strict)
		{
			$this->table_name = $table_name;
			$this->table      = ORM::getDatabase()
								   ->getTable($table_name);
			$columns          = $this->table->getColumns();
			$this->is_new     = (bool)$is_new;
			$this->is_saved   = !$this->is_new;
			$this->strict     = (bool)$strict;

			// we initialise row with default value
			foreach ($columns as $column) {
				$full_name             = $column->getFullName();
				$type                  = $column->getTypeObject();
				$this->row[$full_name] = $type->getDefault();

				// the auto_increment column
				if ($type->isAutoIncremented()) {
					$this->auto_increment_column = $full_name;
				}
			}
		}

		/**
		 * Destructor.
		 */
		public function __destruct()
		{
			$this->table = null;
		}

		/**
		 * Hydrate this entity with values from an array.
		 *
		 * @param array $row map column name to column value
		 *
		 * @return $this
		 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
		 */
		public function hydrate(array $row)
		{
			foreach ($row as $column_name => $value) {
				$this->_setValue($column_name, $value);
			}

			return $this;
		}

		/**
		 * Gets a column value.
		 *
		 * @param string $name the column name or full name.
		 *
		 * @return mixed
		 */
		protected function _getValue($name)
		{
			if ($this->table->hasColumn($name)) {
				$column    = $this->table->getColumn($name);
				$full_name = $column->getFullName();

				return $this->row[$full_name];
			}

			return null;
		}

		/**
		 * To check if this entity is new
		 *
		 * @return bool
		 */
		public function isNew()
		{
			return $this->is_new;
		}

		/**
		 * To check if this entity is saved
		 *
		 * @return bool
		 */
		public function isSaved()
		{
			return $this->is_saved;
		}

		/**
		 * Saves modifications to database.
		 *
		 * @return int|string int for affected row count on update, string for last insert id
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function save()
		{
			if ($this->isNew()) {
				// add
				$ai_column = $this->auto_increment_column;

				if (!empty($ai_column)) {
					$ai_column_value = $this->row[$ai_column];

					if (!is_null($ai_column_value)) {
						throw new ORMException(sprintf('Auto increment column "%s" should be set to null.', $ai_column));
					}
				}

				$columns = array_keys($this->row);
				$values  = array_values($this->row);
				$qb      = new QueryBuilder(ORM::getDatabase());
				$qb->insert()
				   ->into($this->table->getFullName(), $columns)
				   ->values($values);

				$result = $qb->execute();

				if (!empty($ai_column)) {
					if (is_string($result)) {
						$this->row[$ai_column] = $result;
						$returns               = $result; // last insert id
					} else {
						throw new ORMException(sprintf('Unable to get last insert id for column "%s" in table "%s"', $ai_column, $this->table->getName()));
					}
				} else {
					$returns = intval($result); // one row saved
				}
			} elseif (!$this->isSaved() AND isset($this->row_saved)) {
				// update
				$t       = $this->entityTableQuery();
				$returns = $t->safeUpdate($this->row_saved, $this->row)
							 ->execute();
			} else {
				// nothing to do
				$returns = 0;
			}

			$this->row_saved = $this->row;
			$this->is_new    = false;
			$this->is_saved  = true;

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
		protected function _setValue($name, $value)
		{
			if ($this->table->hasColumn($name)) {
				$column = $this->table->getColumn($name);
				$type   = $column->getTypeObject();

				try {
					$value = $type->validate($value, $column->getName(), $this->table->getName());
				} catch (TypesInvalidValueException $e) {
					$debug = [
						"field"      => $column->getFullName(),
						"table_name" => $this->table->getName(),
						"options"    => $type->getCleanOptions()
					];

					$e->setDebugData($debug);

					throw $e;
				}

				$full_name = $column->getFullName();
				if ($this->row[$full_name] !== $value) {
					$this->row[$full_name] = $value;
					$this->is_saved        = false;
				}
			}

			return $this;
		}

		/**
		 * Magic setter for row fetched as class.
		 *
		 * @param string $full_name the column full name
		 * @param mixed  $value     the column value
		 *
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		final public function __set($full_name, $value)
		{
			if ($this->isNew()) {
				throw new ORMException(sprintf('You should not try to manually set properties on "%s" use appropriate getters and setters.', get_class($this)));
			}

			if ($this->table->hasColumn($full_name)) {
				$this->row[$full_name]       = $value;
				$this->row_saved[$full_name] = $value;
				$this->is_saved              = true;
			} elseif ($this->strict) {
				throw new ORMException(sprintf('Could not set column "%s", not defined in table "%s".', $full_name, $this->table->getName()));
			}
		}

		/**
		 * {@inheritdoc}
		 */
		public function asArray($hide_private_column = true)
		{
			$row = $this->row;

			if ($hide_private_column) {
				$privates_columns = $this->table->getPrivatesColumns();

				foreach ($privates_columns as $column) {
					unset($row[$column->getFullName()]);
				}
			}

			return $row;
		}

		/**
		 * Return the entity table instance.
		 *
		 * @return \Gobl\ORM\ORMTableQueryBase
		 */
		abstract public function entityTableQuery();

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