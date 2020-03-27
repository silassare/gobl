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

	use Gobl\DBAL\Types\Exceptions\TypesException;
	use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
	use Gobl\DBAL\Types\Interfaces\TypeInterface;

	/**
	 * Class TypeBigint
	 *
	 * @package Gobl\DBAL\Types
	 */
	class TypeBigint extends TypeBase
	{
		private $unsigned = false;
		private $min      = null;
		private $max      = null;
		const BIGINT_REG          = '#[-+]?(?:[1-9][0-9]*|0)#';
		const BIGINT_UNSIGNED_REG = '#[+]?(?:[1-9][0-9]*|0)#';

		/**
		 * TypeBigint constructor.
		 *
		 * @param int|null $min      the minimum number
		 * @param int|null $max      the maximum number
		 * @param bool     $unsigned as unsigned number
		 *
		 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
		 */
		public function __construct($min = null, $max = null, $unsigned = false)
		{
			if ($unsigned) $this->unsigned();
			if (isset($min)) $this->min($min);
			if (isset($max)) $this->max($max);
		}

		/**
		 * Sets as unsigned.
		 *
		 * @return $this
		 */
		public function unsigned()
		{
			$this->unsigned = true;

			return $this;
		}

		/**
		 * Sets number max value.
		 *
		 * @param int $value the maximum
		 *
		 * @return $this
		 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
		 */
		public function max($value)
		{
			if (!is_numeric($value))
				throw new TypesException(sprintf('"%s" is not a valid number.', $value));

			if (!preg_match(self::BIGINT_REG, "$value"))
				throw new TypesException(sprintf('"%s" is not a valid bigint.', $value));

			if ($this->unsigned AND preg_match(self::BIGINT_UNSIGNED_REG, "$value"))
				throw new TypesException(sprintf('"%s" is not a valid unsigned bigint.', $value));

			if (isset($this->min) AND !self::isLt($this->min, $value))
				throw new TypesException(sprintf('min=%s and max=%s is not a valid condition.', $this->min, $value));

			$this->max = $value;

			return $this;
		}

		/**
		 * Sets number min value.
		 *
		 * @param int $value the minimum
		 *
		 * @return $this
		 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
		 */
		public function min($value)
		{
			if (!is_numeric($value))
				throw new TypesException(sprintf('"%s" is not a valid number.', $value));

			if (!preg_match(self::BIGINT_REG, "$value"))
				throw new TypesException(sprintf('"%s" is not a valid bigint.', $value));

			if ($this->unsigned AND preg_match(self::BIGINT_UNSIGNED_REG, "$value"))
				throw new TypesException(sprintf('"%s" is not a valid unsigned bigint.', $value));

			if (isset($this->max) AND !self::isLt($value, $this->max))
				throw new TypesException(sprintf('min=%s and max=%s is not a valid condition.', $value, $this->max));

			$this->min = $value;

			return $this;
		}

		/**
		 * Checks if the first argument is the smallest.
		 *
		 * @param mixed $a
		 * @param mixed $b
		 * @param bool  $or_equal
		 *
		 * @return bool
		 */
		private static function isLt($a, $b, $or_equal = true)
		{
			if (function_exists('bccomp')) {
				// make sure to have bcmath
				$a = sprintf('%F', $a);
				$b = sprintf('%F', $b);
				$c = bccomp($a, $b);

				return $or_equal ? $c <= 0 : $c < 0;
			}

			return $or_equal ? $a <= $b : $a < $b;
		}

		/**
		 * @inheritdoc
		 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
		 */
		public function validate($value, $column_name, $table_name)
		{
			$debug = [
				"value" => $value
			];

			if (is_null($value)) {
				if ($this->isAutoIncremented()) {
					return null;
				}
				if ($this->isNullAble()) {
					return $this->getDefault();
				}
			}

			if (!is_numeric($value))
				throw new TypesInvalidValueException('invalid_number_type', $debug);

			if (!preg_match(self::BIGINT_REG, "$value"))
				throw new TypesInvalidValueException('invalid_bigint_type', $debug);

			if ($this->unsigned AND !preg_match(self::BIGINT_UNSIGNED_REG, "$value"))
				throw new TypesInvalidValueException('invalid_unsigned_bigint_type', $debug);

			if (isset($this->min) AND !self::isLt($this->min, $value))
				throw new TypesInvalidValueException('number_value_lt_min', $debug);

			if (isset($this->max) AND !self::isLt($value, $this->max))
				throw new TypesInvalidValueException('number_value_gt_max', $debug);

			return $value;
		}

		/**
		 * @inheritdoc
		 */
		public static function getInstance(array $options)
		{
			$instance = new self(self::getOptionKey($options, 'min', null), self::getOptionKey($options, 'max', null), self::getOptionKey($options, 'unsigned', false));

			if (self::getOptionKey($options, 'null', false))
				$instance->nullAble();

			if (self::getOptionKey($options, 'auto_increment', false))
				$instance->autoIncrement();

			if (array_key_exists('default', $options))
				$instance->setDefault($options['default']);

			return $instance;
		}

		/**
		 * @inheritdoc
		 */
		public function getCleanOptions()
		{
			return [
				'type'           => 'bigint',
				'min'            => $this->min,
				'max'            => $this->max,
				'unsigned'       => $this->unsigned,
				'auto_increment' => $this->isAutoIncremented(),
				'null'           => $this->isNullAble(),
				'default'        => $this->getDefault()
			];
		}

		/**
		 * @inheritdoc
		 */
		final public function getTypeConstant()
		{
			return TypeInterface::TYPE_BIGINT;
		}
	}
