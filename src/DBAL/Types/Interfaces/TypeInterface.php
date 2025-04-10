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

namespace Gobl\DBAL\Types\Interfaces;

use Gobl\DBAL\Filters\Filter;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Operator;
use Gobl\DBAL\Types\Exceptions\TypesException;
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\ORM\ORMTypeHint;
use PHPUtils\Interfaces\ArrayCapableInterface;

/**
 * Interface TypeInterface.
 */
interface TypeInterface extends ArrayCapableInterface
{
	/**
	 * Gets type instance based on given options.
	 *
	 * @param array $options the options
	 *
	 * @return $this
	 *
	 * @throws TypesException when options is invalid
	 */
	public static function getInstance(array $options): static;

	/**
	 * Locks the type to prevent further changes.
	 *
	 * @return $this
	 */
	public function lock(): static;

	/**
	 * @return BaseTypeInterface
	 */
	public function getBaseType(): BaseTypeInterface;

	/**
	 * Gets type name.
	 *
	 * @return string
	 */
	public function getName(): string;

	/**
	 * Enable null value.
	 *
	 * @param bool $nullable
	 *
	 * @return $this
	 */
	public function nullable(bool $nullable = true): static;

	/**
	 * This is used to prevent returning empty value when the column
	 * is supposed to be not null and no value is specified yet on the entity instance.
	 *
	 * @return mixed
	 */
	public function getEmptyValueOfType(): mixed;

	/**
	 * Checks if the type allow null value.
	 *
	 * @return bool
	 */
	public function isNullable(): bool;

	/**
	 * Auto-increment allows a unique number to be generated,
	 * when a new record is inserted.
	 *
	 * @param bool $auto_increment
	 *
	 * @return $this
	 */
	public function autoIncrement(bool $auto_increment = true): static;

	/**
	 * Checks if the type is auto-incremented.
	 *
	 * @return bool
	 */
	public function isAutoIncremented(): bool;

	/**
	 * Gets the default value.
	 *
	 * @return mixed
	 */
	public function getDefault(): mixed;

	/**
	 * Checks if this type has default value.
	 *
	 * @return bool
	 */
	public function hasDefault(): bool;

	/**
	 * Explicitly set the default value.
	 *
	 * @param mixed $default the value to use as default
	 *
	 * @return $this
	 */
	public function default(mixed $default): static;

	/**
	 * Called to validate a form field value.
	 *
	 * @param mixed $value the value to validate
	 *
	 * @return mixed the cleaned value to use
	 *
	 * @throws TypesInvalidValueException
	 */
	public function validate(mixed $value): mixed;

	/**
	 * Called to convert db value to php compatible value.
	 *
	 * @param null|mixed     $value the value to convert
	 * @param RDBMSInterface $rdbms the RDBMS
	 *
	 * @return mixed
	 */
	public function dbToPhp(mixed $value, RDBMSInterface $rdbms): mixed;

	/**
	 * Called to convert php value to db compatible value.
	 *
	 * ```
	 * IMPORTANT this should validate the value first
	 * according to the current type options.
	 * ```
	 *
	 * @param null|mixed     $value the value to convert
	 * @param RDBMSInterface $rdbms the RDBMS
	 *
	 * @return null|float|int|string
	 */
	public function phpToDb(mixed $value, RDBMSInterface $rdbms): null|float|int|string;

	/**
	 * Should we enforce default value at the database level.
	 *
	 * @param RDBMSInterface $rdbms
	 *
	 * @return bool
	 */
	public function shouldEnforceDefaultValue(RDBMSInterface $rdbms): bool;

	/**
	 * This is used to make sure that when the base type does not have a default value
	 * we use a default value provided by the type.
	 *
	 * @param RDBMSInterface $rdbms
	 *
	 * @return null|string
	 */
	public function dbQueryDefault(RDBMSInterface $rdbms): ?string;

	/**
	 * Asserts operator and value are allowed for this type.
	 *
	 * @param Filter $filter
	 */
	public function assertFilterAllowed(Filter $filter): void;

	/**
	 * Should we enforce query expression value type.
	 *
	 * @param RDBMSInterface $rdbms the RDBMS
	 *
	 * @return bool
	 */
	public function shouldEnforceQueryExpressionValueType(RDBMSInterface $rdbms): bool;

	/**
	 * Called to enforce query expression value type.
	 *
	 * @param string         $expression the query expression
	 * @param RDBMSInterface $rdbms      the RDBMS
	 *
	 * @return string
	 */
	public function enforceQueryExpressionValueType(string $expression, RDBMSInterface $rdbms): string;

	/**
	 * Returns allowed filters operators.
	 *
	 * @return Operator[]
	 */
	public function getAllowedFilterOperators(): array;

	/**
	 * Adds option key value pair.
	 *
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return $this
	 */
	public function setOption(string $key, mixed $value): static;

	/**
	 * Gets a given option key value.
	 *
	 * @param string     $key
	 * @param null|mixed $default
	 *
	 * @return mixed
	 */
	public function getOption(string $key, mixed $default = null): mixed;

	/**
	 * Gets return Read/Getter type hint.
	 *
	 * Useful for the ORM class generator.
	 *
	 * @return ORMTypeHint
	 */
	public function getReadTypeHint(): ORMTypeHint;

	/**
	 * Gets allowed Write/Setter type hint.
	 *
	 * Useful for the ORM class generator.
	 *
	 * @return ORMTypeHint
	 */
	public function getWriteTypeHint(): ORMTypeHint;

	/**
	 * Apply default, null and auto_increment options.
	 *
	 * @param array $options
	 *
	 * @return $this
	 */
	public function configure(array $options): static;
}
