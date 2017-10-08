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
		 * Constraints list map.
		 *
		 * @var array
		 */
		protected $constraints = [
			Constraint::PRIMARY_KEY => [],
			Constraint::UNIQUE      => [],
			Constraint::FOREIGN_KEY => []
		];

		/**
		 * Constraints counter, used to generate
		 * named constraints.
		 *
		 * @var int
		 */
		protected $constraints_counter = 1;

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
		 * @param string $name          the table name
		 * @param string $plural_name   the plural name
		 * @param string $singular_name the singular name
		 * @param string $namespace     the table namespace
		 * @param string $prefix        the table prefix
		 *
		 * @throws \Exception
		 */
		public function __construct($name, $plural_name, $singular_name, $namespace = '', $prefix = '')
		{
			if (!preg_match(Table::NAME_REG, $name))
				throw new \Exception(sprintf('Invalid table name "%s".', $name));

			if (!empty($prefix)) {
				if (!preg_match(Table::PREFIX_REG, $prefix))
					throw new \Exception(sprintf('Invalid table prefix name "%s".', $prefix));
			}

			if (empty($plural_name) OR empty($singular_name)) {
				throw new \Exception(sprintf('Plural and singular name for table "%s" should not be empty.', $name));
			}

			if ($plural_name === $singular_name) {
				throw new \Exception(sprintf('Plural and singular name for table "%s" should not be the same.', $name));
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
		 * @throws \Exception
		 */
		public function addColumn(Column $column)
		{
			$name      = $column->getName();
			$full_name = $column->getFullName();

			if ($this->hasColumn($name)) {
				throw new \Exception(sprintf('the column "%s" is already defined in table "%s".', $name, $this->name));
			}

			// prevent column full name conflict
			if ($this->hasColumn($full_name)) {
				$c = $this->col_full_name_map[$full_name];
				throw new \Exception(sprintf('the columns "%s" and "%s" has the same full name "%s" in table "%s".', $name, $c, $full_name, $this->getName()));
			}

			$this->columns[$name]                = $column;
			$this->col_full_name_map[$full_name] = $name;

			return $this;
		}

		/**
		 * Define a unique constraint on columns.
		 *
		 * @param array  $columns         the columns
		 * @param string $constraint_name the constraint name
		 *
		 * @return $this
		 * @throws \Exception
		 */
		public function addUniqueConstraint(array $columns, $constraint_name = '')
		{
			if (count($columns) < 1)
				throw new \Exception('columns should not be empty.');

			if (empty($constraint_name))
				$constraint_name = sprintf('uc_%s_%d', $this->name, $this->constraints_counter++);

			$unique_keys = &$this->constraints[Constraint::UNIQUE];

			foreach ($columns as $column_name) {
				if (!$this->hasColumn($column_name))
					throw new \Exception(sprintf('the column "%s" is not defined in table "%s"', $column_name, $this->name));

				$unique_keys[$constraint_name] = $this->getColumn($column_name)
													  ->getFullName();
			}

			return $this;
		}

		/**
		 * Define a primary key constraint on columns.
		 *
		 * @param array  $columns         the columns
		 * @param string $constraint_name the constraint name
		 *
		 * @return $this
		 * @throws \Exception
		 */
		public function addPrimaryKeyConstraint(array $columns, $constraint_name = null)
		{
			if (count($columns) < 1)
				throw new \Exception('columns should not be empty.');

			$primary_keys = &$this->constraints[Constraint::PRIMARY_KEY];

			if (count($primary_keys)) {
				$keys = array_keys($primary_keys);
				$name = $keys[0];
				if (!empty($constraint_name) AND $name != $constraint_name) {
					throw new \Exception(sprintf('they should be only one primary key in table "%s".', $this->name));
				} else {
					$constraint_name = $name;
				}
			}

			if (empty($constraint_name))
				$constraint_name = sprintf('pk_%s_%d', $this->name, $this->constraints_counter++);

			foreach ($columns as $column_name) {
				if (!$this->hasColumn($column_name))
					throw new \Exception(sprintf('the column "%s" is not defined in table "%s"', $column_name, $this->name));

				$cols_options = $this->getColumn($column_name)
									 ->getOptions();

				if ($cols_options['null'] === true)
					throw new \Exception(sprintf('all parts of a PRIMARY KEY must be NOT NULL; if you need NULL in a key, use UNIQUE instead; check column "%s" in table "%s".', $column_name, $this->name));

				$primary_keys[$constraint_name][] = $this->getColumn($column_name)
														 ->getFullName();
			}

			return $this;
		}

		/**
		 * Define a foreign key constraint on columns.
		 *
		 * @param \Gobl\DBAL\Table $reference_table the reference table
		 * @param array                   $columns         the columns
		 * @param string                  $constraint_name the constraint name
		 *
		 * @return $this
		 * @throws \Exception
		 */
		public function addForeignKeyConstraint(Table $reference_table, array $columns, $constraint_name = null)
		{
			if (count($columns) < 1)
				throw new \Exception('columns should not be empty.');

			if (empty($constraint_name))
				$constraint_name = sprintf('fk_%s_%d', $this->name, $this->constraints_counter++);

			$foreign_keys = &$this->constraints[Constraint::FOREIGN_KEY];

			$foreign_keys[$constraint_name]['reference'] = $reference_table;

			foreach ($columns as $column_name => $reference) {
				if (!$this->hasColumn($column_name))
					throw new \Exception(sprintf('the column "%s" is not defined in table "%s"', $column_name, $this->name));

				if (!$reference_table->hasColumn($reference))
					throw new \Exception(sprintf('the column "%s" is not defined in table "%s"', $column_name, $reference_table->getName()));

				$column_name                                         = $this->getColumn($column_name)
																			->getFullName();
				$reference                                           = $reference_table->getColumn($reference)
																					   ->getFullName();
				$foreign_keys[$constraint_name]['map'][$column_name] = $reference;
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
			if (isset($this->columns[$name]) OR isset($this->col_full_name_map[$name])) {
				return true;
			}

			return false;
		}

		/**
		 * Asserts if a given column name is defined.
		 *
		 * @param string $name the column name or full name
		 *
		 * @throws \Exception
		 */
		public function assertHasColumn($name)
		{
			if (!$this->hasColumn($name)) {
				throw new \Exception(sprintf('The column "%s" does not exists in the table "%s".', $name, $this->getFullName()));
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
		 * Gets constraints.
		 *
		 * @return array
		 */
		public function getConstraints()
		{
			return $this->constraints;
		}

		/**
		 * Gets unique constraints.
		 *
		 * @return array
		 */
		public function getUniqueConstraints()
		{
			return $this->constraints[Constraint::UNIQUE];
		}

		/**
		 * Gets primary key constraints.
		 *
		 * @return array
		 */
		public function getPrimaryKeyConstraints()
		{
			return $this->constraints[Constraint::PRIMARY_KEY];
		}

		/**
		 * Gets foreign key constraints.
		 *
		 * @return array
		 */
		public function getForeignKeyConstraints()
		{
			return $this->constraints[Constraint::FOREIGN_KEY];
		}
	}
