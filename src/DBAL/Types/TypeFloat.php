<?php

/**
 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>.
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
 * Class TypeFloat
 */
class TypeFloat extends TypeBase
{
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
	 * @param null|int $min      the minimum number
	 * @param null|int $max      the maximum number
	 * @param bool     $unsigned as unsigned number
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 */
	public function __construct($min = null, $max = null, $unsigned = false)
	{
		$this->unsigned = (bool) $unsigned;

		if (isset($min)) {
			$this->min($min);
		}

		if (isset($max)) {
			$this->max($max);
		}
	}

	/**
	 * Sets number max value.
	 *
	 * @param int $value the maximum
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 *
	 * @return $this
	 */
	public function max($value)
	{
		if (!\is_numeric($value)) {
			throw new TypesException(\sprintf('"%s" is not a valid number.', $value));
		}

		$value += 0;

		if (!\is_float($value) && !\is_int($value)) {
			throw new TypesException(\sprintf('"%s" is not a valid float.', $value));
		}

		if ($this->unsigned && 0 > $value) {
			throw new TypesException(\sprintf('"%s" is not a valid unsigned float.', $value));
		}

		if (isset($this->min) && $value < $this->min) {
			throw new TypesException(\sprintf('min=%s and max=%s is not a valid condition.', $this->min, $value));
		}

		$this->max = (float) $value;

		return $this;
	}

	/**
	 * Sets number min value.
	 *
	 * @param int $value the minimum
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 *
	 * @return $this
	 */
	public function min($value)
	{
		if (!\is_numeric($value)) {
			throw new TypesException(\sprintf('"%s" is not a valid number.', $value));
		}

		$value += 0;

		if (!\is_float($value) && !\is_int($value)) {
			throw new TypesException(\sprintf('"%s" is not a valid float.', $value));
		}

		if ($this->unsigned && 0 > $value) {
			throw new TypesException(\sprintf('"%s" is not a valid unsigned float.', $value));
		}

		if (isset($this->max) && $value > $this->max) {
			throw new TypesException(\sprintf('min=%s and max=%s is not a valid condition.', $value, $this->max));
		}

		$this->min = (float) $value;

		return $this;
	}

	/**
	 * Sets the number of digits following the decimal point.
	 *
	 * @param int $value the mantissa
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 *
	 * @return $this
	 */
	public function mantissa($value)
	{
		if (!\is_int($value) || 0 > $value || 53 < $value) {
			throw new TypesException(
				'The number of digits following the decimal point should be an integer between 0 and 53.'
			);
		}

		$this->mantissa = $value;

		return $this;
	}

	/**
	 * @inheritdoc
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
	 */
	public function validate($value, $column_name, $table_name)
	{
		$debug = [
			'value' => $value,
		];

		if (null === $value && $this->isNullAble()) {
			return $this->getDefault();
		}

		if (!\is_numeric($value)) {
			throw new TypesInvalidValueException('invalid_number_type', $debug);
		}

		$value += 0;

		if (!\is_float($value) && !\is_int($value)) {
			throw new TypesInvalidValueException('invalid_float_type', $debug);
		}

		if ($this->unsigned && 0 > $value) {
			throw new TypesInvalidValueException('invalid_unsigned_float_type', $debug);
		}

		if (isset($this->min) && $value < $this->min) {
			throw new TypesInvalidValueException('number_value_lt_min', $debug);
		}

		if (isset($this->max) && $value > $this->max) {
			throw new TypesInvalidValueException('number_value_gt_max', $debug);
		}

		return (float) $value;
	}

	/**
	 * @inheritdoc
	 */
	public function getCleanOptions()
	{
		return [
			'type'     => 'float',
			'min'      => $this->min,
			'max'      => $this->max,
			'unsigned' => $this->unsigned,
			'mantissa' => $this->mantissa,
			'null'     => $this->isNullAble(),
			'default'  => $this->getDefault(),
		];
	}

	/**
	 * @inheritdoc
	 */
	final public function getTypeConstant()
	{
		return TypeInterface::TYPE_FLOAT;
	}

	/**
	 * @inheritdoc
	 */
	public static function getInstance(array $options)
	{
		$instance = new self(
			self::getOptionKey($options, 'min', null),
			self::getOptionKey($options, 'max', null),
			self::getOptionKey($options, 'unsigned', false)
		);

		if (isset($options['mantissa'])) {
			$instance->mantissa($options['mantissa']);
		}

		if (self::getOptionKey($options, 'null', false)) {
			$instance->nullAble();
		}

		if (\array_key_exists('default', $options)) {
			$instance->setDefault($options['default']);
		}

		return $instance;
	}
}
