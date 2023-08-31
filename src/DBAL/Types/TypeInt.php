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

/**
 * Class TypeInt.
 */
class TypeInt extends Type implements BaseTypeInterface
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
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
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
	 * Sets as unsigned.
	 *
	 * @param bool        $unsigned
	 * @param null|string $message
	 *
	 * @return $this
	 */
	public function unsigned(bool $unsigned = true, ?string $message = null): static
	{
		!empty($message) && $this->msg('invalid_unsigned_int_type', $message);

		return $this->setOption('unsigned', $unsigned);
	}

	/**
	 * Sets min value.
	 *
	 * @param int         $min
	 * @param null|string $message
	 *
	 * @return $this
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
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
	 * @param int         $max
	 * @param null|string $message
	 *
	 * @return $this
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
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
	 * {@inheritDoc}
	 */
	public static function getInstance(array $options): static
	{
		return (new self())->configure($options);
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
	 *
	 * @return null|int
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
	 */
	public function validate(mixed $value): ?int
	{
		$debug = [
			'value' => $value,
		];

		if (null === $value) {
			if ($this->isAutoIncremented()) {
				return null;
			}

			$value = $this->getDefault();

			if (null === $value && $this->isNullable()) {
				return null;
			}
		}

		if (!\is_numeric($value)) {
			throw new TypesInvalidValueException($this->msg('invalid_int_type'), $debug);
		}

		$value += 0;

		if (!\is_int($value)) {
			throw new TypesInvalidValueException($this->msg('invalid_int_type'), $debug);
		}

		if (0 > $value && $this->isUnsigned()) {
			throw new TypesInvalidValueException($this->msg('invalid_unsigned_int_type'), $debug);
		}

		$min = $this->getOption('min');

		if (null !== $min && $value < $min) {
			throw new TypesInvalidValueException($this->msg('int_value_must_be_gt_or_equal_to_min'), $debug);
		}

		$max = $this->getOption('max');

		if (null !== $max && ($value > $max)) {
			throw new TypesInvalidValueException($this->msg('int_value_must_be_lt_or_equal_to_max'), $debug);
		}

		if ($value < ($this->isUnsigned() ? self::INT_UNSIGNED_MIN : self::INT_SIGNED_MIN)) {
			throw new TypesInvalidValueException(
				$this->msg(
					'int_value_must_be_gt_or_equal_to_allowed_int_min'
				),
				$debug
			);
		}

		if ($value > ($this->isUnsigned() ? self::INT_UNSIGNED_MAX : self::INT_SIGNED_MAX)) {
			throw new TypesInvalidValueException(
				$this->msg(
					'int_value_must_be_lt_or_equal_to_allowed_int_max'
				),
				$debug
			);
		}

		return $value;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 */
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

	/**
	 * {@inheritDoc}
	 */
	public function dbToPhp(mixed $value, RDBMSInterface $rdbms): ?int
	{
		return null === $value ? null : (int) $value;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getEmptyValueOfType(): ?int
	{
		return $this->isNullable() ? null : 0;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getReadTypeHint(): ORMTypeHint
	{
		return ORMTypeHint::int();
	}

	/**
	 * {@inheritDoc}
	 */
	public function getWriteTypeHint(): ORMTypeHint
	{
		return ORMTypeHint::int();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
	 */
	public function phpToDb(mixed $value, RDBMSInterface $rdbms): ?int
	{
		return $this->validate($value);
	}

	/**
	 * @param int  $value
	 * @param bool $unsigned
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
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
