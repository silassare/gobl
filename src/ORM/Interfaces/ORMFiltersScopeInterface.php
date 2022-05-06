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

namespace Gobl\ORM\Interfaces;

use Gobl\DBAL\Operator;
use Gobl\DBAL\Table;

/**
 * Interface ORMFiltersScopeInterface.
 */
interface ORMFiltersScopeInterface
{
	/**
	 * Returns the target table.
	 *
	 * @return \Gobl\DBAL\Table
	 */
	public function getTable(): Table;

	/**
	 * Checks if a field/column is allowed in filters.
	 *
	 * @param string $field the field
	 *
	 * @return bool
	 */
	public function isFieldAllowed(string $field): bool;

	/**
	 * Checks if a filter is allowed.
	 *
	 * @param string   $field    the field
	 * @param Operator $operator the operator
	 * @param mixed    $value    the value of the filter
	 *
	 * @return bool
	 */
	public function isFilterAllowed(string $field, mixed $value, Operator $operator): bool;

	/**
	 * Returns the field/column fully qualified name to be used in a query.
	 *
	 * `users.user_id` is FQ Name of `user_id` from the table `users`
	 *
	 * @param string $field the field
	 *
	 * @return string
	 */
	public function getFieldFQName(string $field): string;
}
