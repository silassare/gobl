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
use Gobl\ORM\ORMTypeHint;
use Gobl\ORM\ORMUniversalType;

/**
 * Class TypeDecimal.
 */
class TypeDecimal extends Type implements BaseTypeInterface
{
	public const NAME = 'decimal';

	/**
	 * TypeFloat constructor.
	 *
	 * @param null|string $min      the minimum decimal value
	 * @param null|string $max      the maximum decimal value
	 * @param bool        $unsigned as unsigned decimal value
	 * @param null|string $message  the error message
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 */
	public function __construct(
		?string $min = null,
		?string $max = null,
		bool $unsigned = false,
		?string $message = null
	) {
		if ($unsigned) {
			$this->unsigned();
		}

		if (isset($min)) {
			$this->min($min);
		}

		if (isset($max)) {
			$this->max($max);
		}

		!empty($message) && $this->msg('invalid_decimal_type', $message);

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
		!empty($message) && $this->msg('invalid_unsigned_decimal_type', $message);

		return $this->setOption('unsigned', $unsigned);
	}

	/**
	 * Sets min value.
	 *
	 * @param string      $min
	 * @param null|string $message
	 *
	 * @return $this
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 */
	public function min(string $min, ?string $message = null): self
	{
		if (0 > $min && $this->isUnsigned()) {
			throw new TypesException(\sprintf('"%s" is not a valid unsigned decimal.', $min));
		}
		$max = $this->getOption('max');

		if (null !== $max && $min > $max) {
			throw new TypesException(\sprintf('min=%s and max=%s is not a valid condition.', $min, $max));
		}

		!empty($message) && $this->msg('decimal_value_must_be_gt_or_equal_to_min', $message);

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
	 * @param string      $max
	 * @param null|string $message
	 *
	 * @return $this
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 */
	public function max(string $max, ?string $message = null): self
	{
		if (0.0 > $max && $this->isUnsigned()) {
			throw new TypesException(\sprintf('"%s" is not a valid unsigned decimal.', $max));
		}

		$min = $this->getOption('min');

		if (null !== $min && $max < $min) {
			throw new TypesException(\sprintf('min=%s and max=%s is not a valid condition.', $min, $max));
		}

		!empty($message) && $this->msg('decimal_value_must_be_lt_or_equal_to_max', $message);

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
			$this->min((string) $options['min']);
		}

		if (isset($options['max'])) {
			$this->max((string) $options['max']);
		}

		if (isset($options['unsigned'])) {
			$this->unsigned((bool) $options['unsigned']);
		}

		if (isset($options['precision'])) {
			if (isset($options['scale'])) {
				$this->precision((int) $options['precision'], (int) $options['scale']);
			} else {
				$this->precision($options['precision']);
			}
		}

		return parent::configure($options);
	}

	/**
	 * Sets the total number of digits and the scale.
	 *
	 * @param int      $precision the number of digits before the fixed point
	 * @param null|int $scale     the number of digits following the fixed point
	 *
	 * @return $this
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 */
	public function precision(int $precision, ?int $scale = null): self
	{
		if (1 > $precision) {
			throw new TypesException(
				'The total number of digits should be an integer greater than 1.'
			);
		}

		if (null !== $scale) {
			if (0 > $scale || $precision < $scale) {
				throw new TypesException(
					\sprintf(
						'The number of digits following the fixed point should be an integer between %s and %s.',
						0,
						$precision
					)
				);
			}

			$this->setOption('scale', $scale);
		}

		return $this->setOption('precision', $precision);
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
	 * @return null|string
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
	 */
	public function validate(mixed $value): ?string
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

		if (!\is_numeric($value) && !\is_float($value) && !\is_int($value)) {
			throw new TypesInvalidValueException($this->msg('invalid_decimal_type'), $debug);
		}

		if (0 > $value && $this->isUnsigned()) {
			throw new TypesInvalidValueException($this->msg('invalid_unsigned_decimal_type'), $debug);
		}

		$min = $this->getOption('min');

		if (null !== $min && !self::isLt($min, $value, true)) {
			throw new TypesInvalidValueException($this->msg('decimal_value_must_be_gt_or_equal_to_min'), $debug);
		}
		$max = $this->getOption('max');

		if (null !== $max && !self::isLt($value, $max, true)) {
			throw new TypesInvalidValueException($this->msg('decimal_value_must_be_lt_or_equal_to_max'), $debug);
		}

		return (string) $value;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getWriteTypeHint(): ORMTypeHint
	{
		return ORMTypeHint::decimal()
			->addUniversalTypes(ORMUniversalType::FLOAT, ORMUniversalType::INT);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getReadTypeHint(): ORMTypeHint
	{
		return ORMTypeHint::decimal();
	}

	/**
	 * {@inheritDoc}
	 */
	public function dbToPhp(mixed $value, RDBMSInterface $rdbms): ?string
	{
		return null === $value ? null : (string) $value;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
	 */
	public function phpToDb(mixed $value, RDBMSInterface $rdbms): ?string
	{
		return $this->validate($value);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getName(): string
	{
		return self::NAME;
	}
}
