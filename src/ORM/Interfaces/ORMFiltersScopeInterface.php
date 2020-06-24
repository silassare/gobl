<?php

/**
 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gobl\ORM\Interfaces;

interface ORMFiltersScopeInterface
{
	/**
	 * Returns the target table.
	 *
	 * @return \Gobl\DBAL\Table
	 */
	public function getTable();

	/**
	 * Checks if a field/column is allowed in filters.
	 *
	 * @param string $column
	 *
	 * @return bool
	 */
	public function isFieldAllowed($column);

	/**
	 * Checks if a filter is allowed.
	 *
	 * @param string $column
	 * @param int    $operator
	 * @param mixed  $value
	 *
	 * @return bool
	 */
	public function isFilterAllowed($column, $value, $operator);

	/**
	 * Returns the column fully qualified name.
	 *
	 * `users.user_id` is FQ Name of `user_id` from the table `users`
	 *
	 * @param string $column
	 *
	 * @return string
	 */
	public function getColumnFQName($column);
}
