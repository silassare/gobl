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
 * Class TypeBigint.
 */
class TypeBigint extends Type implements BaseTypeInterface
{
	public const NAME = 'bigint';

	public const BIGINT_REG = '~[-+]?(?:[1-9]\d*|0)~';

	public const BIGINT_UNSIGNED_REG = '~[+]?(?:[1-9]\d*|0)~';

	/**
	 * TypeBigint constructor.
	 *
	 * @param null|string $min      the minimum bigint value
	 * @param null|string $max      the maximum bigint value
	 * @param bool        $unsigned as unsigned bigint value
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

		!empty($message) && $this->msg('invalid_bigint_type', $message);

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
		!empty($message) && $this->msg('invalid_unsigned_bigint_type', $message);

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
		self::assertValidBigint($min, $this->isUnsigned());

		$max = $this->getOption('max');

		if (null !== $max && !self::isLt($min, $max, true)) {
			throw new TypesException(\sprintf('min=%s and max=%s is not a valid condition.', $min, $max));
		}

		!empty($message) && $this->msg('bigint_value_must_be_gt_or_equal_to_min', $message);

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
		self::assertValidBigint($max, $this->isUnsigned());

		if (isset($this->min) && !self::isLt($this->min, $max, true)) {
			throw new TypesException(\sprintf('min=%s and max=%s is not a valid condition.', $this->min, $max));
		}

		!empty($message) && $this->msg('bigint_value_must_be_lt_or_equal_to_max', $message);

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

		return parent::configure($options);
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
			if ($this->isAutoIncremented()) {
				return null;
			}

			$value = $this->getDefault();

			if (null === $value && $this->isNullable()) {
				return null;
			}
		}

		if (!\is_numeric($value)) {
			throw new TypesInvalidValueException($this->msg('invalid_bigint_type'), $debug);
		}

		if ($this->isUnsigned()) {
			if (!\preg_match(self::BIGINT_UNSIGNED_REG, (string) $value)) {
				throw new TypesInvalidValueException($this->msg('invalid_unsigned_bigint_type'), $debug);
			}
		} elseif (!\preg_match(self::BIGINT_REG, (string) $value)) {
			throw new TypesInvalidValueException($this->msg('invalid_bigint_type'), $debug);
		}

		$min = $this->getOption('min');

		if (null !== $min && !self::isLt($min, $value, true)) {
			throw new TypesInvalidValueException($this->msg('bigint_value_must_be_gt_or_equal_to_min'), $debug);
		}

		$max = $this->getOption('max');

		if (null !== $max && !self::isLt($value, $max, true)) {
			throw new TypesInvalidValueException($this->msg('bigint_value_must_be_lt_or_equal_to_max'), $debug);
		}

		return (string) $value;
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
	public function dbToPhp(mixed $value, RDBMSInterface $rdbms): ?string
	{
		return null === $value ? null : (string) $value;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 */
	public function phpToDb(mixed $value, RDBMSInterface $rdbms): ?string
	{
		return $this->validate($value);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getWriteTypeHint(): ORMTypeHint
	{
		return ORMTypeHint::bigint();
	}

	/**
	 * {@inheritDoc}
	 */
	public function getReadTypeHint(): ORMTypeHint
	{
		return ORMTypeHint::bigint()
			->addUniversalTypes(ORMUniversalType::INT);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getName(): string
	{
		return self::NAME;
	}

	/**
	 * Checks if a given value is a valid bigint.
	 *
	 * @param mixed $value
	 * @param bool  $unsigned
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
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
