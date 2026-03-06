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

use Gobl\DBAL\Column;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Operator;
use Gobl\DBAL\Table;
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\Interfaces\BaseTypeInterface;
use Gobl\ORM\ORMTableQuery;
use Gobl\ORM\ORMTypeHint;
use Gobl\ORM\ORMUniversalType;
use InvalidArgumentException;
use JsonSerializable;
use OLIUP\CG\PHPMethod;
use OLIUP\CG\PHPType;

/**
 * Class TypeJSON.
 *
 * A JSON-capable base type that falls back to TEXT when native JSON is disabled or
 * the RDBMS does not support it.  TypeMap and TypeList use this as their base type
 * so that schema builders can opt-in to native JSON columns per-driver.
 *
 * Usage as a standalone column type:
 * ```php
 * $t->column('data', (new TypeJSON())->nativeJson());
 * ```
 *
 * Usage via the builder shorthand (when added to TableBuilder):
 * ```php
 * $t->json('data')->nativeJson();
 * ```
 *
 * TypeMap/TypeList automatically use TypeJSON as their base; to enable the native
 * JSON column type for those columns:
 * ```php
 * $t->map('meta')->nativeJson();
 * ```
 */
class TypeJSON extends Type implements BaseTypeInterface
{
	public const NAME = 'json';

	/**
	 * TypeJSON constructor.
	 *
	 * @param null|string $message custom error message
	 */
	public function __construct(?string $message = null)
	{
		!empty($message) && $this->msg('invalid_json_type', $message);

		parent::__construct($this);
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
	 */
	public function getName(): string
	{
		return self::NAME;
	}

	/**
	 * Enable native JSON column type in supporting RDBMS (MySQL >= 5.7, PostgreSQL).
	 *
	 * When disabled (the default) the column is stored as TEXT, which is compatible
	 * with every RDBMS and maintains backward-compatibility with existing schemas.
	 *
	 * @param bool $native_json
	 *
	 * @return $this
	 */
	public function nativeJson(bool $native_json = true): static
	{
		return $this->setOption('native_json', $native_json);
	}

	/**
	 * Big JSON hints to the schema generator that the column may hold larger data.
	 *
	 * When native_json is disabled this maps to MEDIUMTEXT in MySQL.
	 *
	 * @param bool $big
	 *
	 * @return $this
	 */
	public function big(bool $big = true): static
	{
		return $this->setOption('big', $big);
	}

	/**
	 * Returns whether the column is configured to use the native JSON type (when supported by the RDBMS).
	 */
	public function isNativeJson(): bool
	{
		return $this->getOption('native_json', false);
	}

	/**
	 * {@inheritDoc}
	 */
	public function configure(array $options): static
	{
		if (isset($options['native_json'])) {
			$this->nativeJson((bool) $options['native_json']);
		}

		if (isset($options['big'])) {
			$this->big((bool) $options['big']);
		}

		return parent::configure($options);
	}

	/**
	 * {@inheritDoc}
	 *
	 * Only accepts array, object, or null. Scalar values (string, int, float, bool) are rejected.
	 * When used as base type for TypeMap/TypeList, validation is handled by the wrapper type.
	 *
	 * @throws TypesInvalidValueException
	 */
	public function validate(mixed $value): ?string
	{
		if (null === $value) {
			$def = $this->getDefault();

			if (null !== $def) {
				$value = $def;
			} elseif ($this->isNullable()) {
				return null;
			}
		}

		// Only arrays and objects are accepted.
		if (!\is_array($value) && !\is_object($value)) {
			throw new TypesInvalidValueException($this->msg('invalid_json_type'), ['value' => $value]);
		}

		$encoded = \json_encode($value);
		if (false === $encoded) {
			throw new TypesInvalidValueException($this->msg('invalid_json_type'), [
				'value'      => $value,
				'json_error' => \json_last_error_msg(),
			]);
		}

		return $encoded;
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
	 */
	public function phpToDb(mixed $value, RDBMSInterface $rdbms): ?string
	{
		return $this->validate($value);
	}

	/**
	 * {@inheritDoc}
	 *
	 * Operators depend on whether the column is native JSON or TEXT-stored JSON.
	 *
	 * TEXT-stored JSON (native_json=false):
	 *   Only string-comparison operators apply to the raw JSON string value.
	 *   No JSON-aware functions are available, so containment operators are excluded.
	 *
	 * Native JSON (native_json=true):
	 *   All operators run through path-based JSON extraction (via `queryBuilderApplyFilter`)
	 *   except `CONTAINS` and `HAS_KEY`, which operate on the whole column without a path.
	 */
	public function getAllowedFilterOperators(): array
	{
		$operators = [
			Operator::EQ,
			Operator::NEQ,
			Operator::LIKE,
			Operator::NOT_LIKE,
		];

		if ($this->isNullable()) {
			$operators[] = Operator::IS_NULL;
			$operators[] = Operator::IS_NOT_NULL;
		}

		if ($this->isNativeJson()) {
			// Whole-column containment + key existence (single- and multi-segment)
			$operators[] = Operator::CONTAINS;
			$operators[] = Operator::HAS_KEY;
		}

		return $operators;
	}

	/**
	 * {@inheritDoc}
	 */
	public function queryBuilderApplyFilter(ORMTableQuery $qb, Column $column, Operator $operator, array $args): void
	{
		// TEXT-stored JSON: standard filter on the raw string column
		if (!$this->isNativeJson()) {
			$value = $operator->isUnary() ? null : ($args[0] ?? null);
			$qb->filterBy($column->getFullName(), $operator, $value);

			return;
		}

		if (Operator::CONTAINS === $operator) {
			$value   =  $args[0] ?? null;
			$at_path = isset($args[1]) && \is_string($args[1]) && '' !== $args[1] ? $args[1] : null;

			$qb->filterBy($column->getFullName(), Operator::CONTAINS, $value, $at_path);

			return;
		}

		if (Operator::HAS_KEY === $operator) {
			$path_str = $args[0] ?? null;

			if (!\is_string($path_str) || '' === $path_str) {
				throw new InvalidArgumentException(
					\sprintf(
						'Expected a non-empty path string as first argument for operator "%s" on JSON column "%s".',
						$operator->value,
						$column->getFullName()
					)
				);
			}

			// here path is treated as value but Operator::HAS_KEY is well-known by the driver
			// to generate the appropriate key-existence expression
			$qb->filterBy($column->getFullName(), Operator::HAS_KEY, $path_str);

			return;
		}

		// All other native JSON operators:
		// - Unary (IS_NULL, IS_NOT_NULL): $args[0] = dot-notation path, no value
		// - Non-unary (EQ, NEQ, LIKE, NOT_LIKE): $args[0] = value, $args[1] = dot-notation path
		if ($operator->isUnary()) {
			$json_path = $args[0] ?? null;
			$value     = null;
		} else {
			$value     = $args[0] ?? null;
			$json_path = $args[1] ?? null;
		}

		if (!\is_string($json_path) || '' === $json_path) {
			throw new InvalidArgumentException(
				\sprintf(
					'Expected a non-empty string path as %s argument for operator "%s" on JSON column "%s".',
					$operator->isUnary() ? 'first' : 'second',
					$operator->value,
					$column->getFullName()
				)
			);
		}

		$qb->filterBy($column->getFullName(), $operator, $value, $json_path);
	}

	/**
	 * {@inheritDoc}
	 *
	 * Enhancement rules for native JSON filter methods:
	 *
	 * - `CONTAINS`: `$value` (JSON-serializable) + optional `$path` (dot-notation, default null).
	 *   When `$path` is given the containment check is applied to the extracted sub-value.
	 * - `HAS_KEY`: single `$path` (dot-notation path). A single segment checks the top-level
	 *   key; multiple segments check whether the nested key resolves to a non-null value.
	 * - All other native JSON operators: for unary (IS_NULL/IS_NOT_NULL) only `$path`;
	 *   for non-unary (EQ/NEQ/LIKE/NOT_LIKE) `$value` first then `$path` (mirrors CONTAINS convention).
	 *
	 * For TEXT-stored JSON, no enhancement is performed.
	 */
	public function queryBuilderEnhanceFilterMethod(Table $table, Column $column, Operator $operator, PHPMethod $method): void
	{
		if (!$this->isNativeJson()) {
			return;
		}

		$col_ref = '`' . $table->getName() . '.' . $column->getName() . '`';

		$comment = match ($operator) {
			Operator::CONTAINS    => "Filters rows where {$col_ref} contains JSON fragment \$value. Optional \$path (dot-notation) applies the check to the extracted sub-value (e.g. 'user.tags' checks within `\$.user.tags`). Not supported on SQLite.",
			Operator::HAS_KEY     => "Filters rows where {$col_ref} has the JSON key at \$path. A single segment (e.g. 'tag') checks the top-level key; multiple segments (e.g. 'user.role') check existence of the nested key.",
			Operator::IS_NULL     => "Filters rows where {$col_ref} at dot-notation \$path is null.",
			Operator::IS_NOT_NULL => "Filters rows where {$col_ref} at dot-notation \$path is not null.",
			Operator::EQ          => "Filters rows where \$value equals the extracted value of {$col_ref} at dot-notation \$path.",
			Operator::NEQ         => "Filters rows where \$value does not equal the extracted value of {$col_ref} at dot-notation \$path.",
			Operator::LIKE        => "Filters rows where \$value matches the extracted value of {$col_ref} at dot-notation \$path using LIKE.",
			Operator::NOT_LIKE    => "Filters rows where \$value does not match the extracted value of {$col_ref} at dot-notation \$path using LIKE.",
			default               => null,
		};

		if (null !== $comment) {
			$method->setComment($comment);
		}

		if (Operator::CONTAINS === $operator) {
			$method->newArgument('value')
				->setType(new PHPType('null', 'int', 'float', 'string', 'array', '\JsonSerializable'));
			$method->newArgument('path')
				->setType(new PHPType('null', 'string'))
				->setValue(null);

			return;
		}

		if (Operator::HAS_KEY === $operator) {
			// $path: single segment = top-level key; multi-segment = nested key existence
			$method->newArgument('path')->setType('string');

			return;
		}

		// All other native JSON operators:
		// - Unary (IS_NULL, IS_NOT_NULL): only $path argument
		// - Non-unary (EQ, NEQ, LIKE, NOT_LIKE): $value first, then $path (mirrors CONTAINS convention)
		if (!$operator->isUnary()) {
			$value_type = match ($operator) {
				Operator::LIKE, Operator::NOT_LIKE => new PHPType('string'),
				default                            => new PHPType('string', 'int', 'float', 'null'),
			};
			$method->newArgument('value')->setType($value_type);
		}

		$method->newArgument('path')->setType('string');
	}

	/**
	 * {@inheritDoc}
	 */
	public function getEmptyValueOfType(): mixed
	{
		return $this->isNullable() ? null : 'null';
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
	public function getWriteTypeHint(): ORMTypeHint
	{
		return ORMTypeHint::mixed()->addUniversalTypes(ORMUniversalType::ARRAY, ORMUniversalType::MAP);
	}

	/**
	 * Serializes a CONTAINS filter value to a JSON-encoded string.
	 *
	 * This is used to convert the right-hand operand of a CONTAINS filter to the appropriate
	 * JSON-encoded string representation.
	 * - `null` and `string` are passed through as-is (string is assumed pre-encoded).
	 * - `int`|`float`|`bool` are JSON-encoded to a string (e.g. 42 -> '42', 3.14 -> '3.14', true -> 'true', false -> 'false')
	 * - `array`|`\JsonSerializable` are JSON-encoded (e.g. ['a', 'b'] -> '["a","b"]', ['role => 'admin'] -> '{"role":"admin"}')
	 *
	 * @param null|array|bool|float|int|JsonSerializable|string $value
	 *
	 * @return null|string
	 */
	public static function serializeJsonValue(array|bool|float|int|JsonSerializable|string|null $value): ?string
	{
		if (null === $value || \is_string($value)) {
			return $value;
		}

		return \json_encode($value, \JSON_THROW_ON_ERROR);
	}
}
