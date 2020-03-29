<?php

/**
 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gobl\DBAL\Constraints;

use Gobl\DBAL\Table;

/**
 * Class Unique
 */
class Unique extends Constraint
{
	/**
	 * Unique constructor.
	 *
	 * @param string           $name  the constraint name
	 * @param \Gobl\DBAL\Table $table the table in which the constraint was defined
	 */
	public function __construct($name, Table $table)
	{
		parent::__construct($name, $table, Constraint::UNIQUE);
	}

	/**
	 * Adds column to the constraint
	 *
	 * @param string $name the column name
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 *
	 * @return $this
	 */
	public function addColumn($name)
	{
		$this->table->assertHasColumn($name);
		$this->columns[] = $this->table->getColumn($name)
									   ->getFullName();

		return $this;
	}
}
