<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the Gobl package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace Gobl\ORM;

	abstract class ArrayCapable implements \JsonSerializable
	{
		/**
		 * Returns entity in array form.
		 *
		 * @param bool $hide_private_column
		 *
		 * @return array
		 */
		abstract function asArray($hide_private_column = true);

		/**
		 * Specify data which should be serialized to JSON.
		 *
		 * @return array
		 */
		public function jsonSerialize()
		{
			return $this->asArray();
		}
	}