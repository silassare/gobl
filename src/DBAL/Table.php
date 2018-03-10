<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the Gobl package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace Gobl\DBAL;

	use Gobl\DBAL\Constraints\ForeignKey;
	use Gobl\DBAL\Constraints\PrimaryKey;
	use Gobl\DBAL\Constraints\Unique;
	use Gobl\DBAL\Exceptions\DBALException;
	use Gobl\DBAL\Relations\Relation;

	/**
	 * Class Table
	 *
	 * @package Gobl\DBAL
	 */
	class Table
	{
		const NAME_REG   = '#^(?:[a-zA-Z][a-zA-Z0-9_]*[a-zA-Z0-9]|[a-zA-Z])$#';
		const PREFIX_REG = '#^(?:[a-zA-Z][a-zA-Z0-9_]*[a-zA-Z0-9]|[a-zA-Z])$#';

		/**
		 * The table name.
		 *
		 * @var string
		 */
		protected $name;

		/**
		 * The table plural name.
		 *
		 * @var string
		 */
		protected $plural_name;

		/**
		 * The table singular name.
		 *
		 * @var string
		 */
		protected $singular_name;

		/**
		 * The table prefix.
		 *
		 * @var null|string
		 */
		protected $prefix;

		/**
		 * The table namespace.
		 *
		 * @var string
		 */
		protected $namespace;

		/**
		 * The table columns.
		 *
		 * @var \Gobl\DBAL\Column[]
		 */
		protected $columns = [];

		/**
		 * @var array
		 */
		protected $col_full_name_map = [];

		/**
		 * Foreign keys constraints.
		 *
		 * @var \Gobl\DBAL\Constraints\ForeignKey[]
		 */
		protected $fk_constraints = [];

		/**
		 * Primary key constraint.
		 *
		 * @var \Gobl\DBAL\Constraints\PrimaryKey
		 */
		protected $pk_constraint = null;

		/**
		 * Unique constraints.
		 *
		 * @var \Gobl\DBAL\Constraints\Unique[]
		 */
		protected $uc_constraints = [];

		/**
		 * Constraints counter, used to generate
		 * named constraints.
		 *
		 * @var int
		 */
		protected $constraints_counter = 1;

		/**
		 * Table relations list
		 *
		 * @var \Gobl\DBAL\Relations\Relation[]
		 */
		protected $relations = [];

		/**
		 * Table constructor.
		 *
		 * Plural and singular class name are used to generate
		 * table custom class and row class.
		 * Their are used to be concise and ide friendly.
		 * Example:
		 *    table name     ------> users
		 *    plural class   ------> Users
		 *    singular class ------> User
		 *
		 * @param string      $name          the table name
		 * @param string      $plural_name   the plural name
		 * @param string      $singular_name the singular name
		 * @param string      $namespace     the table namespace
		 * @param string|null $prefix        the table prefix
		 *
		 * @throws \InvalidArgumentException
		 */
		public function __construct($name, $plural_name, $singular_name, $namespace, $prefix = null)
		{
			if (!preg_match(Table::NAME_REG, $name))
				throw new \InvalidArgumentException(sprintf('Invalid table name "%s".', $name));

			if (!is_string($namespace) OR empty($namespace))
				throw new \InvalidArgumentException(sprintf('You should provide namespace for table "%s".', $name));

			if (!is_null($prefix)) {
				if (!preg_match(Table::PREFIX_REG, $prefix))
					throw new \InvalidArgumentException(sprintf('Invalid table prefix name "%s".', $prefix));
			}

			if (empty($plural_name) OR empty($singular_name)) {
				throw new \InvalidArgumentException(sprintf('Plural and singular name for table "%s" should not be empty.', $name));
			}

			if ($plural_name === $singular_name) {
				throw new \InvalidArgumentException(sprintf('Plural and singular name for table "%s" should not be the same.', $name));
			}

			$this->name          = strtolower($name);
			$this->prefix        = $prefix;
			$this->plural_name   = $plural_name;
			$this->singular_name = $singular_name;
			$this->namespace     = $namespace;
		}

		/**
		 * Adds a given column to the current table.
		 *
		 * @param \Gobl\DBAL\Column $column the column to add
		 *
		 * @return $this
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function addColumn(Column $column)
		{
			$name      = $column->getName();
			$full_name = $column->getFullName();

			if ($this->hasColumn($name)) {
				throw new DBALException(sprintf('The column "%s" is already defined in table "%s".', $name, $this->name));
			}

			// prevent column full name conflict
			if ($this->hasColumn($full_name)) {
				$c = $this->col_full_name_map[$full_name];
				throw new DBALException(sprintf('The columns "%s" and "%s" has the same full name "%s" in table "%s".', $name, $c, $full_name, $this->getName()));
			}

			$this->columns[$name]                = $column;
			$this->col_full_name_map[$full_name] = $name;

			return $this;
		}

		/**
		 * Adds relation to this table.
		 *
		 * @param \Gobl\DBAL\Relations\Relation $relation
		 *
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function addRelation(Relation $relation)
		{
			$name = $relation->getName();

			if ($this->hasRelation($name)) {
				throw new DBALException(sprintf('Cannot override relation "%s" in table "%s".', $name, $this->getName()));
			}

			if ($this->hasColumn($name)) {
				throw new DBALException(sprintf('Cannot use "%s" as relation name, column "%s" exists in table "%s".', $name, $name, $this->getName()));
			}

			$master_name = $relation->getMasterTable()
									->getName();
			$slave_name  = $relation->getSlaveTable()
									->getName();
			if ($master_name !== $this->name AND $slave_name !== $this->name) {
				throw new DBALException(sprintf('Trying to add relation "%s" between ("%s","%s") to table "%s".', $relation->getName(), $master_name, $slave_name, $this->name));
			}

			$this->relations[$name] = $relation;
		}

		/**
		 * Adds a unique constraint on columns.
		 *
		 * @param array $columns the columns
		 *
		 * @return $this
		 */
		public function addUniqueConstraint(array $columns)
		{
			if (count($columns)) {
				$count           = count($this->uc_constraints) + 1;
				$constraint_name = sprintf('uc_%s_%d', $this->getFullName(), $count);
				$uc              = new Unique($constraint_name, $this);

				foreach ($columns as $column_name) {
					$uc->addColumn($column_name);
				}

				$this->uc_constraints[$constraint_name] = $uc;
			}

			return $this;
		}

		/**
		 * Adds a primary key constraint on columns.
		 *
		 * @param array $columns the columns
		 *
		 * @return $this
		 */
		public function addPrimaryKeyConstraint(array $columns)
		{
			if (count($columns)) {
				if (!isset($this->pk_constraint)) {
					$constraint_name     = sprintf('pk_%s', $this->getFullName());
					$this->pk_constraint = new PrimaryKey($constraint_name, $this);
				}

				foreach ($columns as $column_name) {
					$this->pk_constraint->addColumn($column_name);
				}
			}

			return $this;
		}

		/**
		 * Adds a foreign key constraint on columns.
		 *
		 * @param \Gobl\DBAL\Table $reference_table the reference table
		 * @param array            $columns         the columns
		 * @param int              $update_action   the reference column update action
		 * @param int              $delete_action   the reference column delete action
		 *
		 * @return $this
		 */
		public function addForeignKeyConstraint(Table $reference_table, array $columns, $update_action, $delete_action)
		{
			if (count($columns)) {
				$constraint_name = sprintf('fk_%s_%s', $this->getName(), $reference_table->getName());

				if (!isset($this->fk_constraints[$constraint_name])) {
					$this->fk_constraints[$constraint_name] = new ForeignKey($constraint_name, $this, $reference_table);
				}

				$fk = $this->fk_constraints[$constraint_name];

				foreach ($columns as $column_name => $reference_column) {
					$fk->addColumn($column_name, $reference_column);
				}

				$fk->setUpdateAction($update_action);
				$fk->setDeleteAction($delete_action);
			}

			return $this;
		}

		/**
		 * Gets table name.
		 *
		 * @return string
		 */
		public function getName()
		{
			return $this->name;
		}

		/**
		 * Gets the plural name.
		 *
		 * @return string
		 */
		public function getPluralName()
		{
			return $this->plural_name;
		}

		/**
		 * Gets the singular name.
		 *
		 * @return string
		 */
		public function getSingularName()
		{
			return $this->singular_name;
		}

		/**
		 * Gets table prefix.
		 *
		 * @return string
		 */
		public function getPrefix()
		{
			return $this->prefix;
		}

		/**
		 * Gets table namespace.
		 *
		 * @return string
		 */
		public function getNamespace()
		{
			return $this->namespace;
		}

		/**
		 * Gets table full name.
		 *
		 * @return string
		 */
		public function getFullName()
		{
			if (empty($this->prefix))
				return $this->name;

			return $this->prefix . '_' . $this->name;
		}

		/**
		 * Gets columns.
		 *
		 * @return \Gobl\DBAL\Column[]
		 */
		public function getColumns()
		{
			return $this->columns;
		}

		/**
		 * Checks if a given column is defined.
		 *
		 * @param string $name the column name or full name
		 *
		 * @return bool
		 */
		public function hasColumn($name)
		{
			return isset($this->columns[$name]) OR isset($this->col_full_name_map[$name]);
		}

		/**
		 * Asserts if a given column name is defined.
		 *
		 * @param string $name the column name or full name
		 *
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function assertHasColumn($name)
		{
			if (!$this->hasColumn($name)) {
				throw new DBALException(sprintf('The column "%s" does not exists in the table "%s".', $name, $this->getName()));
			}
		}

		/**
		 * Gets column with a given name.
		 *
		 * @param string $name the column name or full name
		 *
		 * @return \Gobl\DBAL\Column
		 */
		public function getColumn($name)
		{
			if ($this->hasColumn($name)) {
				if (isset($this->col_full_name_map[$name])) {
					$name = $this->col_full_name_map[$name];
				}

				return $this->columns[$name];
			}

			return null;
		}

		/**
		 * Checks if a given relation is defined.
		 *
		 * @param string $name the relation name
		 *
		 * @return bool
		 */
		public function hasRelation($name)
		{
			return isset($this->relations[$name]);
		}

		/**
		 * Gets a relation by name.
		 *
		 * @param string $name the relation name
		 *
		 * @return null|\Gobl\DBAL\Relations\Relation
		 */
		public function getRelation($name)
		{
			if ($this->hasRelation($name)) {
				return $this->relations[$name];
			}

			return null;
		}

		/**
		 * Gets relations.
		 *
		 * @return \Gobl\DBAL\Relations\Relation[]
		 */
		public function getRelations()
		{
			return $this->relations;
		}

		/**
		 * Gets unique constraints.
		 *
		 * @return \Gobl\DBAL\Constraints\Unique[]
		 */
		public function getUniqueConstraints()
		{
			return $this->uc_constraints;
		}

		/**
		 * Gets primary key constraint.
		 *
		 * @return null|\Gobl\DBAL\Constraints\PrimaryKey
		 */
		public function getPrimaryKeyConstraint()
		{
			return $this->pk_constraint;
		}

		/**
		 * Gets foreign key constraints.
		 *
		 * @return \Gobl\DBAL\Constraints\ForeignKey[]
		 */
		public function getForeignKeyConstraints()
		{
			return $this->fk_constraints;
		}

		/**
		 * Gets foreign key constraint that have a given reference table.
		 *
		 * @param \Gobl\DBAL\Table $reference the reference table
		 *
		 * @return \Gobl\DBAL\Constraints\ForeignKey
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function getForeignKeyConstraintFrom(Table $reference)
		{
			$fk_name = sprintf('fk_%s_%s', $this->getName(), $reference->getName());
			if (isset($this->fk_constraints[$fk_name])) {
				return $this->fk_constraints[$fk_name];
			} else {
				throw new DBALException(sprintf('Foreign key from table "%s" not found in table "%s".', $reference->getName(), $this->name));
			}
		}

		/**
		 * Check if the current table has foreign key that refer
		 * to columns in the reference table.
		 *
		 * @param \Gobl\DBAL\Table $reference the reference table
		 * @param array            $columns   the foreign columns
		 *
		 * @return bool
		 */
		public function hasForeignColumns(Table $reference, array $columns)
		{
			$fk_name = sprintf('fk_%s_%s', $this->getName(), $reference->getName());
			$x       = count($columns);
			if ($x) {
				if (isset($this->fk_constraints[$fk_name])) {
					$fk         = $this->fk_constraints[$fk_name];
					$fk_columns = array_flip($fk->getConstraintColumns());
					$y          = 0;
					foreach ($columns as $column) {
						if (isset($fk_columns[$column])) {
							$y++;
						}
					}

					return $x === $y;
				}
			}

			return false;
		}

		/**
		 * Check if the table has foreign key constraint with column from a given reference table.
		 *
		 * @param \Gobl\DBAL\Table $reference the reference table
		 *
		 * @return bool
		 */
		public function hasForeignKeyConstraint(Table $reference)
		{
			$fk_name = sprintf('fk_%s_%s', $this->getName(), $reference->getName());

			return isset($this->fk_constraints[$fk_name]);
		}

		/**
		 * Check if the table has primary key constraint.
		 *
		 * @return bool
		 */
		public function hasPrimaryKeyConstraint()
		{
			return !is_null($this->pk_constraint);
		}

		/**
		 * Check if the table has unique constraint.
		 *
		 * @return bool
		 */
		public function hasUniqueConstraint()
		{
			return count($this->uc_constraints);
		}

		/**
		 * Check if a given columns list are the primary key of this table.
		 *
		 * @param array $columns columns full name list
		 *
		 * @return bool
		 */
		public function isPrimaryKey(array $columns)
		{
			$x = count($columns);
			if ($x AND $this->hasPrimaryKeyConstraint()) {
				$pk_columns = $this->pk_constraint->getConstraintColumns();
				$y          = count($pk_columns);

				return ($x === $y AND !count(array_diff($pk_columns, $columns)));
			}

			return false;
		}

		/**
		 * Check if a given columns list are foreign key in this table.
		 *
		 * @param array $columns columns full name list
		 *
		 * @return bool
		 */
		public function isForeignKey(array $columns)
		{
			$x = count($columns);
			if ($x) {
				foreach ($this->fk_constraints as $fk) {
					$fk_columns = array_keys($fk->getConstraintColumns());

					$y = count($fk_columns);
					if ($x === $y AND !count(array_diff($fk_columns, $columns))) {
						return true;
					}
				}
			}

			return false;
		}

		/**
		 * Check if a given columns list are unique in this table.
		 *
		 * @param array $columns columns full name list
		 *
		 * @return bool
		 */
		public function isUnique(array $columns)
		{
			$x = count($columns);
			if ($x) {
				foreach ($this->uc_constraints as $uc) {
					$uc_columns = $uc->getConstraintColumns();
					$y          = count($uc_columns);

					if ($x === $y AND !count(array_diff($uc_columns, $columns))) {
						return true;
					}
				}
			}

			return false;
		}
	}
