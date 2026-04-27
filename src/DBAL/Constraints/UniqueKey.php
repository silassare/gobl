<?php

/**
 * Copyright (c) Emile Silas Sare.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Gobl\DBAL\Constraints;

use Gobl\DBAL\Table;
use Override;
use PHPUtils\Exceptions\RuntimeException;

/**
 * Class UniqueKey.
 */
final class UniqueKey extends Constraint
{
	/**
	 * The columns that make up the unique key.
	 *
	 * @var list<string>
	 */
	private array $columns = [];

	/**
	 * UniqueKey constructor.
	 *
	 * @param string $name       the constraint name
	 * @param Table  $host_table the table in which the constraint was defined
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
	 * @throws RuntimeException when the constraint is locked
	 */
	public function addColumn(string $name): static
	{
		$this->assertNotLocked();

		$this->columns[] = $this->host_table->getColumnOrFail($name)
			->getFullName();

		return $this;
	}

	/**
	 * Gets unique constraints columns.
	 *
	 * @return list<string>
	 */
	public function getColumns(): array
	{
		return $this->columns;
	}

	#[Override]
	public function assertIsValid(): void {}

	#[Override]
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
