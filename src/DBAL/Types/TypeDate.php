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

/**
 * Class TypeDate.
 */
class TypeDate extends Type
{
	public const NAME = 'date';

	public const FORMAT_TIMESTAMP = 'timestamp';

	/**
	 * TypeDate constructor.
	 *
	 * @param null|string $message the error message
	 */
	public function __construct(?string $message = null)
	{
		!empty($message) && $this->msg('invalid_date_type', $message);

		parent::__construct(new TypeBigint());
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

		if (isset($options['auto'])) {
			$this->auto((bool) $options['auto']);
		}

		if (isset($options['format'])) {
			$this->format((string) $options['format']);
		}

		return parent::configure($options);
	}

	/**
	 * Sets min date.
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
		$min_parsed = self::toTimestamp($min);

		if (null === $min_parsed) {
			throw new TypesException(\sprintf('min=%s is not a valid date string.', $min));
		}

		/** @var \Gobl\DBAL\Types\TypeBigint $bt */
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
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 */
	public function max(string $max, ?string $message = null): self
	{
		$max_parsed = self::toTimestamp($max);

		if (null === $max_parsed) {
			throw new TypesException(\sprintf('max=%s is not a valid date string.', $max));
		}

		/** @var \Gobl\DBAL\Types\TypeBigint $bt */
		$bt = $this->base_type;

		$bt->max($max_parsed, !empty($message) ? $message : 'date_value_must_be_lt_or_equal_to_max');

		return $this->setOption('max', $max);
	}

	/**
	 * To allow/disable auto value on null.
	 *
	 * @return $this
	 */
	public function auto(bool $auto = true): self
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
	public function format(string $format): self
	{
		$this->setOption('format', $format);

		return $this;
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
	public function setDefault(mixed $default): self
	{
		$this->base_type->setDefault($default);

		return parent::setDefault($default);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDefault(): ?string
	{
		$default = parent::getDefault();
		$auto    = $this->getOption('auto');

		if (null === $default && $auto) {
			$default = (string) \time();
		}

		return $default;
	}

	/**
	 * {@inheritDoc}
	 */
	public function dbToPhp(mixed $value, RDBMSInterface $rdbms): ?string
	{
		if (null === $value) {
			return null;
		}

		$format = $this->getOption('format', self::FORMAT_TIMESTAMP);

		if (self::FORMAT_TIMESTAMP === $format) {
			return (string) $value;
		}

		if ($formatted = \date($this->getOption('format', $format), $value)) {
			return $formatted;
		}

		return (string) $value;
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
	public function validate(mixed $value): ?string
	{
		$debug = [
			'value' => $value,
		];

		if (empty($value) && 0 !== $value) {
			if ($this->getOption('auto')) {
				return (string) \time();
			}

			$value = $this->getDefault();

			if (null === $value && $this->isNullAble()) {
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
	 * @param $value
	 *
	 * @return null|string
	 */
	private static function toTimestamp($value): ?string
	{
		if (\is_string($value) && !\preg_match('~^\d+$~', $value)) {
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
