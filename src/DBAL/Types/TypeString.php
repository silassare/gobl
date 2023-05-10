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

use BackedEnum;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Types\Exceptions\TypesException;
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\Interfaces\BaseTypeInterface;
use Gobl\ORM\ORMTypeHint;

/**
 * Class TypeString.
 */
class TypeString extends Type implements BaseTypeInterface
{
	public const NAME = 'string';

	/**
	 * TypeString constructor.
	 *
	 * @param null|int    $min     the minimum string length
	 * @param null|int    $max     the maximum string length
	 * @param null|string $pattern the string pattern
	 * @param string[]    $one_of  the list of allowed string
	 * @param null|string $message the error message
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 */
	public function __construct(
		?int $min = null,
		?int $max = null,
		?string $pattern = null,
		array $one_of = [],
		?string $message = null
	) {
		if (isset($min)) {
			$this->min($min);
		}

		if (isset($max)) {
			$this->max($max);
		}

		if (isset($pattern)) {
			$this->pattern($pattern);
		}

		if (!empty($one_of)) {
			$this->oneOf($one_of);
		}

		!empty($message) && $this->msg('invalid_string_type', $message);

		parent::__construct($this);
	}

	/**
	 * Sets string min length.
	 *
	 * @param int         $min
	 * @param null|string $message
	 *
	 * @return $this
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 */
	public function min(int $min, ?string $message = null): self
	{
		self::assertSafeIntRange($min, $this->getOption('max', \PHP_INT_MAX), 0);

		!empty($message) && $this->msg('string_length_must_be_gt_or_equal_to_min', $message);

		return $this->setOption('min', $min);
	}

	/**
	 * Sets string max length.
	 *
	 * @param int         $max
	 * @param null|string $message
	 *
	 * @return $this
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 */
	public function max(int $max, ?string $message = null): self
	{
		self::assertSafeIntRange($this->getOption('min', 0), $max, 0);

		!empty($message) && $this->msg('string_length_must_be_lt_or_equal_to_max', $message);

		return $this->setOption('max', $max);
	}

	/**
	 * Sets the string pattern (regular expression).
	 *
	 * @param string      $pattern
	 * @param null|string $message
	 *
	 * @return $this
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 */
	public function pattern(string $pattern, ?string $message = null): self
	{
		if (false === \preg_match($pattern, '')) {
			throw new TypesException(\sprintf('invalid regular expression: %s', $pattern));
		}

		!empty($message) && $this->msg('string_pattern_check_fails', $message);

		return $this->setOption('pattern', $pattern);
	}

	/**
	 * Sets the allowed string list.
	 *
	 * @param string[]    $list
	 * @param null|string $message
	 *
	 * @return $this
	 */
	public function oneOf(array $list, ?string $message = null): self
	{
		!empty($message) && $this->msg('string_not_in_allowed_list', $message);

		return $this->setOption('one_of', \array_unique($list));
	}

	/**
	 * Sets the allowed string list from enum class.
	 *
	 * @param class-string<BackedEnum> $enum_class
	 * @param null|string              $message
	 *
	 * @return $this
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 */
	public function enum(string $enum_class, ?string $message = null): self
	{
		if (!\is_subclass_of($enum_class, BackedEnum::class)) {
			throw new TypesException(\sprintf(
				'invalid enum class, found "%s" while expecting subclass of "%s"',
				$enum_class,
				BackedEnum::class
			));
		}

		/** @var BackedEnum[] $cases */
		$cases = $enum_class::cases();
		$list  = [];

		foreach ($cases as $case) {
			$list[] = (string) $case->value;
		}

		return $this->oneOf($list, $message);
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
			$this->min((int) $options['min']);
		}

		if (isset($options['max'])) {
			$this->max((int) $options['max']);
		}

		if (isset($options['truncate'])) {
			$this->truncate((bool) $options['truncate']);
		}

		if (isset($options['multiline'])) {
			$this->multiline((bool) $options['multiline']);
		}

		if (isset($options['trim'])) {
			$this->trim((bool) $options['trim']);
		}

		if (isset($options['pattern'])) {
			$this->pattern((string) $options['pattern']);
		}

		if (isset($options['one_of']) && \is_array($options['one_of'])) {
			$this->oneOf($options['one_of']);
		}

		return parent::configure($options);
	}

	/**
	 * Enable truncating when string length greater than max.
	 *
	 * @param bool $truncate
	 *
	 * @return $this
	 */
	public function truncate(bool $truncate = true): self
	{
		return $this->setOption('truncate', $truncate);
	}

	/**
	 * One line string.
	 *
	 * @param bool $one_line
	 *
	 * @return $this
	 */
	public function oneLine(bool $one_line = true): self
	{
		return $this->multiline(!$one_line);
	}

	/**
	 * Multiline string.
	 *
	 * @param bool $multiline
	 *
	 * @return $this
	 */
	public function multiline(bool $multiline = true): self
	{
		return $this->setOption('multiline', $multiline);
	}

	/**
	 * Checks if truncate is enabled.
	 *
	 * @return bool
	 */
	public function canTruncate(): bool
	{
		return (bool) $this->getOption('truncate', false);
	}

	/**
	 * Trim string.
	 *
	 * @param bool $trim
	 *
	 * @return $this
	 */
	public function trim(bool $trim = true): self
	{
		return $this->setOption('trim', $trim);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getEmptyValueOfType(): ?string
	{
		return $this->isNullable() ? null : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function getWriteTypeHint(): ORMTypeHint
	{
		return ORMTypeHint::string();
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

		if (\is_numeric($value)) { // accept numeric value
			$value = (string) $value;
		}

		if (null === $value || '' === $value) {
			$value = $this->getDefault();

			if (null === $value && $this->isNullable()) {
				return null;
			}
		}

		if (!\is_string($value)) {
			throw new TypesInvalidValueException($this->msg('invalid_string_type'), $debug);
		}

		if (false === $this->getOption('multiline')) {
			$value = \preg_replace('~\\s+~', ' ', $value);
		}

		if (true === $this->getOption('trim')) {
			$value = \trim($value);
		}

		$min = $this->getOption('min');

		if (null !== $min && \strlen($value) < $min) {
			throw new TypesInvalidValueException($this->msg('string_length_must_be_gt_or_equal_to_min'), $debug);
		}

		$max = $this->getOption('max');

		if (null !== $max && \strlen($value) > $max) {
			if (!$this->canTruncate()) {
				throw new TypesInvalidValueException($this->msg('string_length_must_be_lt_or_equal_to_max'), $debug);
			}

			$value = \substr($value, 0, $max);
		}

		$pattern = $this->getOption('pattern');

		if (null !== $pattern && !\preg_match($pattern, $value)) {
			throw new TypesInvalidValueException($this->msg('string_pattern_check_fails'), $debug);
		}

		$one_of = $this->getOption('one_of');

		if (!empty($one_of) && !\in_array($value, $one_of, true)) {
			throw new TypesInvalidValueException($this->msg('string_not_in_allowed_list'), $debug);
		}

		return $value;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getReadTypeHint(): ORMTypeHint
	{
		return ORMTypeHint::string();
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
