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
use Gobl\DBAL\Filters\FilterFieldNotation;
use Gobl\DBAL\Queries\Interfaces\QBInterface;

/**
 * Interface FiltersScopeInterface.
 */
interface FiltersScopeInterface
{
	/**
	 * Asserts if a filter is allowed.
	 *
	 * @param Filter           $filter the filter to check
	 * @param null|QBInterface $qb     optional query builder used for alias resolution
	 */
	public function assertFilterAllowed(Filter $filter, ?QBInterface $qb = null): void;

	/**
	 * Should try to resolve a provided unresolved field notation.
	 *
	 * @param FilterFieldNotation $fn the field notation to resolve
	 * @param QBInterface         $qb query builder context
	 */
	public function tryResolveFieldNotation(FilterFieldNotation $fn, QBInterface $qb): void;

	/**
	 * Checks if a given filters scope is allowed.
	 *
	 * This is helpful to safely merge filters from different sources (sub-queries etc.).
	 *
	 * @param FiltersScopeInterface $scope the scope to check
	 *
	 * @return bool
	 */
	public function shouldAllowFiltersScope(self $scope): bool;
}
