<?php

/**
 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Gobl\DBAL\Constraints;

use Gobl\DBAL\Table;

/**
 * Class UniqueKey.
 */
class UniqueKey extends Constraint
{
	private array $columns = [];

	/**
	 * UniqueKey constructor.
	 *
	 * @param string           $name       the constraint name
	 * @param \Gobl\DBAL\Table $host_table the table in which the constraint was defined
	 */
	public function __construct(string $name, Table $host_table)
	{
		parent::__construct($name, $host_table, Constraint::UNIQUE_KEY);
	}

	/**
	 * Adds column to the constraint.
	 *
	 * @param string $name the column name
	 *
	 * @return $this
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function addColumn(string $name): self
	{
		$this->assertNotLocked();

		$this->columns[] = $this->host_table->getColumnOrFail($name)
			->getFullName();

		return $this;
	}

	/**
	 * Gets unique constraints columns.
	 *
	 * @return string[]
	 */
	public function getColumns(): array
	{
		return $this->columns;
	}

	/**
	 * {@inheritDoc}
	 */
	public function assertIsValid(): void
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		$columns = [];

		foreach ($this->columns as $full_name) {
			$columns[] = $this->host_table->getColumnOrFail($full_name)
				->getName();
		}

		return [
			'type'    => 'unique_key',
			'columns' => $columns,
		];
	}
}
