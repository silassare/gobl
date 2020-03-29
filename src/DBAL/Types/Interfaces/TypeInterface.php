<?php

/**
 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gobl\DBAL\Types\Interfaces;

/**
 * Interface Type
 */
interface TypeInterface
{
	const TYPE_INT    = 1;

	const TYPE_BIGINT = 2;

	const TYPE_FLOAT  = 3;

	const TYPE_BOOL   = 4;

	const TYPE_STRING = 5;

	/**
	 * Gets type instance based on given options.
	 *
	 * @param array $options the options
	 *
	 * @throws \Exception when options is invalid
	 *
	 * @return $this
	 */
	public static function getInstance(array $options);

	/**
	 * Gets type constant Type::TYPE_*.
	 *
	 * @return int
	 */
	public function getTypeConstant();

	/**
	 * Enable null value.
	 *
	 * @return $this
	 */
	public function nullAble();

	/**
	 * Checks if the type allow null value.
	 *
	 * @return bool
	 */
	public function isNullAble();

	/**
	 * Auto-increment allows a unique number to be generated,
	 * when a new record is inserted.
	 *
	 * @return $this
	 */
	public function autoIncrement();

	/**
	 * Checks if the type is auto-incremented.
	 *
	 * @return bool
	 */
	public function isAutoIncremented();

	/**
	 * Gets the default value.
	 */
	public function getDefault();

	/**
	 * Explicitly set the default value.
	 *
	 * the default should comply with all rules or not ?
	 * the answer is up to you.
	 *
	 * @param mixed $value the value to use as default
	 *
	 * @return $this
	 */
	public function setDefault($value);

	/**
	 * Called to validate a form field value.
	 *
	 * @param mixed  $value       the value to validate
	 * @param string $column_name the column name
	 * @param string $table_name  the table name
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
	 *
	 * @return mixed the cleaned value to use
	 */
	public function validate($value, $column_name, $table_name);

	/**
	 * Gets clean options from type instance.
	 *
	 * @return array
	 */
	public function getCleanOptions();
}
