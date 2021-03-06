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
use InvalidArgumentException;

/**
 * Class Constraint
 */
abstract class Constraint
{
	const NAME_REG    = '~^(?:[a-zA-Z][a-zA-Z0-9_]*[a-zA-Z0-9]|[a-zA-Z])$~';

	const PRIMARY_KEY = 1;

	const UNIQUE      = 2;

	const FOREIGN_KEY = 3;

	/** @var int */
	protected $type;

	/** @var string */
	protected $name;

	/** @var \Gobl\DBAL\Table */
	protected $table;

	/** @var string[] */
	protected $columns = [];

	/**
	 * Constraint constructor.
	 *
	 * @param string           $name  the constraint name
	 * @param \Gobl\DBAL\Table $table the table in which the constraint was defined
	 * @param int              $type  the constraint type
	 */
	public function __construct($name, Table $table, $type)
	{
		if (!\preg_match(self::NAME_REG, $name)) {
			throw new InvalidArgumentException(\sprintf(
				'Invalid constraint name "%s" in table "%s".',
				$name,
				$table->getName()
			));
		}

		$this->table = $table;
		$this->name  = $name;
		$this->type  = $type;
	}

	/**
	 * Gets constraint type
	 *
	 * @return int
	 */
	public function getType()
	{
		return $this->type;
	}

	/**
	 * Gets constraint columns
	 *
	 * @return string[]
	 */
	public function getConstraintColumns()
	{
		return $this->columns;
	}

	/**
	 * Gets constraint name
	 *
	 * @return string
	 */
	public function getName()
	{
		return $this->name;
	}
}
