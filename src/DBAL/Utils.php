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
		 * Converts string to ClassName.
		 *
		 * example:
		 *    a_class_name        => AClassName
		 *    my_class_name       => MYClassName
		 *    another_class_name  => AnotherClassName
		 *
		 * @param string $str the table or column name
		 *
		 * @return string
		 */
		public static function toClassName($str)
		{
			$result = implode('', array_map('ucfirst', explode('_', $str)));

			if (strlen($str) > 3 AND $str[2] === "_") {
				$result[1] = strtoupper($result[1]);
			}

			return $result;
		}
	}
