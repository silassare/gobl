<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the Gobl package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace Gobl\DBAL\Types;

	/**
	 * Interface Type
	 *
	 * @package Gobl\DBAL\Types
	 */
	interface Type
	{
		const TYPE_INT    = 1;
		const TYPE_BIGINT = 2;
		const TYPE_FLOAT  = 3;
		const TYPE_BOOL   = 4;
		const TYPE_STRING = 5;

		/**
		 * Gets type constant Type::TYPE_*.
		 *
		 * @return int
		 */
		public function getTypeConstant();

		/**
		 * Explicitly set the default value.
		 *
		 * the default should comply with all rules or not ?
		 * the answer is up to you.
		 *
		 * @param mixed $value the value to use as default
		 *
		 * @return \Gobl\DBAL\Types\Type
		 */
		public function def($value);

		/**
		 * Enable null value.
		 *
		 * @return \Gobl\DBAL\Types\Type
		 */
		public function nullAble();

		/**
		 * Called to validate a form field value.
		 *
		 * @param string $value the value to validate
		 *
		 * @return mixed    the cleaned value to use.
		 *
		 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
		 */
		public function validate($value);

		/**
		 * Gets clean options from type instance.
		 *
		 * @return array
		 */
		public function getCleanOptions();

		/**
		 * Gets type instance based on given options.
		 *
		 * @param array $options the options
		 *
		 * @return \Gobl\DBAL\Types\Type
		 *
		 * @throws \Exception    when options is invalid
		 */
		public static function getInstance(array $options);
	}