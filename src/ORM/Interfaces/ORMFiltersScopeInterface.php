<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
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
		 * Checks if a field/column is allowed in filters.
		 *
		 * @param string $column
		 *
		 * @return boolean
		 */
		public function isFieldAllowed($column);

		/**
		 * Checks if a filter is allowed.
		 *
		 * @param string  $column
		 * @param mixed   $value
		 * @param integer $operator
		 *
		 * @return boolean
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
