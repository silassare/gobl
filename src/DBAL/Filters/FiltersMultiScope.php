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

namespace Gobl\DBAL\Filters;

use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Filters\Interfaces\FiltersScopeInterface;
use Gobl\DBAL\Queries\Interfaces\QBInterface;
use Gobl\DBAL\Table;

/**
 * Class FiltersMultiScope.
 *
 * A filter scope that spans multiple registered scope, checked in declaration order.
 *
 * For example this is used in relation link filters (`Link::apply()`) where conditions may
 * legitimately reference columns from either the link's **host** or **target** table
 * (e.g. referencing a pivot column from a `pivot_to_target` sub-link).
 */
class FiltersMultiScope implements FiltersScopeInterface
{
	/**
	 * @var array<string, array{table: ?Table, scope: FiltersScopeInterface}>
	 */
	private array $entries = [];

	/**
	 * FiltersMultiScope constructor.
	 */
	public function __construct() {}

	/**
	 * Add a scope to this multi-scope.
	 *
	 * Scopes are checked in declaration order, so the most important scope should be pushed first.
	 *
	 * @param FiltersScopeInterface $scope The scope for that table
	 * @param Table                 $table The table
	 * @param null|string           $alias An optional alias used as alternative key to reference the scope (e.g. a table alias used in the query builder)
	 */
	public function push(FiltersScopeInterface $scope, Table $table, ?string $alias = null): self
	{
		$entry = [
			'table' => $table,
			'scope' => $scope,
		];

		$this->entries[$table->getFullName()] = $entry;

		if ($alias) {
			$this->entries[$alias] = $entry;
		}

		return $this;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws DBALRuntimeException when the column is found but blocked by private/sensitive
	 *                              rules, or when a bare column name is not found in any registered table
	 */
	public function assertFilterAllowed(Filter $filter, ?QBInterface $qb = null): void
	{
		// left operand should be a resolved column
		$resolved = $filter->getLeftOperand()->getResolvedColumnOrFail();

		$table_key = $resolved->getTableOrAlias();

		if ($table_key) {
			$entry = $this->entries[$table_key] ?? null;
			// left operand should be a column
			$col_name = $resolved->getColumnName();

			if ($entry) {
				$entry['scope']->assertFilterAllowed($filter, $qb);

				return; // handled by the owning table's scope
			}
		}

		$dfn = $resolved->getFieldNotation();

		foreach ($this->entries as $entry) {
			$entry['scope']->tryResolveFieldNotation($dfn, $qb);

			if ($dfn->isResolved()) {
				$entry['scope']->assertFilterAllowed($filter, $qb);

				return; // handled by this scope
			}
		}

		throw new DBALRuntimeException('Field not allowed in filters of this scope.', [
			'field' => $resolved->getColumnName(),
			'_why'  => 'Field/Column is not found in any registered filter scope.',
		]);
	}

	public function tryResolveFieldNotation(FilterFieldNotation $fn, QBInterface $qb): void
	{
		foreach ($this->entries as $entry) {
			$entry['scope']->tryResolveFieldNotation($fn, $qb);

			if ($fn->isResolved()) {
				return;
			}
		}
	}

	/**
	 * {@inheritDoc}
	 *
	 * A scope is allowed if at least one registered scope allows it.
	 */
	public function shouldAllowFiltersScope(FiltersScopeInterface $scope): bool
	{
		foreach ($this->entries as $entry) {
			if ($entry['scope']->shouldAllowFiltersScope($scope)) {
				return true;
			}
		}

		return false;
	}
}
