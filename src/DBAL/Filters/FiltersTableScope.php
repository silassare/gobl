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
use Gobl\DBAL\Queries\Interfaces\QBInterface;
use Gobl\DBAL\Queries\QBUtils;
use Gobl\DBAL\Table;

/**
 * Class FiltersTableScope.
 */
class FiltersTableScope implements FiltersScopeInterface
{
	/** @var string */
	protected string $table_alias;

	protected bool $allow_private_column_in_filters   = false;
	protected bool $allow_sensitive_column_in_filters = false;

	/**
	 * FiltersTableScope constructor.
	 *
	 * @param Table       $table
	 * @param null|string $table_alias
	 */
	public function __construct(protected Table $table, ?string $table_alias = null)
	{
		$this->table_alias = $table_alias ?? QBUtils::newAlias();
	}

	/**
	 * {@inheritDoc}
	 *
	 * Enforces the following access rules (throws `DBALRuntimeException` on violation):
	 * - **Private columns** are always rejected (unless `allowPrivateColumnInFilters(true)` is set).
	 * - **Sensitive columns** are always rejected (unless `allowSensitiveColumnInFilters(true)` is set).
	 * - All other non-private, non-sensitive public columns are allowed.
	 * - The column's own type may impose additional restrictions via `Type::assertFilterAllowed()`.
	 *
	 * @throws DBALRuntimeException
	 */
	public function assertFilterAllowed(Filter $filter, ?QBInterface $qb = null): void
	{
		// left operand should be a resolved column
		$col_name = $filter->getLeftOperand()->getResolvedColumnOrFail()->getColumnName();

		$column = $this->table->getColumnOrFail($col_name);

		if (!$this->allow_sensitive_column_in_filters && $column->isSensitive()) {
			throw new DBALRuntimeException('Field not allowed in filters of this scope.', [
				'field' => $filter->getLeftOperand(),
				'_why'  => 'Column is sensitive.',
			]);
		}
		if (!$this->allow_private_column_in_filters && $column->isPrivate()) {
			throw new DBALRuntimeException('Field not allowed in filters of this scope.', [
				'field' => $filter->getLeftOperand(),
				'_why'  => 'Column is private.',
			]);
		}

		$column->getType()->assertFilterAllowed($filter);
	}

	/**
	 * {@inheritDoc}
	 */
	public function tryResolveFieldNotation(FilterFieldNotation $fn, QBInterface $qb): void
	{
		$name = $fn->getColumnName() ?? $fn->getField();

		if ($col = $this->table->getColumn($name)) {
			$fn->markAsResolved($this->table_alias, $col->getFullName());
		}
	}

	/**
	 * {@inheritDoc}
	 *
	 * Returns `true` when `$scope` is an instance of this class or a subclass.
	 * This means a `FiltersTableScope` will accept filters from another `FiltersTableScope`
	 * (regardless of which table it references) but will reject any other scope type.
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

	/**
	 * Enable or disable filtering on sensitive columns.
	 *
	 * @param bool $allow
	 *
	 * @return $this
	 */
	public function allowSensitiveColumnInFilters(bool $allow = true): static
	{
		$this->allow_sensitive_column_in_filters = $allow;

		return $this;
	}
}
