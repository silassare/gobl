<?php

/**
 * Copyright (c) Emile Silas Sare.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Gobl\DBAL\Types;

use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Operator;
use Gobl\DBAL\Types\Exceptions\TypesException;
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\Interfaces\ValidationSubjectInterface;
use Gobl\ORM\ORMTypeHint;
use Gobl\ORM\ORMUniversalType;
use Override;

/**
 * Class TypeDecimal.
 *
 * @extends BaseType<mixed, null|string>
 */
class TypeDecimal extends BaseType
{
	public const NAME = 'decimal';

	/**
	 * TypeDecimal constructor.
	 *
	 * @param null|string $min      the minimum decimal value
	 * @param null|string $max      the maximum decimal value
	 * @param bool        $unsigned as unsigned decimal value
	 * @param null|string $message  the error message
	 *
	 * @throws TypesException
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
	 * Sets min value.
	 *
	 * @param float|string $min
	 * @param null|string  $message
	 *
	 * @return static
	 *
	 * @throws TypesException
	 */
	public function min(float|string $min, ?string $message = null): static
	{
		if (0.0 > $min && $this->isUnsigned()) {
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
	 * Sets max value.
	 *
	 * @param float|string $max
	 * @param null|string  $message
	 *
	 * @return static
	 *
	 * @throws TypesException
	 */
	public function max(float|string $max, ?string $message = null): static
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
	 * Sets as unsigned.
	 *
	 * @param bool        $unsigned
	 * @param null|string $message
	 *
	 * @return static
	 */
	public function unsigned(bool $unsigned = true, ?string $message = null): static
	{
		!empty($message) && $this->msg('invalid_unsigned_decimal_type', $message);

		return $this->setOption('unsigned', $unsigned);
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

	#[Override]
	public static function getInstance(array $options): static
	{
		return (new static())->configure($options);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws TypesException
	 */
	#[Override]
	public function configure(array $options): static
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
	 * @return static
	 *
	 * @throws TypesException
	 */
	public function precision(int $precision, ?int $scale = null): static
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

	#[Override]
	public function getName(): string
	{
		return self::NAME;
	}

	#[Override]
	public function dbToPhp(mixed $value, RDBMSInterface $rdbms): ?string
	{
		return null === $value ? null : (string) $value;
	}

	#[Override]
	public function getEmptyValueOfType(): ?float
	{
		return $this->isNullable() ? null : 0.0;
	}

	#[Override]
	public function getReadTypeHint(): ORMTypeHint
	{
		return ORMTypeHint::decimal();
	}

	#[Override]
	public function getWriteTypeHint(): ORMTypeHint
	{
		return ORMTypeHint::decimal()
			->addUniversalTypes(ORMUniversalType::FLOAT, ORMUniversalType::INT);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws TypesInvalidValueException
	 */
	#[Override]
	public function phpToDb(mixed $value, RDBMSInterface $rdbms): ?string
	{
		return $this->validate($value)->getCleanValue();
	}

	#[Override]
	public function castValueForFilter(mixed $value, Operator $operator, RDBMSInterface $rdbms): float|int|string|null
	{
		return $this->phpToDb($value, $rdbms);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws TypesInvalidValueException
	 */
	#[Override]
	protected function runValidation(ValidationSubjectInterface $subject): void
	{
		$value = $subject->getUnsafeValue();
		$debug = [
			'value' => $value,
		];

		if (null === $value) {
			$value = $this->getDefault();

			if (null === $value && $this->isNullable()) {
				$subject->accept(null);

				return;
			}
			if (null === $value) {
				$subject->reject($this->msg('invalid_decimal_type'), $debug);

				return;
			}
		}

		if (!\is_numeric($value) && !\is_float($value) && !\is_int($value)) {
			$subject->reject($this->msg('invalid_decimal_type'), $debug);

			return;
		}

		if (0 > $value && $this->isUnsigned()) {
			$subject->reject($this->msg('invalid_unsigned_decimal_type'), $debug);

			return;
		}

		$min = $this->getOption('min');

		if (null !== $min && !self::isLt($min, $value, true)) {
			$subject->reject($this->msg('decimal_value_must_be_gt_or_equal_to_min'), $debug);

			return;
		}

		$max = $this->getOption('max');

		if (null !== $max && !self::isLt($value, $max, true)) {
			$subject->reject($this->msg('decimal_value_must_be_lt_or_equal_to_max'), $debug);

			return;
		}

		$subject->accept((string) $value);
	}
}
