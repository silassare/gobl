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

/**
 * Class TypeBigint.
 *
 * @extends BaseType<mixed, null|string>
 */
class TypeBigint extends BaseType
{
	public const BIGINT_REG          = '~[-+]?(?:[1-9]\d*|0)~';
	public const BIGINT_UNSIGNED_REG = '~[+]?(?:[1-9]\d*|0)~';
	public const NAME                = 'bigint';

	/**
	 * TypeBigint constructor.
	 *
	 * @param null|string $min      the minimum bigint value
	 * @param null|string $max      the maximum bigint value
	 * @param bool        $unsigned as unsigned bigint value
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

		if (null !== $min) {
			$this->min($min);
		}

		if (null !== $max) {
			$this->max($max);
		}

		!empty($message) && $this->msg('invalid_bigint_type', $message);

		parent::__construct($this);
	}

	/**
	 * Sets min value.
	 *
	 * @param int|string  $min
	 * @param null|string $message
	 *
	 * @return $this
	 *
	 * @throws TypesException
	 */
	public function min(int|string $min, ?string $message = null): static
	{
		self::assertValidBigint($min, $this->isUnsigned());

		$max = $this->getOption('max');

		if (null !== $max && !self::isLt($min, $max, true)) {
			throw new TypesException(\sprintf('min=%s and max=%s is not a valid condition.', $min, $max));
		}

		!empty($message) && $this->msg('bigint_value_must_be_gt_or_equal_to_min', $message);

		return $this->setOption('min', $min);
	}

	/**
	 * Sets max value.
	 *
	 * @param int|string  $max
	 * @param null|string $message
	 *
	 * @return $this
	 *
	 * @throws TypesException
	 */
	public function max(int|string $max, ?string $message = null): static
	{
		self::assertValidBigint($max, $this->isUnsigned());

		$min = $this->getOption('min');

		if (null !== $min && !self::isLt($min, $max, true)) {
			throw new TypesException(\sprintf('min=%s and max=%s is not a valid condition.', $min, $max));
		}

		!empty($message) && $this->msg('bigint_value_must_be_lt_or_equal_to_max', $message);

		return $this->setOption('max', $max);
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
		!empty($message) && $this->msg('invalid_unsigned_bigint_type', $message);

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

	public static function getInstance(array $options): static
	{
		return (new self())->configure($options);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws TypesException
	 */
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

		return parent::configure($options);
	}

	public function getName(): string
	{
		return self::NAME;
	}

	public function dbToPhp(mixed $value, RDBMSInterface $rdbms): ?string
	{
		return null === $value ? null : (string) $value;
	}

	public function getEmptyValueOfType(): ?int
	{
		return $this->isNullable() ? null : 0;
	}

	public function getReadTypeHint(): ORMTypeHint
	{
		return ORMTypeHint::bigint();
	}

	public function getWriteTypeHint(): ORMTypeHint
	{
		return ORMTypeHint::bigint()
			->addUniversalTypes(ORMUniversalType::INT);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws TypesException
	 */
	public function phpToDb(mixed $value, RDBMSInterface $rdbms): ?string
	{
		return $this->validate($value)->getCleanValue();
	}

	public function castValueForFilter(mixed $value, Operator $operator, RDBMSInterface $rdbms): float|int|string|null
	{
		return $this->phpToDb($value, $rdbms);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws TypesInvalidValueException
	 */
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
				$subject->reject($this->msg('invalid_bigint_type'), $debug);

				return;
			}
		}

		if (!\is_numeric($value)) {
			$subject->reject($this->msg('invalid_bigint_type'), $debug);

			return;
		}

		if ($this->isUnsigned()) {
			if (!\preg_match(self::BIGINT_UNSIGNED_REG, (string) $value)) {
				$subject->reject($this->msg('invalid_unsigned_bigint_type'), $debug);

				return;
			}
		} elseif (!\preg_match(self::BIGINT_REG, (string) $value)) {
			$subject->reject($this->msg('invalid_bigint_type'), $debug);

			return;
		}

		$min = $this->getOption('min');

		if (null !== $min && !self::isLt($min, $value, true)) {
			$subject->reject($this->msg('bigint_value_must_be_gt_or_equal_to_min'), $debug);

			return;
		}

		$max = $this->getOption('max');

		if (null !== $max && !self::isLt($value, $max, true)) {
			$subject->reject($this->msg('bigint_value_must_be_lt_or_equal_to_max'), $debug);

			return;
		}

		$subject->accept((string) $value);
	}

	/**
	 * Checks if a given value is a valid bigint.
	 *
	 * @param mixed $value
	 * @param bool  $unsigned
	 *
	 * @throws TypesException
	 */
	private static function assertValidBigint(mixed $value, bool $unsigned): void
	{
		if (!\is_numeric($value)) {
			throw new TypesException(\sprintf('"%s" is not a valid bigint.', $value));
		}

		if ($unsigned) {
			if (!\preg_match(self::BIGINT_UNSIGNED_REG, (string) $value)) {
				throw new TypesException(\sprintf('"%s" is not a valid unsigned bigint.', $value));
			}
		} elseif (!\preg_match(self::BIGINT_REG, (string) $value)) {
			throw new TypesException(\sprintf('"%s" is not a valid bigint.', $value));
		}
	}
}
