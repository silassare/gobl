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

namespace Gobl\DBAL\Filters\Interfaces;

use Gobl\DBAL\Filters\Filter;

/**
 * Interface FiltersScopeInterface.
 */
interface FiltersScopeInterface
{
	/**
	 * Asserts if a filter is allowed.
	 *
	 * @param Filter $filter
	 */
	public function assertFilterAllowed(Filter $filter): void;

	/**
	 * Should return the given column fully qualified name or return as is.
	 *
	 * `users.user_id` is FQN of `user_id` from the table `users`
	 *
	 * @param string $column_name
	 *
	 * @return string
	 */
	public function getColumnFQName(string $column_name): string;

	/**
	 * Checks if a given filters scope is allowed.
	 *
	 * This is helpful to safely merge filters.
	 *
	 * @param FiltersScopeInterface $scope
	 *
	 * @return bool
	 */
	public function shouldAllowFiltersScope(self $scope): bool;
}
