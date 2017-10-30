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
	 * Class TypeFloat
	 *
	 * @package Gobl\DBAL\Types
	 */
	class TypeFloat implements Type
	{
		private $null     = false;
		private $default  = null;
		private $unsigned = false;
		private $min;
		private $max;

		/**
		 * The number of digits following the decimal point.
		 *
		 * @var int
		 */
		private $mantissa = 53;

		/**
		 * TypeFloat constructor.
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

			if (!is_float($value) AND !is_int($value))
				throw new TypesException(sprintf('"%s" is not a valid float.', $value));

			if ($this->unsigned AND 0 > $value)
				throw new TypesException(sprintf('"%s" is not a valid unsigned float.', $value));

			if (isset($this->min) AND $value < $this->min)
				throw new TypesException(sprintf('min=%s and max=%s is not a valid condition.', $this->min, $value));

			$this->max = (float)$value;

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

			if (!is_float($value) AND !is_int($value))
				throw new TypesException(sprintf('"%s" is not a valid float.', $value));

			if ($this->unsigned AND 0 > $value)
				throw new TypesException(sprintf('"%s" is not a valid unsigned float.', $value));

			if (isset($this->max) AND $value > $this->max)
				throw new TypesException(sprintf('min=%s and max=%s is not a valid condition.', $value, $this->max));

			$this->min = (float)$value;

			return $this;
		}

		/**
		 * Sets the number of digits following the decimal point.
		 *
		 * @param int $value the mantissa
		 *
		 * @return $this
		 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
		 */
		public function mantissa($value)
		{
			if (!is_int($value) OR 0 > $value OR 53 < $value)
				throw new TypesException('The number of digits following the decimal point should be an integer between 0 and 53.');

			$this->mantissa = $value;

			return $this;
		}

		/**
		 * {@inheritdoc}
		 */
		public function nullAble()
		{
			$this->null = true;
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

			if (is_null($value) AND $this->null)
				return $this->default;

			if (!is_numeric($value))
				throw new TypesInvalidValueException('invalid_number_type', $debug);

			$value += 0;

			if (!is_float($value) AND !is_int($value))
				throw new TypesInvalidValueException('invalid_float_type', $debug);

			if ($this->unsigned AND 0 > $value)
				throw new TypesInvalidValueException('invalid_unsigned_float_type', $debug);

			if (isset($this->min) AND $value < $this->min)
				throw new TypesInvalidValueException('number_value_lt_min', $debug);

			if (isset($this->max) AND $value > $this->max)
				throw new TypesInvalidValueException('number_value_gt_max', $debug);

			return (float)$value;
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

			if (isset($options['mantissa']))
				$instance->mantissa($options['mantissa']);

			if (isset($options['null']) AND $options['null'])
				$instance->nullAble();

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
				'type'     => 'float',
				'min'      => $this->min,
				'max'      => $this->max,
				'unsigned' => $this->unsigned,
				'mantissa' => $this->mantissa,
				'null'     => $this->null,
				'default'  => $this->default
			];

			return $options;
		}

		/**
		 * {@inheritdoc}
		 */
		final public function getTypeConstant()
		{
			return Type::TYPE_FLOAT;
		}
	}
