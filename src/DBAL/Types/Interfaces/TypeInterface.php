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

namespace Gobl\DBAL\Types\Interfaces;

use Gobl\DBAL\Column;
use Gobl\DBAL\Filters\Filter;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Operator;
use Gobl\DBAL\Table;
use Gobl\DBAL\Types\Exceptions\TypesException;
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\ORM\ORMTableQuery;
use Gobl\ORM\ORMTypeHint;
use OLIUP\CG\PHPMethod;
use PHPUtils\Interfaces\ArrayCapableInterface;
use PHPUtils\Interfaces\LockInterface;

/**
 * Interface TypeInterface.
 *
 * @template TUnsafe
 * @template TClean
 */
interface TypeInterface extends ArrayCapableInterface, LockInterface
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
	 * Runs the full validation pipeline on an already-created subject.
	 *
	 * The pipeline calls hooks in order:
	 *  1. {@see TypeValidatorInterface::preValidate()} -- skipped when already terminal.
	 *  2. {@see Type::runValidation()} -- skipped when already terminal.
	 *  3. {@see TypeValidatorInterface::postValidate()} -- always runs.
	 *
	 * Returns `true` when the subject ends up valid, `false` otherwise.
	 * This method does NOT throw; inspect `$subject->getRejectionException()` for the cause.
	 * For a throw-on-failure convenience see {@see validate()}.
	 */
	public function applyValidation(ValidationSubjectInterface $subject): bool;

	/**
	 * Creates a validation subject wrapping the given raw value.
	 *
	 * @param mixed  $value          the raw value to validate
	 * @param string $reference      short reference for error messages (e.g. column short name)
	 * @param string $referenceDebug verbose debug reference (e.g. column full name or FQCN)
	 *
	 * @return ValidationSubjectInterface<TUnsafe, TClean>
	 */
	public function createValidationSubject(mixed $value, string $reference = '', string $referenceDebug = ''): ValidationSubjectInterface;

	/**
	 * Validates `$value` through the full pipeline and returns the subject, or throws on failure.
	 *
	 * Internally creates a {@see ValidationSubjectInterface} via {@see createValidationSubject()},
	 * runs {@see applyValidation()}, and re-throws the stored rejection exception
	 * (a {@see TypesInvalidValueException}) on failure.
	 *
	 * If a {@see ValidationSubjectInterface} instance is passed as `$value`, it is used
	 * directly (the `$reference` and `$referenceDebug` arguments are ignored in that case).
	 *
	 * Use `$subject->getCleanValue()` on the returned subject to read the clean value.
	 *
	 * @param mixed|ValidationSubjectInterface $value          the raw value to validate, or an existing subject
	 * @param string                           $reference      short reference for error messages
	 * @param string                           $referenceDebug verbose debug reference
	 *
	 * @return ValidationSubjectInterface<TUnsafe, TClean> the subject carrying the clean value
	 *
	 * @throws TypesInvalidValueException when validation fails
	 */
	public function validate(mixed $value, string $reference = '', string $referenceDebug = ''): ValidationSubjectInterface;

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
	public function phpToDb(mixed $value, RDBMSInterface $rdbms): float|int|string|null;

	/**
	 * Converts a PHP filter right-operand value to its DB-compatible form.
	 *
	 * Called by {@see Gobl\DBAL\Filters\Filters::add()} via {@see Gobl\DBAL\Types\Utils\TypeUtils::runCastValueForFilter()}
	 * when the left operand resolves to a known column, so that filter values are coerced to the same DB representation as
	 * values written by the ORM (e.g. date strings become Unix timestamps, booleans become
	 * 0/1 integers).
	 *
	 * The default implementation in {@see Type} returns scalar values unchanged, which is
	 * correct for string-stored types (TypeString, TypeEnum, TypeJSON, etc.).
	 * Types that store a different representation (TypeDate, TypeBool, TypeInt, TypeFloat,
	 * TypeBigint, TypeDecimal) override this and delegate to {@see phpToDb()}.
	 *
	 * Custom type implementations can override this method to apply their own conversion
	 * strategy without affecting the full {@see phpToDb()} write path.
	 *
	 * NOTE: This method is NOT called for:
	 *  - unary operators (IS_NULL, IS_NOT_NULL, IS_TRUE, IS_FALSE) -- no right operand
	 *  - CONTAINS and HAS_KEY -- right operand is JSON-specific, not a column-typed value
	 *  - subquery or expression right operands (QBSelect, QBExpression)
	 *
	 * @param mixed          $value    the raw PHP value provided as the filter right operand
	 * @param Operator       $operator the filter operator being applied
	 * @param RDBMSInterface $rdbms    the current RDBMS
	 *
	 * @return null|float|int|string the value coerced to its DB form
	 */
	public function castValueForFilter(mixed $value, Operator $operator, RDBMSInterface $rdbms): float|int|string|null;

	/**
	 * Whether {@see castValueForFilter()} should be called for the given operator.
	 *
	 * Returning `false` skips the cast entirely for that operator/rdbms combination,
	 * which is useful when a type wants to convert for EQ/IN but pass LIKE patterns through unchanged.
	 *
	 * @param Operator       $operator the filter operator being applied
	 * @param RDBMSInterface $rdbms    the current RDBMS
	 *
	 * @return bool
	 */
	public function shouldCastValueForFilter(Operator $operator, RDBMSInterface $rdbms): bool;

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
	 * Returns allowed filters operators.
	 *
	 * @return Operator[]
	 */
	public function getAllowedFilterOperators(): array;

	/**
	 * Called to apply query builder filter.
	 */
	public function queryBuilderApplyFilter(ORMTableQuery $qb, Column $column, Operator $operator, array $args): void;

	/**
	 * Called to enhance query builder filter method when the operator is supported by the type.
	 *
	 * If the method have a body we add it in the class body otherwise we just add it in class comment as we have magic
	 * {@see Gobl\ORM\ORMTableQuery::__call()} method in the query builder.
	 */
	public function queryBuilderEnhanceFilterMethod(Table $table, Column $column, Operator $operator, PHPMethod $method): void;

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

	/**
	 * Returns a string hash of a clean column value for dirty-state comparison.
	 *
	 * The default implementation handles:
	 * - null          -> empty string
	 * - scalar        -> (string) cast
	 * - BackedEnum    -> (string) cast of the backing scalar value
	 * - everything else (array, JsonSerializable object, ...) -> json_encode
	 *
	 * Override only when the default serialization is insufficient for your type.
	 *
	 * @param TClean $v a clean value produced by this type's validate()
	 *
	 * @return string
	 */
	public function hash(mixed $v): string;
}
