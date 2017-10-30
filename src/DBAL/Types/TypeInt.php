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

	/**
	 * Class TypeInt
	 *
	 * @package Gobl\DBAL\Types
	 */
	class TypeInt implements Type
	{
		private $null           = false;
		private $default        = null;
		private $unsigned       = false;
		private $auto_increment = false;
		private $min;
		private $max;
		const INT_SIGNED_MIN   = -2147483648;
		const INT_SIGNED_MAX   = 2147483647;
		const INT_UNSIGNED_MIN = 0;
		const INT_UNSIGNED_MAX = 4294967295;

		/**
		 * TypeInt constructor.
		 *
		 * @param int|null $min      the minimum number
		 * @param int|null $max      the maximum number
		 * @param bool     $unsigned as unsigned number
		 */
		public function __construct($min = null, $max = null, $unsigned = false)
		{
			$this->unsigned = (bool)$unsigned;

			if (isset($min)) $this->min($min);
			if (isset($max)) $this->max($max);
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

			$value += 0;

			if (!is_int($value))
				throw new TypesException(sprintf('"%s" is not a valid int.', $value));

			if ($this->unsigned AND TypeInt::INT_UNSIGNED_MIN > $value)
				throw new TypesException(sprintf('"%s" is not a valid unsigned int.', $value));

			if ($this->unsigned AND $value > TypeInt::INT_UNSIGNED_MAX)
				throw new TypesException('You should use "unsigned bigint" instead of "unsigned int".');

			if (!$this->unsigned AND $value > TypeInt::INT_SIGNED_MAX)
				throw new TypesException('You should use "signed bigint" instead of "signed int".');

			if (isset($this->min) AND $value < $this->min)
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

			$value += 0;

			if (!is_int($value))
				throw new TypesException(sprintf('"%s" is not a valid int.', $value));

			if ($this->unsigned AND TypeInt::INT_UNSIGNED_MIN > $value)
				throw new TypesException(sprintf('"%s" is not a valid unsigned int.', $value));

			if (!$this->unsigned AND $value < TypeInt::INT_SIGNED_MIN)
				throw new TypesException('You should use "signed bigint" instead of "signed int".');

			if (isset($this->max) AND $value > $this->max)
				throw new TypesException(sprintf('min=%s and max=%s is not a valid condition.', $value, $this->max));

			$this->min = $value;

			return $this;
		}

		/**
		 * Auto-increment allows a unique number to be generated,
		 * when a new record is inserted.
		 *
		 * @return $this
		 */
		public function autoIncrement()
		{
			$this->auto_increment = true;

			return $this;
		}

		/**
		 * {@inheritdoc}
		 */
		public function nullAble()
		{
			$this->null = true;

			return $this;
		}

		/**
		 * {@inheritdoc}
		 */
		public function def($value)
		{
			$this->default = $value;

			return $this;
		}

		/**
		 * {@inheritdoc}
		 */
		public function validate($value)
		{
			$debug = ['value' => $value, 'min' => $this->min, 'max' => $this->max, 'default' => $this->default];

			if (is_null($value)) {
				if ($this->auto_increment) {
					return null;
				}
				if ($this->null) {
					return $this->default;
				}
			}

			if (!is_numeric($value))
				throw new TypesInvalidValueException('invalid_number_type', $debug);

			$value += 0;

			if (!is_int($value))
				throw new TypesInvalidValueException('invalid_integer_type', $debug);

			if ($this->unsigned AND 0 > $value)
				throw new TypesInvalidValueException('invalid_unsigned_integer_type', $debug);

			if (isset($this->min) AND $value < $this->min)
				throw new TypesInvalidValueException('number_value_lt_min', $debug);

			if (isset($this->max) AND $value > $this->max)
				throw new TypesInvalidValueException('number_value_gt_max', $debug);

			return $value;
		}

		/**
		 * {@inheritdoc}
		 */
		public static function getInstance(array $options)
		{
			$options = array_merge([
				'min'      => null,
				'max'      => null,
				'unsigned' => false
			], $options);

			$instance = new self($options['min'], $options['max'], $options['unsigned']);

			if (isset($options['null']) AND $options['null'])
				$instance->nullAble();

			if (isset($options['auto_increment']) AND $options['auto_increment'])
				$instance->autoIncrement();

			if (array_key_exists('default', $options))
				$instance->def($options['default']);

			return $instance;
		}

		/**
		 * {@inheritdoc}
		 */
		public function getCleanOptions()
		{
			$options = [
				'type'           => 'int',
				'min'            => $this->min,
				'max'            => $this->max,
				'unsigned'       => $this->unsigned,
				'auto_increment' => $this->auto_increment,
				'null'           => $this->null,
				'default'        => $this->default
			];

			return $options;
		}

		/**
		 * {@inheritdoc}
		 */
		final public function getTypeConstant()
		{
			return Type::TYPE_INT;
		}
	}
