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

namespace Gobl\DBAL\Filters;

use Gobl\DBAL\Queries\Interfaces\QBInterface;
use Gobl\DBAL\Table;
use InvalidArgumentException;
use LogicException;

/**
 * Represents a resolved column in a filter expression, including the resolved table name or alias, column name, and the original field notation.
 */
final class FilterResolvedColumn
{
	/**
	 * FilterResolvedColumn constructor.
	 *
	 * @param string              $table_or_alias The resolved table name or alias for the column
	 * @param string              $column_name    The resolved column name
	 * @param FilterFieldNotation $original_fn    The original field notation that was resolved to this column
	 * @param QBInterface         $qb             The query builder instance, used for resolving the table
	 */
	public function __construct(
		private string $table_or_alias,
		private string $column_name,
		private FilterFieldNotation $original_fn,
		private QBInterface $qb,
	) {
		if (empty($table_or_alias)) {
			throw new InvalidArgumentException('Table name or alias cannot be empty.');
		}

		if (empty($column_name)) {
			throw new InvalidArgumentException('Column name cannot be empty.');
		}

		if (!$original_fn->isResolved()) {
			throw new InvalidArgumentException('Original field notation must be resolved.');
		}
	}

	/**
	 * Get the resolved table name or alias.
	 *
	 * The value is determined by the original field notation's table or alias if available,
	 * otherwise it falls back to the provided table or alias in the constructor.
	 * This allows for proper disambiguation in cases we need original
	 * provided table or alias as seen in the field notation string.
	 */
	public function getTableOrAlias(): string
	{
		return $this->original_fn->getTableOrAlias() ?? $this->table_or_alias;
	}

	/**
	 * Get the resolved table instance.
	 */
	public function getTable(): Table
	{
		$table = $this->qb->resolveTable($this->getTableOrAlias());

		if (null === $table) {
			// this should not happen because the original field notation is resolved,
			// but we add this check just in case to prevent potential issues later on
			// and help type inference
			throw new LogicException(
				\sprintf(
					'Unexpected error resolving table name for "%s".',
					$this->getTableOrAlias()
				)
			);
		}

		return $table;
	}

	/**
	 * Get the resolved column name.
	 */
	public function getColumnName(): string
	{
		return $this->original_fn->getColumnName() ?? $this->column_name;
	}

	/**
	 * Get the original field notation.
	 */
	public function getFieldNotation(): FilterFieldNotation
	{
		return $this->original_fn;
	}
}
