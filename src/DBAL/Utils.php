<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the Gobl package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace Gobl\DBAL;

	/**
	 * Class Utils
	 *
	 * @package Gobl\DBAL
	 */
	class Utils
	{
		/**
		 * Converts string to CamelCase.
		 *
		 * example:
		 *    my_table_query_name    => MyTableQueryName
		 *    my_column_name        => MyColumnName
		 *
		 * @param string $str the table or column name
		 *
		 * @return string
		 */
		public static function toCamelCase($str)
		{
			return implode('', array_map('ucfirst', explode('_', $str)));
		}
	}