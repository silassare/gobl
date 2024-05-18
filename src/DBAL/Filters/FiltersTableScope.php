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

use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Filters\Interfaces\FiltersScopeInterface;
use Gobl\DBAL\Queries\QBUtils;
use Gobl\DBAL\Table;

/**
 * Class FiltersTableScope.
 */
class FiltersTableScope implements FiltersScopeInterface
{
	/** @var string */
	protected string $table_alias;

	protected bool $allow_private_column_in_filters = false;

	/**
	 * FiltersTableScope constructor.
	 *
	 * @param Table       $table
	 * @param null|string $table_alias
	 */
	public function __construct(protected Table $table, string $table_alias = null)
	{
		$this->table_alias = $table_alias ?? QBUtils::newAlias();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws DBALRuntimeException
	 */
	public function assertFilterAllowed(Filter $filter): void
	{
		// left operand should be a column
		$column = $this->table->getColumnOrFail($filter->getLeftOperand());

		if (!$this->allow_private_column_in_filters && $column->isPrivate()) {
			throw new DBALRuntimeException('Private column not allowed in filters.');
		}

		$column->getType()
			->assertFilterAllowed($filter);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getColumnFQName($column_name): string
	{
		if ($col = $this->table->getColumn($column_name)) {
			return $this->table_alias . '.' . $col->getFullName();
		}

		return $column_name;
	}

	/**
	 * {@inheritDoc}
	 */
	public function shouldAllowFiltersScope(FiltersScopeInterface $scope): bool
	{
		return \is_a($scope, static::class);
	}

	/**
	 * Enable or disable filtering on private columns.
	 *
	 * @param bool $allow
	 *
	 * @return $this
	 */
	public function allowPrivateColumnInFilters(bool $allow = true): static
	{
		$this->allow_private_column_in_filters = $allow;

		return $this;
	}
}
