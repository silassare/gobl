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

	use Gobl\DBAL\Table;

	/**
	 * Class ForeignKey
	 *
	 * @package Gobl\DBAL\Constraints
	 */
	class ForeignKey extends Constraint
	{
		/** @var \Gobl\DBAL\Table */
		private $reference_table;

		/**
		 * ForeignKey constructor.
		 *
		 * @param string           $name            the constraint name
		 * @param \Gobl\DBAL\Table $table           the table in which the constraint was defined
		 * @param \Gobl\DBAL\Table $reference_table the reference table
		 */
		public function __construct($name, Table $table, Table $reference_table)
		{
			parent::__construct($name, $table, Constraint::FOREIGN_KEY);

			$this->reference_table = $reference_table;
		}

		/**
		 * Adds column to the constraint
		 *
		 * @param string $name   the column name
		 * @param string $target the column name in the reference table
		 *
		 * @return $this
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function addColumn($name, $target)
		{
			$this->table->assertHasColumn($name);
			$this->reference_table->assertHasColumn($target);
			$name   = $this->table->getColumn($name)
								  ->getFullName();
			$target = $this->reference_table->getColumn($target)
											->getFullName();

			$this->columns[$name] = $target;

			return $this;
		}

		/**
		 * Gets the foreign keys reference table
		 *
		 * @return \Gobl\DBAL\Table
		 */
		public function getReferenceTable()
		{
			return $this->reference_table;
		}
	}
