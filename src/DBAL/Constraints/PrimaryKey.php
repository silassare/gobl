<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the Gobl package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace Gobl\DBAL\Constraints;

	use Gobl\DBAL\Exceptions\DBALException;
	use Gobl\DBAL\Table;

	/**
	 * Class PrimaryKey
	 *
	 * @package Gobl\DBAL\Constraints
	 */
	class PrimaryKey extends Constraint
	{
		/**
		 * PrimaryKey constructor.
		 *
		 * @param string           $name  the constraint name
		 * @param \Gobl\DBAL\Table $table the table in which the constraint was defined
		 */
		public function __construct($name, Table $table)
		{
			parent::__construct($name, $table, Constraint::PRIMARY_KEY);
		}

		/**
		 * Adds column to the constraint
		 *
		 * @param string $name the column name
		 *
		 * @return $this
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function addColumn($name)
		{
			$this->table->assertHasColumn($name);
			$column = $this->table->getColumn($name);

			if ($column->getTypeObject()->isNullAble()) {
				throw new DBALException(sprintf('All parts of a PRIMARY KEY must be NOT NULL; if you need NULL in a key, use UNIQUE instead; check column "%s" in table "%s".', $name, $this->table->getName()));
			}

			$this->columns[] = $column->getFullName();

			return $this;
		}
	}
