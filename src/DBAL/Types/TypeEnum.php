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

use BackedEnum;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Types\Exceptions\TypesException;
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\Interfaces\ValidationSubjectInterface;
use Gobl\ORM\ORMTypeHint;
use OLIUP\CG\PHPEnum;
use OLIUP\CG\PHPType;
use Override;
use Throwable;

/**
 * Class TypeEnum.
 *
 * @extends Type<mixed, null|BackedEnum>
 */
final class TypeEnum extends Type
{
	public const NAME              = 'enum';
	public const OPTION_ENUM_CLASS = 'enum_class';

	/**
	 * Whether the enum is int-backed, determined from the backing type of the enum class when set.
	 * This is used to handle the common case where int-backed enums are stored as strings in the DB,
	 * so we can cast them to int before trying to resolve the enum value instance.
	 *
	 * For int-backed enums, cast the string to int before calling ::from()
	 * so we don't get a TypeError with strict_types=1.
	 */
	private bool $is_int_backed = false;

	/**
	 * TypeEnum constructor.
	 *
	 * @param null|class-string<BackedEnum> $enum_class
	 * @param null|string                   $message
	 *
	 * @throws TypesException
	 */
	public function __construct(
		?string $enum_class = null,
		?string $message = null
	) {
		!empty($message) && $this->msg('invalid_enum_value_type', $message);

		if ($enum_class) {
			$this->enumClass($enum_class);
		}

		parent::__construct(new TypeString(0, 128));
	}

	/**
	 * Sets the enum class.
	 *
	 * @param class-string<BackedEnum> $enum_class
	 * @param null|string              $message
	 *
	 * @return static
	 *
	 * @throws TypesException
	 */
	public function enumClass(string $enum_class, ?string $message = null): static
	{
		if (!\is_subclass_of($enum_class, BackedEnum::class)) {
			throw new TypesException(
				\sprintf(
					'invalid enum class, found "%s" while expecting subclass of "%s"',
					$enum_class,
					BackedEnum::class
				)
			);
		}

		!empty($message) && $this->msg('invalid_enum_value_type', $message);

		/** @var array<int, BackedEnum> $cases */
		$cases               = $enum_class::cases();
		$this->is_int_backed = !empty($cases) && \is_int($cases[0]->value);

		return $this->setOption(self::OPTION_ENUM_CLASS, $enum_class);
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
		if (isset($options[self::OPTION_ENUM_CLASS])) {
			$this->enumClass($options[self::OPTION_ENUM_CLASS]);
		}

		return parent::configure($options);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws Throwable
	 */
	#[Override]
	public function dbToPhp(mixed $value, RDBMSInterface $rdbms): ?BackedEnum
	{
		if (null === $value) {
			return null;
		}

		if ($this->is_int_backed && \is_string($value)) {
			$value = (int) $value;
		}

		return $this->toEnumValue($value);
	}

	#[Override]
	public function getEmptyValueOfType(): ?BackedEnum
	{
		return null;
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
	public function getReadTypeHint(): ORMTypeHint
	{
		return ORMTypeHint::string()
			->setPHPType(new PHPType(new PHPEnum($this->getEnumClass())));
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws TypesException
	 */
	#[Override]
	public function getWriteTypeHint(): ORMTypeHint
	{
		return ORMTypeHint::string()
			->setPHPType(new PHPType(new PHPEnum($this->getEnumClass()), 'string'));
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws TypesInvalidValueException
	 * @throws TypesException
	 */
	#[Override]
	public function phpToDb(mixed $value, RDBMSInterface $rdbms): int|string|null
	{
		return $this->validate($value)->getCleanValue()?->value;
	}

	/**
	 * Returns the enum class.
	 *
	 * @return class-string<BackedEnum>
	 *
	 * @throws TypesException
	 */
	public function getEnumClass(): string
	{
		/** @var null|class-string<BackedEnum> $cls */
		$cls = $this->getOption(self::OPTION_ENUM_CLASS);

		if (!$cls) {
			throw new TypesException('enum class not set');
		}

		return $cls;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws TypesInvalidValueException
	 * @throws TypesException
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
				$subject->reject($this->msg('invalid_enum_value_type'), $debug);

				return;
			}
		}

		$enum_cls = $this->getEnumClass();

		if (\is_string($value) || \is_int($value)) {
			try {
				$value = $this->toEnumValue($value);
			} catch (Throwable $t) {
				$subject->reject(new TypesInvalidValueException($this->msg('invalid_enum_value_type'), $debug, $t));

				return;
			}
		}

		if ($value instanceof $enum_cls) {
			$subject->accept($value);

			return;
		}

		$subject->reject($this->msg('invalid_enum_value_type'), $debug);
	}

	/**
	 * Returns the enum value instance from string.
	 *
	 * @param int|string $value
	 *
	 * @return BackedEnum
	 *
	 * @throws Throwable
	 */
	protected function toEnumValue(int|string $value): BackedEnum
	{
		$cls = $this->getEnumClass();

		return $cls::from($value);
	}
}
