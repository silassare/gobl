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
use Override;

/**
 * Class TypeInt.
 *
 * @extends BaseType<mixed, null|int>
 */
class TypeInt extends BaseType
{
	public const INT_SIGNED_MAX   = 2147483647;
	public const INT_SIGNED_MIN   = -2147483648;
	public const INT_UNSIGNED_MAX = 4294967295;
	public const INT_UNSIGNED_MIN = 0;
	public const NAME             = 'int';

	/**
	 * TypeInt constructor.
	 *
	 * @param null|int    $min      the minimum int value
	 * @param null|int    $max      the maximum int value
	 * @param bool        $unsigned as unsigned int value
	 * @param null|string $message  the error message
	 *
	 * @throws TypesException
	 */
	public function __construct(?int $min = null, ?int $max = null, bool $unsigned = false, ?string $message = null)
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

		!empty($message) && $this->msg('invalid_int_type', $message);

		parent::__construct($this);
	}

	/**
	 * Sets min value.
	 *
	 * @param int         $min
	 * @param null|string $message
	 *
	 * @return static
	 *
	 * @throws TypesException
	 */
	public function min(int $min, ?string $message = null): static
	{
		self::assertValidInt($min, $this->isUnsigned());

		$max = $this->getOption('max');

		if (null !== $max && $min > $max) {
			throw new TypesException(\sprintf('min=%s and max=%s is not a valid condition.', $min, $max));
		}

		!empty($message) && $this->msg('int_value_must_be_gt_or_equal_to_min', $message);

		return $this->setOption('min', $min);
	}

	/**
	 * Sets max value.
	 *
	 * @param int         $max
	 * @param null|string $message
	 *
	 * @return static
	 *
	 * @throws TypesException
	 */
	public function max(int $max, ?string $message = null): static
	{
		self::assertValidInt($max, $this->isUnsigned());

		$min = $this->getOption('min');

		if (null !== $min && $max < $min) {
			throw new TypesException(\sprintf('min=%s and max=%s is not a valid condition.', $min, $max));
		}

		!empty($message) && $this->msg('int_value_must_be_lt_or_equal_to_max', $message);

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
		!empty($message) && $this->msg('invalid_unsigned_int_type', $message);

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
		return (new self())->configure($options);
	}

	#[Override]
	public function getName(): string
	{
		return self::NAME;
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
			$this->min((int) $options['min']);
		}

		if (isset($options['max'])) {
			$this->max((int) $options['max']);
		}

		if (isset($options['unsigned'])) {
			$this->unsigned((bool) $options['unsigned']);
		}

		return parent::configure($options);
	}

	#[Override]
	public function dbToPhp(mixed $value, RDBMSInterface $rdbms): ?int
	{
		return null === $value ? null : (int) $value;
	}

	#[Override]
	public function getEmptyValueOfType(): ?int
	{
		return $this->isNullable() ? null : 0;
	}

	#[Override]
	public function getReadTypeHint(): ORMTypeHint
	{
		return ORMTypeHint::int();
	}

	#[Override]
	public function getWriteTypeHint(): ORMTypeHint
	{
		return ORMTypeHint::int();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws TypesInvalidValueException
	 */
	#[Override]
	public function phpToDb(mixed $value, RDBMSInterface $rdbms): ?int
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
			if ($this->isAutoIncremented()) {
				$subject->accept(null);

				return;
			}

			$value = $this->getDefault();

			if (null === $value && $this->isNullable()) {
				$subject->accept(null);

				return;
			}
			if (null === $value) {
				$subject->reject($this->msg('invalid_int_type'), $debug);

				return;
			}
		}

		if (!\is_numeric($value)) {
			$subject->reject($this->msg('invalid_int_type'), $debug);

			return;
		}

		// coerce the value to a integer
		$value = (int) $value;

		if (0 > $value && $this->isUnsigned()) {
			$subject->reject($this->msg('invalid_unsigned_int_type'), $debug);

			return;
		}

		$min = $this->getOption('min');

		if (null !== $min && $value < $min) {
			$subject->reject($this->msg('int_value_must_be_gt_or_equal_to_min'), $debug);

			return;
		}

		$max = $this->getOption('max');

		if (null !== $max && ($value > $max)) {
			$subject->reject($this->msg('int_value_must_be_lt_or_equal_to_max'), $debug);

			return;
		}

		if ($value < ($this->isUnsigned() ? self::INT_UNSIGNED_MIN : self::INT_SIGNED_MIN)) {
			$subject->reject($this->msg('int_value_must_be_gt_or_equal_to_allowed_int_min'), $debug);

			return;
		}

		if ($value > ($this->isUnsigned() ? self::INT_UNSIGNED_MAX : self::INT_SIGNED_MAX)) {
			$subject->reject($this->msg('int_value_must_be_lt_or_equal_to_allowed_int_max'), $debug);

			return;
		}

		$subject->accept($value);
	}

	/**
	 * @param int  $value
	 * @param bool $unsigned
	 *
	 * @throws TypesException
	 */
	private static function assertValidInt(int $value, bool $unsigned): void
	{
		if ($unsigned) {
			if ($value < self::INT_UNSIGNED_MIN) {
				throw new TypesException(
					\sprintf(
						'"%s" is not a valid unsigned int. Allowed min=%s.',
						$value,
						self::INT_UNSIGNED_MIN
					)
				);
			}

			if ($value > self::INT_UNSIGNED_MAX) {
				throw new TypesException(
					\sprintf(
						'"%s" is not a valid unsigned int. Allowed max=%s.',
						$value,
						self::INT_UNSIGNED_MAX
					)
				);
			}
		} elseif ($value < self::INT_SIGNED_MIN) {
			throw new TypesException(
				\sprintf(
					'"%s" is not a valid signed int. Allowed min=%s.',
					$value,
					self::INT_SIGNED_MIN
				)
			);
		} elseif ($value > self::INT_SIGNED_MAX) {
			throw new TypesException(
				\sprintf(
					'"%s" is not a valid signed int. Allowed max=%s.',
					$value,
					self::INT_SIGNED_MAX
				)
			);
		}
	}
}
