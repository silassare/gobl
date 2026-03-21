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
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\Interfaces\ValidationSubjectInterface;
use Gobl\ORM\ORMTypeHint;
use Override;

/**
 * Class TypeBool.
 *
 * @extends BaseType<mixed, null|bool>
 */
final class TypeBool extends BaseType
{
	public const NAME = 'bool';

	/**
	 * @var array
	 */
	private static array $map_extended = [
		'1'     => true,
		'0'     => false,
		'true'  => true,
		'false' => false,
		'yes'   => true,
		'no'    => false,
		'on'    => true,
		'off'   => false,
		'y'     => true,
		'n'     => false,
	];

	/**
	 * TypeBool Constructor.
	 *
	 * @param bool        $strict  whether to limit bool value to (true,false,1,0)
	 * @param null|string $message the error message
	 */
	public function __construct(bool $strict = true, ?string $message = null)
	{
		$this->strict($strict);

		!empty($message) && $this->msg('invalid_bool_type', $message);

		parent::__construct($this);
	}

	/**
	 * Sets this type as (non-)strict.
	 *
	 * whether to limit bool value to (true,false,1,0)
	 *
	 * @param bool $strict
	 *
	 * @return static
	 */
	public function strict(bool $strict = true): static
	{
		return $this->setOption('strict', $strict);
	}

	#[Override]
	public static function getInstance(array $options): static
	{
		return (new static())->configure($options);
	}

	#[Override]
	public function getName(): string
	{
		return self::NAME;
	}

	#[Override]
	public function configure(array $options): static
	{
		if (isset($options['strict'])) {
			$this->strict((bool) $options['strict']);
		}

		return parent::configure($options);
	}

	#[Override]
	public function dbToPhp(mixed $value, RDBMSInterface $rdbms): ?bool
	{
		return null === $value ? null : (bool) $value;
	}

	#[Override]
	public function default(mixed $default): static
	{
		return parent::default((int) ((bool) $default));
	}

	#[Override]
	public function getAllowedFilterOperators(): array
	{
		$operators = [
			Operator::EQ,
			Operator::NEQ,
			Operator::IS_FALSE,
			Operator::IS_TRUE,
		];

		if ($this->isNullable()) {
			$operators[] = Operator::IS_NULL;
			$operators[] = Operator::IS_NOT_NULL;
		}

		return $operators;
	}

	#[Override]
	public function getEmptyValueOfType(): ?bool
	{
		return $this->isNullable() ? null : false;
	}

	#[Override]
	public function getReadTypeHint(): ORMTypeHint
	{
		return ORMTypeHint::bool();
	}

	#[Override]
	public function getWriteTypeHint(): ORMTypeHint
	{
		return ORMTypeHint::bool();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws TypesInvalidValueException
	 */
	#[Override]
	public function phpToDb(mixed $value, RDBMSInterface $rdbms): ?int
	{
		$value = $this->validate($value)->getCleanValue();

		if (null === $value) {
			return null;
		}

		return $value ? 1 : 0;
	}

	#[Override]
	public function castValueForFilter(mixed $value, Operator $operator, RDBMSInterface $rdbms): float|int|string|null
	{
		return $this->phpToDb($value, $rdbms);
	}

	/**
	 * Checks if this is strict.
	 *
	 * @return bool
	 */
	public function isStrict(): bool
	{
		return (bool) $this->getOption('strict', true);
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
			$value = $this->getDefault();

			if (null === $value && $this->isNullable()) {
				$subject->accept(null);

				return;
			}
			if (null === $value) {
				$subject->reject($this->msg('invalid_bool_type'), $debug);

				return;
			}
		}

		if (true === $value || false === $value || 1 === $value || 0 === $value) {
			$subject->accept((bool) $value);

			return;
		}

		if (\is_string($value) && !$this->isStrict()) {
			$value = \strtolower($value);

			if (isset(self::$map_extended[$value])) {
				$subject->accept(self::$map_extended[$value]);

				return;
			}
		}

		$subject->reject($this->msg('invalid_bool_type'), $debug);
	}
}
