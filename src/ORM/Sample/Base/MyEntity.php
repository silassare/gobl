<?php
//__GOBL_HEAD_COMMENT__

	namespace MY_PROJECT_NS\Base;

	use Gobl\DBAL\QueryBuilder;
	use Gobl\ORM\ArrayCapable;
	use Gobl\ORM\Exceptions\ORMException;
	use Gobl\ORM\ORM;
	use MY_PROJECT_NS\MyTableQuery as MyTableQueryReal;

//__GOBL_RELATIONS_USE_CLASS__

	/**
	 * Class MyEntity
	 *
	 * @package MY_PROJECT_NS\Base
	 */
	abstract class MyEntity extends ArrayCapable
	{
//__GOBL_COLUMNS_CONSTANTS__
		/** @var \Gobl\DBAL\Table */
		protected $table;

		/** @var  array */
		protected $row;

		/** @var  array */
		protected $row_saved;

		/**
		 * @var bool
		 */
		protected $is_new = true;

		/**
		 * @var bool
		 */
		protected $saved = false;

		/**
		 * @var bool
		 */
		protected $modified = false;

		/**
		 * The auto_increment column full name.
		 *
		 * @var string
		 */
		protected $auto_increment_column = null;

//__GOBL_RELATIONS_PROPERTIES__

		/**
		 * MyEntity constructor.
		 *
		 * @param bool $is_new True for new entity false for entity fetched
		 *                     from the database, default is true.
		 */
		public function __construct($is_new = true)
		{
			$this->table  = ORM::getDatabase()
							   ->getTable('my_table');
			$columns      = $this->table->getColumns();
			$this->is_new = (bool)$is_new;

			// we initialise row with default value
			foreach ($columns as $column) {
				$full_name             = $column->getFullName();
				$this->row[$full_name] = $column->getDefaultValue();

				// the auto_increment column
				if ($column->isAutoIncrement()) {
					$this->auto_increment_column = $full_name;
				}
			}
		}
//__GOBL_RELATIONS_GETTERS__
//__GOBL_ENTITY_COLUMNS_SETTERS_GETTERS__
		/**
		 * Hydrate, load, populate this entity with values from an array.
		 *
		 * @param array $row map column name to column value
		 *
		 * @return $this|\MY_PROJECT_NS\MyEntity
		 */
		public function hydrate(array $row)
		{
			foreach ($row as $column_name => $value) {
				$this->_setValue($column_name, $value);
			}

			return $this;
		}

		/**
		 * To check if this entity is modified
		 *
		 * @return bool
		 */
		public function isModified()
		{
			return $this->modified;
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
			return $this->saved;
		}

		/**
		 * Saves modifications to database.
		 *
		 * @return int|string int for affected row count on update, string for last insert id
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function save()
		{
			if ($this->isSaved()) {
				if ($this->isModified()) {
					// update
					$t       = new MyTableQueryReal();
					$returns = $t->safeUpdate($this->row_saved, $this->row);
				} else {
					$returns = 0;
				}
			} else {
				// add
				$columns = array_keys($this->row);
				$values  = array_values($this->row);
				$qb      = new QueryBuilder(ORM::getDatabase());
				$qb->insert()
				   ->into($this->table->getFullName(), $columns)
				   ->values($values);

				$result    = $qb->execute();
				$ai_column = $this->auto_increment_column;

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
			}

			$this->row_saved = $this->row;
			$this->is_new    = false;
			$this->saved     = true;
			$this->modified  = false;

			return $returns;
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
		 * Sets a column value.
		 *
		 * @param string $name  the column name or full name.
		 * @param mixed  $value the column new value.
		 *
		 * @return $this|\MY_PROJECT_NS\MyEntity
		 */
		protected function _setValue($name, $value)
		{
			if ($this->table->hasColumn($name)) {
				$column    = $this->table->getColumn($name);
				$value     = $column->getTypeObject()
									->validate($value);
				$full_name = $column->getFullName();
				if ($this->row[$full_name] !== $value) {
					$this->row[$full_name] = $value;
					$this->modified        = true;
					$this->saved           = false;
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
		public function __set($full_name, $value)
		{
			if (!$this->isNew()) {
				if ($this->table->hasColumn($full_name)) {
					$this->row[$full_name]       = $value;
					$this->row_saved[$full_name] = $value;
					$this->modified              = false;
				} else {
					throw new ORMException(sprintf('Could not set column "%s", not defined in table "%s".', $full_name, $this->table->getName()));
				}
			} else {
				throw new ORMException(sprintf('You should not try to manually set property for "%s".', $full_name, get_class($this)));
			}
		}

		/**
		 * {@inheritdoc}
		 */
		public function asArray()
		{
			return $this->row;
		}
	}