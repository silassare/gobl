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
use Gobl\DBAL\Table;

/**
 * Class FiltersMultiScope.
 *
 * A filter scope that spans multiple registered scope, checked in declaration order.
 *
 * For example this is used in relation link filters (`Link::apply()`) where conditions may
 * legitimately reference columns from either the link's **host** or **target** table
 * (e.g. referencing a pivot column from a `pivot_to_target` sub-link).
 *
 * Scopes are checked in order:
 * - The **first** scope has the highest priority for both column resolution and FQN qualification.
 * - If the column is not found in the first scope the next scopes are tried in turn.
 * - Bare column names that are not found in any registered scope cause a `DBALRuntimeException`
 *   to be thrown (unknown column).
 */
class FiltersMultiScope implements FiltersScopeInterface
{
	/**
	 * @var array<int, array{table: ?Table, scope: FiltersScopeInterface}>
	 */
	private array $entries = [];

	/**
	 * @var array<string, array{table: ?Table, scope: FiltersScopeInterface}>
	 */
	private array $entries_table_map = [];

	/**
	 * FiltersMultiScope constructor.
	 */
	public function __construct() {}

	/**
	 * Add a scope to this multi-scope.
	 */
	public function push(FiltersScopeInterface $scope, ?Table $table = null): self
	{
		$this->entries[] = [
			'table' => $table,
			'scope' => $scope,
		];
		if (null !== $table) {
			$this->entries_table_map[$table->getFullName()] = [
				'table' => $table,
				'scope' => $scope,
			];
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
		$left_operand = $filter->getLeftOperand();
		$dtc          = $left_operand->getDetectedTableAndColumn();
		$left         = $left_operand->getDetectedColumnOrValueAsDefined();

		if ($dtc) {
			$entry = $this->entries_table_map[$dtc['table']] ?? null;

			if ($entry && $entry['table']->hasColumn($left)) {
				$entry['scope']->assertFilterAllowed($filter, $qb);

				return; // handled by the owning table's scope
			}
		}

		foreach ($this->entries as $entry) {
			$col_fqn = $entry['scope']->tryGetColumnFQName($left);

			if (null !== $col_fqn) {
				$entry['scope']->assertFilterAllowed($filter, $qb);

				return; // handled by this scope
			}
		}

		throw new DBALRuntimeException('Field not allowed in filters.', [
			'field' => $left,
			'_why'  => 'Field/Column is not found in any registered filter scope.',
		]);
	}

	/**
	 * {@inheritDoc}
	 */
	public function tryGetColumnFQName(string $column_name): ?string
	{
		foreach ($this->entries as $entry) {
			$fqn = $entry['scope']->tryGetColumnFQName($column_name);

			if (null !== $fqn) {
				return $fqn;
			}
		}

		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function shouldAllowFiltersScope(FiltersScopeInterface $scope): bool
	{
		return true;
	}
}
