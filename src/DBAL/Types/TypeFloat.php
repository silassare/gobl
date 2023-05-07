<?php

/**
 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Gobl\DBAL\Types;

use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Types\Exceptions\TypesException;
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\Interfaces\BaseTypeInterface;
use Gobl\ORM\Utils\ORMTypeHint;

/**
 * Class TypeFloat.
 */
class TypeFloat extends Type implements BaseTypeInterface
{
	public const NAME = 'float';

	/**
	 * TypeFloat constructor.
	 *
	 * @param null|float  $min      the minimum float value
	 * @param null|float  $max      the maximum float value
	 * @param bool        $unsigned as unsigned float value
	 * @param null|string $message  the error message
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 */
	public function __construct(?float $min = null, ?float $max = null, bool $unsigned = false, ?string $message = null)
	{
		if ($unsigned) {
			$this->unsigned();
		}

		if (isset($min)) {
			$this->min($min);
		}

		if (isset($max)) {
			$this->max($max);
		}

		!empty($message) && $this->msg('invalid_float_type', $message);

		parent::__construct($this);
	}

	/**
	 * Sets as unsigned.
	 *
	 * @param bool        $unsigned
	 * @param null|string $message
	 *
	 * @return $this
	 */
	public function unsigned(bool $unsigned = true, ?string $message = null): self
	{
		!empty($message) && $this->msg('invalid_unsigned_float_type', $message);

		return $this->setOption('unsigned', $unsigned);
	}

	/**
	 * Sets min value.
	 *
	 * @param float       $min
	 * @param null|string $message
	 *
	 * @return $this
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 */
	public function min(float $min, ?string $message = null): self
	{
		if (0 > $min && $this->isUnsigned()) {
			throw new TypesException(\sprintf('"%s" is not a valid unsigned float.', $min));
		}
		$max = $this->getOption('max');

		if (null !== $max && $min > $max) {
			throw new TypesException(\sprintf('min=%s and max=%s is not a valid condition.', $min, $max));
		}

		!empty($message) && $this->msg('float_value_must_be_gt_or_equal_to_min', $message);

		return $this->setOption('min', $min);
	}

	/**
	 * Checks if this is unsigned.
	 *
	 * @return bool
	 */
	public function isUnsigned(): bool
	{
		return (bool) $this->getOption('unsigned', false);
	}

	/**
	 * Sets max value.
	 *
	 * @param float       $max
	 * @param null|string $message
	 *
	 * @return $this
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 */
	public function max(float $max, ?string $message = null): self
	{
		if (0.0 > $max && $this->isUnsigned()) {
			throw new TypesException(\sprintf('"%s" is not a valid unsigned float.', $max));
		}

		$min = $this->getOption('min');

		if (null !== $min && $max < $min) {
			throw new TypesException(\sprintf('min=%s and max=%s is not a valid condition.', $min, $max));
		}

		!empty($message) && $this->msg('float_value_must_be_lt_or_equal_to_max', $message);

		return $this->setOption('max', $max);
	}

	/**
	 * {@inheritDoc}
	 */
	public static function getInstance(array $options): self
	{
		return (new static())->configure($options);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 */
	public function configure(array $options): self
	{
		if (isset($options['min'])) {
			$this->min((float) $options['min']);
		}

		if (isset($options['max'])) {
			$this->max((float) $options['max']);
		}

		if (isset($options['unsigned'])) {
			$this->unsigned((bool) $options['unsigned']);
		}

		if (isset($options['mantissa'])) {
			$this->mantissa((int) $options['mantissa']);
		}

		return parent::configure($options);
	}

	/**
	 * Sets the number of digits following the floating point.
	 *
	 * @param int $mantissa the mantissa
	 *
	 * @return $this
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 */
	public function mantissa(int $mantissa): self
	{
		if (0 > $mantissa) {
			throw new TypesException(
				'The number of digits following the floating point should be a positive integer.'
			);
		}

		return $this->setOption('mantissa', $mantissa);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getEmptyValueOfType(): ?float
	{
		return $this->isNullable() ? null : 0.0;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return null|float
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
	 */
	public function validate(mixed $value): ?float
	{
		$debug = [
			'value' => $value,
		];

		if (null === $value) {
			$value = $this->getDefault();

			if (null === $value && $this->isNullable()) {
				return null;
			}
		}

		if (!\is_numeric($value)) {
			throw new TypesInvalidValueException($this->msg('invalid_float_type'), $debug);
		}

		// coerce the value to a number
		$value += 0;

		/** @psalm-suppress TypeDoesNotContainType */
		if (!\is_float($value) && !\is_int($value)) {
			throw new TypesInvalidValueException($this->msg('invalid_float_type'), $debug);
		}

		if (0 > $value && $this->isUnsigned()) {
			throw new TypesInvalidValueException($this->msg('invalid_unsigned_float_type'), $debug);
		}

		$min = $this->getOption('min');

		if (null !== $min && $value < $min) {
			throw new TypesInvalidValueException($this->msg('float_value_must_be_gt_or_equal_to_min'), $debug);
		}
		$max = $this->getOption('max');

		if (null !== $max && $value > $max) {
			throw new TypesInvalidValueException($this->msg('float_value_must_be_lt_or_equal_to_max'), $debug);
		}

		return (float) $value;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getWriteTypeHint(): array
	{
		return [ORMTypeHint::FLOAT, ORMTypeHint::INT];
	}

	/**
	 * {@inheritDoc}
	 */
	public function getName(): string
	{
		return self::NAME;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getReadTypeHint(): array
	{
		return [ORMTypeHint::FLOAT];
	}

	/**
	 * {@inheritDoc}
	 */
	public function dbToPhp(mixed $value, RDBMSInterface $rdbms): ?float
	{
		return null === $value ? null : (float) $value;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
	 */
	public function phpToDb(mixed $value, RDBMSInterface $rdbms): ?float
	{
		return $this->validate($value);
	}
}
