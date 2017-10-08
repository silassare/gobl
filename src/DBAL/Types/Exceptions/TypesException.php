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
	 * Class TypesException
	 *
	 * @package Gobl\DBAL\Types\Exceptions
	 */
	class TypesException extends \Exception
	{
		/**
		 * Debug data.
		 *
		 * @var array
		 */
		protected $debug_data;

		/**
		 * TypesException constructor.
		 *
		 * @param string $message
		 * @param array  $data
		 */
		public function __construct($message, array $data = [])
		{
			parent::__construct($message);
			$this->debug_data = $data;
		}

		/**
		 * Gets debug data.
		 *
		 * @return array
		 */
		public function getDebugData()
		{
			return $this->debug_data;
		}
	}
