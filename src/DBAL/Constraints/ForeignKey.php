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

use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Table;

/**
 * Class ForeignKey
 */
class ForeignKey extends Constraint
{
	const ACTION_NO_ACTION = 1;

	const ACTION_SET_NULL  = 2;

	const ACTION_CASCADE   = 3;

	const ACTION_RESTRICT  = 4;

	/** @var \Gobl\DBAL\Table */
	private $reference_table;

	/** @var int */
	private $update_action = self::ACTION_NO_ACTION;

	/** @var int */
	private $delete_action = self::ACTION_NO_ACTION;

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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 *
	 * @return $this
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

	/**
	 * Sets on update action.
	 *
	 * @param int $action one of ForeignKey::ACTION_* constants
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 *
	 * @return $this
	 */
	public function setUpdateAction($action)
	{
		if ($action < self::ACTION_NO_ACTION || $action > self::ACTION_RESTRICT) {
			throw new DBALException('Invalid update action for foreign key constraint.');
		}
		$this->update_action = $action;

		return $this;
	}

	/**
	 * Gets on update action.
	 *
	 * @return int
	 */
	public function getUpdateAction()
	{
		return $this->update_action;
	}

	/**
	 * Sets on delete action.
	 *
	 * @param int $action one of ForeignKey::ACTION_* constants
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 *
	 * @return $this
	 */
	public function setDeleteAction($action)
	{
		if ($action < self::ACTION_NO_ACTION || $action > self::ACTION_RESTRICT) {
			throw new DBALException('Invalid delete action for foreign key constraint.');
		}
		$this->delete_action = $action;

		return $this;
	}

	/**
	 * Gets on delete action.
	 *
	 * @return int
	 */
	public function getDeleteAction()
	{
		return $this->delete_action;
	}
}
