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

use DateTime;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Types\Exceptions\TypesException;
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\Interfaces\BaseTypeInterface;

/**
 * Class TypeDate.
 */
class TypeDate extends Type
{
	public const NAME = 'date';

	public const FORMAT_TIMESTAMP = 'timestamp';
	public const FORMAT_DEFAULT   = \DATE_ATOM;

	/**
	 * TypeDate constructor.
	 *
	 * @param null|string $message the error message
	 *
	 * @throws TypesException
	 */
	public function __construct(?string $message = null)
	{
		!empty($message) && $this->msg('invalid_date_type', $message);

		parent::__construct(self::chooseBaseType());
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
	 *
	 * @throws TypesException
	 */
	public function configure(array $options): static
	{
		if (isset($options['precision']) && 'microseconds' === $options['precision']) {
			$this->microseconds();
		}

		if (isset($options['min'])) {
			$this->min((string) $options['min']);
		}

		if (isset($options['max'])) {
			$this->max((string) $options['max']);
		}

		if (isset($options['auto'])) {
			$this->auto((bool) $options['auto']);
		}

		if (isset($options['format'])) {
			$this->format((string) $options['format']);
		}

		return parent::configure($options);
	}

	/**
	 * Sets the date precision to microseconds.
	 *
	 * @return $this
	 *
	 * @throws TypesException
	 */
	public function microseconds(): static
	{
		$this->base_type = self::chooseBaseType(true);

		return $this->setOption('precision', 'microseconds');
	}

	/**
	 * Sets min date.
	 *
	 * @param string      $min
	 * @param null|string $message
	 *
	 * @return $this
	 *
	 * @throws TypesException
	 */
	public function min(string $min, ?string $message = null): static
	{
		$min_parsed = self::toTimestamp($min);

		if (null === $min_parsed) {
			throw new TypesException(\sprintf('min=%s is not a valid date string.', $min));
		}

		/** @var TypeBigint $bt */
		$bt = $this->base_type;

		$bt->min($min_parsed, !empty($message) ? $message : 'date_value_must_be_gt_or_equal_to_min');

		return $this->setOption('min', $min);
	}

	/**
	 * Sets max date.
	 *
	 * @param string      $max
	 * @param null|string $message
	 *
	 * @return $this
	 *
	 * @throws TypesException
	 */
	public function max(string $max, ?string $message = null): static
	{
		$max_parsed = self::toTimestamp($max);

		if (null === $max_parsed) {
			throw new TypesException(\sprintf('max=%s is not a valid date string.', $max));
		}

		/** @var TypeBigint $bt */
		$bt = $this->base_type;

		$bt->max($max_parsed, !empty($message) ? $message : 'date_value_must_be_lt_or_equal_to_max');

		return $this->setOption('max', $max);
	}

	/**
	 * To allow/disable auto value on null.
	 *
	 * @return $this
	 */
	public function auto(bool $auto = true): static
	{
		return $this->setOption('auto', $auto);
	}

	/**
	 * Sets the date format.
	 *
	 * @param string $format
	 *
	 * @return $this
	 */
	public function format(string $format): static
	{
		$this->setOption('format', $format);

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function dbToPhp(mixed $value, RDBMSInterface $rdbms): ?string
	{
		if (null === $value) {
			return null;
		}

		$format = $this->getOption('format', self::FORMAT_DEFAULT);

		if (self::FORMAT_TIMESTAMP === $format) {
			return (string) $value;
		}

		$value        = (string) $value;
		$value_format = \preg_match('~^\d+\.\d+$~', $value) ? 'U.u' : 'U';

		if ($date = DateTime::createFromFormat($value_format, $value)) {
			return $date->format($format);
		}

		return $value;
	}

	/**
	 * {@inheritDoc}
	 */
	public function default(mixed $default): static
	{
		$this->base_type->default($default);

		return parent::default($default);
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
	 * @throws TypesInvalidValueException
	 */
	public function phpToDb(mixed $value, RDBMSInterface $rdbms): ?string
	{
		return $this->validate($value);
	}

	/**
	 * {@inheritDoc}
	 */
	public function validate(mixed $value): ?string
	{
		$debug = [
			'value' => $value,
		];

		// if empty value and not (0 or 0.0)
		if (empty($value) && !\is_numeric($value)) {
			if ($this->isAuto()) {
				return $this->now();
			}

			$value = $this->getDefault();

			if (null === $value && $this->isNullable()) {
				return null;
			}
		}

		$value = self::toTimestamp($value);

		if (null === $value) {
			throw new TypesInvalidValueException('invalid_date_type', $debug);
		}

		try {
			$value = $this->base_type->validate($value);
		} catch (TypesInvalidValueException $e) {
			throw new TypesInvalidValueException('invalid_date', $debug, $e);
		}

		return $value;
	}

	/**
	 * Checks if the auto value is enabled.
	 */
	public function isAuto(): bool
	{
		return (bool) $this->getOption('auto');
	}

	/**
	 * Checks if the date precision is microseconds.
	 *
	 * @return bool
	 */
	public function isMicroseconds(): bool
	{
		return 'microseconds' === $this->getOption('precision');
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDefault(): ?string
	{
		$default = parent::getDefault();

		if (null === $default && $this->isAuto()) {
			$default = $this->now();
		}

		return $default;
	}

	/**
	 * {@inheritDoc}
	 */
	public function shouldEnforceDefaultValue(RDBMSInterface $rdbms): bool
	{
		return false;
	}

	/**
	 * Choose appropriate base type.
	 *
	 * @throws TypesException
	 */
	protected static function chooseBaseType(bool $microseconds = false): BaseTypeInterface
	{
		// we use bigint or decimal because of the year 2038 issue
		// https://stackoverflow.com/questions/2012589/php-mysql-year-2038-bug-what-is-it-how-to-solve-it

		$base_type = new TypeBigint();

		if ($microseconds) {
			$base_type = new TypeDecimal();

			$base_type->precision(20, 6);
		}

		return $base_type;
	}

	/**
	 * Returns the current date.
	 *
	 * @return string
	 */
	protected function now(): string
	{
		return $this->isMicroseconds() ? (string) \microtime(true) : (string) \time();
	}

	/**
	 * Convert value to timestamp.
	 *
	 * @param mixed $value
	 *
	 * @return null|string
	 */
	private static function toTimestamp(mixed $value): ?string
	{
		if (\is_string($value) && !\preg_match('~^\d+(?:\.\d+)?$~', $value)) {
			$converted = \strtotime($value);

			if (false !== $converted) {
				return (string) $converted;
			}

			return null;
		}

		if (\is_numeric($value)) {
			return (string) $value;
		}

		return null;
	}
}
