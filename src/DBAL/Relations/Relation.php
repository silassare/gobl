<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the Gobl package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace Gobl\DBAL\Relations;

	use Gobl\DBAL\Exceptions\DBALException;
	use Gobl\DBAL\Table;
	use Gobl\DBAL\Utils;

	abstract class Relation
	{
		const NAME_REG = '#^(?:[a-zA-Z][a-zA-Z0-9_]*[a-zA-Z0-9]|[a-zA-Z])$#';

		const ONE_TO_ONE   = 1;
		const ONE_TO_MANY  = 2;
		const MANY_TO_ONE  = 3;
		const MANY_TO_MANY = 4;

		/** @var int */
		protected $type;
		/** @var \Gobl\DBAL\Table */
		protected $host_table;
		/** @var \Gobl\DBAL\Table */
		protected $target_table;
		/** @var bool */
		protected $target_is_slave;
		/**@var array */
		protected $relation_columns;
		/** @var string */
		protected $name;

		/**
		 * Relation constructor.
		 *
		 * @param string           $name
		 * @param \Gobl\DBAL\Table $host_table
		 * @param \Gobl\DBAL\Table $target_table
		 * @param array|null       $columns
		 * @param int              $type
		 *
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function __construct($name, Table $host_table, Table $target_table, array $columns = null, $type)
		{
			if (!preg_match(Relation::NAME_REG, $name))
				throw new \InvalidArgumentException(sprintf('Invalid relation name "%s".', $name));

			// the relation is based on foreign keys
			if (is_null($columns)) {
				// the slave table contains foreign key from the master table
				if ($target_table->hasDefaultForeignKeyConstraint($host_table)) {
					$this->target_is_slave = true;
					$columns               = $target_table->getDefaultForeignKeyConstraintFrom($host_table)
														  ->getConstraintColumns();
				} elseif ($host_table->hasDefaultForeignKeyConstraint($target_table)) {
					$this->target_is_slave = false;
					$columns               = $host_table->getDefaultForeignKeyConstraintFrom($target_table)
														->getConstraintColumns();
				} else {
					throw new DBALException(sprintf('Error in relation "%s", there is no columns to link the table "%s" to the table "%s".', $name, $host_table->getName(), $target_table->getName()));
				}
			} else {
				$cols = [];
				foreach ($columns as $from_column => $target_column) {
					$host_table->assertHasColumn($from_column);
					$target_table->assertHasColumn($target_column);
					$from_column   = $host_table->getColumn($from_column)
												->getFullName();
					$target_column = $target_table->getColumn($target_column)
												  ->getFullName();

					$cols[$from_column] = $target_column;
				}

				$this->target_is_slave = true;
				$columns               = $cols;
			}

			$this->name             = $name;
			$this->type             = $type;
			$this->host_table       = $host_table;
			$this->target_table     = $target_table;
			$this->relation_columns = $columns;
		}

		/**
		 * Gets the relation host table.
		 *
		 * @return \Gobl\DBAL\Table
		 */
		public function getHostTable()
		{
			return $this->host_table;
		}

		/**
		 * Gets the relation target table.
		 *
		 * @return \Gobl\DBAL\Table
		 */
		public function getTargetTable()
		{
			return $this->target_table;
		}

		/**
		 * Gets the relation master table.
		 *
		 * @return \Gobl\DBAL\Table
		 */
		public function getMasterTable()
		{
			return $this->target_is_slave ? $this->host_table : $this->target_table;
		}

		/**
		 * Gets the relation slave table.
		 *
		 * @return \Gobl\DBAL\Table
		 */
		public function getSlaveTable()
		{
			return $this->target_is_slave ? $this->target_table : $this->host_table;
		}

		/**
		 * Gets relation columns.
		 *
		 * @return array
		 */
		public function getRelationColumns()
		{
			return $this->relation_columns;
		}

		/**
		 * Gets the relation type.
		 *
		 * @return int
		 */
		public function getType()
		{
			return $this->type;
		}

		/**
		 * Gets the relation name.
		 *
		 * @return string
		 */
		public function getName()
		{
			return $this->name;
		}

		/**
		 * Gets relation getter function name.
		 *
		 * @return string
		 */
		public function getGetterName()
		{
			return 'get' . Utils::toCamelCase($this->getName());
		}
	}
