<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the Gobl package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace Gobl\DBAL\Types\Exceptions;

	/**
	 * Class TypesInvalidValueException
	 *
	 * @package Gobl\DBAL\Types\Exceptions
	 */
	class TypesInvalidValueException extends TypesException {
		protected $debug_data = [];

		/**
		 * Gets debug data.
		 *
		 * @return array
		 */
		public function getDebugData()
		{
			return $this->debug_data;
		}

		/**
		 * Sets debug data.
		 *
		 * @param array $debug
		 */
		public function setDebugData(array $debug){
			$this->debug_data = $debug;
		}
	}
