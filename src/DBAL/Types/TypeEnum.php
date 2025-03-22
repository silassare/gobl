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
use Gobl\ORM\ORMTypeHint;
use OLIUP\CG\PHPEnum;
use OLIUP\CG\PHPType;
use Throwable;

/**
 * Class TypeEnum.
 */
class TypeEnum extends Type
{
	public const NAME = 'enum';

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
	 * @return $this
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

		return $this->setOption('enum_class', $enum_class);
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
		if (isset($options['enum_class'])) {
			$this->enumClass($options['enum_class']);
		}

		return parent::configure($options);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws TypesException
	 */
	public function dbToPhp(mixed $value, RDBMSInterface $rdbms): ?BackedEnum
	{
		if (null === $value) {
			return null;
		}

		return $this->toEnumValue($value);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getEmptyValueOfType(): ?BackedEnum
	{
		return null;
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
	 * @throws TypesException
	 */
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
	public function phpToDb(mixed $value, RDBMSInterface $rdbms): null|int|string
	{
		return $this->validate($value)?->value;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return null|BackedEnum
	 *
	 * @throws TypesInvalidValueException
	 * @throws TypesException
	 */
	public function validate(mixed $value): ?BackedEnum
	{
		$debug = [
			'value' => $value,
		];

		if (null === $value) {
			$value = $this->getDefault();

			if (null === $value && $this->isNullable()) {
				return null;
			}
		}

		/** @var class-string<BackedEnum> $enum_cls */
		$enum_cls = $this->getEnumClass();
		if (\is_string($value) || \is_int($value)) {
			try {
				$value = $this->toEnumValue($value);
			} catch (Throwable $t) {
				throw new TypesInvalidValueException($this->msg('invalid_enum_value_type'), $debug, $t);
			}
		}

		if (!$value instanceof $enum_cls) {
			throw new TypesInvalidValueException($this->msg('invalid_enum_value_type'), $debug);
		}

		return $value;
	}

	/**
	 * Returns the enum value instance from string.
	 *
	 * @param int|string $value
	 *
	 * @return BackedEnum
	 *
	 * @throws TypesException
	 */
	protected function toEnumValue(int|string $value): BackedEnum
	{
		/** @var class-string<BackedEnum> $cls */
		$cls = $this->getEnumClass();

		return $cls::from($value);
	}

	/**
	 * Returns the enum class.
	 *
	 * @return string
	 *
	 * @throws TypesException
	 */
	protected function getEnumClass(): string
	{
		$cls = $this->getOption('enum_class');

		if (!$cls) {
			throw new TypesException('enum class not set');
		}

		return $cls;
	}
}
