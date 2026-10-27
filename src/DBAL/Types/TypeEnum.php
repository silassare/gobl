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
use ReflectionEnum;
use Throwable;

/**
 * Class TypeEnum.
 *
 * The cases of the enum class are written in the type's options (`enum_cases`, name => value in
 * declaration order, and `enum_backing`), so a schema written to a migration keeps them as they were.
 * A migration is history: when its enum class is gone, the type is loaded detached from those frozen
 * cases, and reads and writes the backing values instead of the enum instances. The database never
 * needed the class: an enum column is a string column.
 *
 * @extends Type<mixed, null|BackedEnum|int|string>
 */
final class TypeEnum extends Type
{
	public const string NAME                = 'enum';
	public const string OPTION_ENUM_CLASS   = 'enum_class';
	public const string OPTION_ENUM_CASES   = 'enum_cases';
	public const string OPTION_ENUM_BACKING = 'enum_backing';

	/**
	 * Whether the enum is int-backed, from the backing type of the enum class or the frozen one.
	 * An int-backed enum stored as a string is cast to int before it is resolved,
	 * so `::from()` does not throw a TypeError with strict_types=1.
	 */
	private bool $is_int_backed = false;

	/**
	 * Whether the enum class is missing and the frozen cases stand for it.
	 */
	private bool $detached = false;

	/**
	 * TypeEnum constructor.
	 *
	 * @param null|class-string<BackedEnum> $enum_class
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

		$cases = [];

		foreach ($enum_class::cases() as $case) {
			$cases[$case->name] = $case->value;
		}

		$this->is_int_backed = 'int' === (string) new ReflectionEnum($enum_class)->getBackingType();
		$this->detached      = false;

		return $this->setOption(self::OPTION_ENUM_CLASS, $enum_class)
			->setOption(self::OPTION_ENUM_CASES, $cases)
			->setOption(self::OPTION_ENUM_BACKING, $this->is_int_backed ? 'int' : 'string');
	}

	/**
	 * Sets an enum whose class is missing, from the cases it had.
	 *
	 * @param string                    $enum_class the name of the missing class
	 * @param array<string, int|string> $cases      the cases, name => value
	 * @param null|string               $backing    `int` or `string`, from the values when null
	 *
	 * @throws TypesException
	 */
	public function detachedEnum(string $enum_class, array $cases, ?string $backing = null): static
	{
		$backing ??= \is_int(\reset($cases)) ? 'int' : 'string';

		if ('int' !== $backing && 'string' !== $backing) {
			throw new TypesException(\sprintf(
				'invalid enum backing type "%s" for "%s", expecting "int" or "string"',
				$backing,
				$enum_class
			));
		}

		foreach ($cases as $name => $value) {
			if (!\is_string($name) || ('int' === $backing ? !\is_int($value) : !\is_string($value))) {
				throw new TypesException(\sprintf(
					'invalid frozen cases for enum "%s": expecting a map of case name to %s value',
					$enum_class,
					$backing
				));
			}
		}

		$this->is_int_backed = 'int' === $backing;
		$this->detached      = true;

		return $this->setOption(self::OPTION_ENUM_CLASS, $enum_class)
			->setOption(self::OPTION_ENUM_CASES, $cases)
			->setOption(self::OPTION_ENUM_BACKING, $backing);
	}

	/**
	 * Whether the enum class is missing: values are then the backing values, not enum instances.
	 */
	public function isDetached(): bool
	{
		return $this->detached;
	}

	/**
	 * Returns the cases, name => value in declaration order.
	 *
	 * @return array<string, int|string>
	 */
	public function getEnumCases(): array
	{
		/** @var array<string, int|string> $cases */
		return $this->getOption(self::OPTION_ENUM_CASES, []);
	}

	#[Override]
	public static function getInstance(array $options): static
	{
		return new self()->configure($options);
	}

	/**
	 * @throws TypesException
	 */
	#[Override]
	public function configure(array $options): static
	{
		if (isset($options[self::OPTION_ENUM_CLASS])) {
			$enum_class = (string) $options[self::OPTION_ENUM_CLASS];
			$cases      = $options[self::OPTION_ENUM_CASES] ?? null;

			if (\is_array($cases) && !\class_exists($enum_class)) {
				$backing = $options[self::OPTION_ENUM_BACKING] ?? null;

				$this->detachedEnum($enum_class, $cases, null === $backing ? null : (string) $backing);
			} else {
				$this->enumClass($enum_class);
			}
		}

		return parent::configure($options);
	}

	/**
	 * {@inheritDoc}
	 *
	 * A default given as its backing value (as a migration's schema gives it) is the enum case: a
	 * default is trusted, not validated, so an entity would otherwise hold the bare value.
	 */
	#[Override]
	public function getDefault(): mixed
	{
		$default = parent::getDefault();

		$scalar = \is_int($default) || \is_string($default);

		if ($scalar && !$this->detached && $this->getOption(self::OPTION_ENUM_CLASS)) {
			try {
				return $this->toEnumValue($default);
			} catch (Throwable) {
				// not a case: validation refuses it where it is used
			}
		}

		return $default;
	}

	/**
	 * {@inheritDoc}
	 *
	 * The default is written as its backing value: the array may be written as PHP code (a migration),
	 * where an enum case would be an expression that needs the class to load.
	 */
	#[Override]
	public function toArray(): array
	{
		$options = parent::toArray();

		if (isset($options['default']) && $options['default'] instanceof BackedEnum) {
			$options['default'] = $options['default']->value;
		}

		return $options;
	}

	/**
	 * @throws Throwable
	 */
	#[Override]
	public function dbToPhp(mixed $value, RDBMSInterface $rdbms): BackedEnum|int|string|null
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
	 * @throws TypesException
	 */
	#[Override]
	public function getReadTypeHint(): ORMTypeHint
	{
		if ($this->detached) {
			return $this->is_int_backed ? ORMTypeHint::int() : ORMTypeHint::string();
		}

		return ORMTypeHint::string()
			->setPHPType(new PHPType(new PHPEnum($this->getEnumClass())));
	}

	/**
	 * @throws TypesException
	 */
	#[Override]
	public function getWriteTypeHint(): ORMTypeHint
	{
		if ($this->detached) {
			return $this->is_int_backed ? ORMTypeHint::int() : ORMTypeHint::string();
		}

		return ORMTypeHint::string()
			->setPHPType(new PHPType(new PHPEnum($this->getEnumClass()), 'string'));
	}

	/**
	 * @throws TypesInvalidValueException
	 * @throws TypesException
	 */
	#[Override]
	public function phpToDb(mixed $value, RDBMSInterface $rdbms): int|string|null
	{
		$clean = $this->validate($value)->getCleanValue();

		return $clean instanceof BackedEnum ? $clean->value : $clean;
	}

	/**
	 * Returns the enum class, the name of the missing one when detached ({@see isDetached()}).
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

		if ($this->detached) {
			$found = $this->toCaseValue($value);

			if (null === $found) {
				$subject->reject($this->msg('invalid_enum_value_type'), $debug);

				return;
			}

			$subject->accept($found);

			return;
		}

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
	 * @return BackedEnum|int|string the frozen backing value when detached
	 *
	 * @throws Throwable
	 */
	protected function toEnumValue(int|string $value): BackedEnum|int|string
	{
		if ($this->detached) {
			$found = $this->toCaseValue($value);

			if (null === $found) {
				throw new TypesException(
					\sprintf('"%s" is not a case of the enum "%s"', $value, $this->getEnumClass())
				);
			}

			return $found;
		}

		$cls = $this->getEnumClass();

		// An int-backed enum given the text of an integer, which is what every field of an HTML form or
		// of a multipart body is: `from('1')` is a TypeError under strict types, so such an enum could
		// only be submitted as JSON. Only a plain integer is read; `1.0`, ` 1` or `01` are not.
		if (
			\is_string($value)
			&& 'int' === (string) new ReflectionEnum($cls)->getBackingType()
			&& \preg_match('~^-?(?:0|[1-9]\d*)$~D', $value)
		) {
			$value = (int) $value;
		}

		return $cls::from($value);
	}

	/**
	 * Returns the frozen case value a value stands for, when detached; null when it is none.
	 *
	 * An integer text is read as an integer for an int-backed enum, as {@see toEnumValue()} does.
	 */
	private function toCaseValue(mixed $value): int|string|null
	{
		if ($value instanceof BackedEnum) {
			$value = $value->value;
		}

		if ($this->is_int_backed && \is_string($value) && \preg_match('~^-?(?:0|[1-9]\d*)$~D', $value)) {
			$value = (int) $value;
		}

		if ((\is_int($value) || \is_string($value)) && \in_array($value, $this->getEnumCases(), true)) {
			return $value;
		}

		return null;
	}
}
