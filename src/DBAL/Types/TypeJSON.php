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

use Gobl\DBAL\Column;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Operator;
use Gobl\DBAL\Table;
use Gobl\DBAL\Types\Exceptions\TypesException;
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\Interfaces\BaseTypeInterface;
use Gobl\DBAL\Types\Interfaces\ValidationSubjectInterface;
use Gobl\DBAL\Types\Utils\JsonOfInterface;
use Gobl\DBAL\Types\Utils\JsonPatch;
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
 * ## Why scalar values are rejected
 *
 * TypeJSON models a **JSON document** (object or array root), not an arbitrary JSON
 * *value* (scalar). Although `"hello"`, `42`, and `true` are valid JSON in every
 * RDBMS, storing a scalar via TypeJSON would produce a raw string like `'"hello"'`
 * (with the extra JSON quotes) in `dbToPhp()` return value, which is confusing -
 * use TypeString, TypeInt, or TypeBool for scalar columns instead.
 *
 * ## json_of option
 *
 * When `json_of` is set to a FQCN implementing {@see JsonOfInterface}, TypeJSON:
 *  - accepts instances of that class directly in `validate()` (they are JSON-encoded);
 *  - accepts plain arrays and passes them through `ClassName::revive($decoded)` to
 *    produce the typed object before re-encoding;
 *  - revives the stored JSON on `dbToPhp()` by calling `ClassName::revive($decoded)`
 *    so the getter returns a typed object instead of a raw string;
 *  - emits the correct PHP type hint in generated ORM classes.
 *
 * Usage as a standalone column type:
 * ```php
 * $t->column('data', (new TypeJSON())->nativeJson());
 * $t->column('meta', (new TypeJSON())->jsonOf(MyMeta::class));
 * ```
 *
 * Usage via the builder shorthand (when added to TableBuilder):
 * ```php
 * $t->json('data')->nativeJson();
 * $t->json('meta')->jsonOf(MyMeta::class);
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

	public static function getInstance(array $options): static
	{
		return (new self())->configure($options);
	}

	public function getName(): string
	{
		return self::NAME;
	}

	/**
	 * Binds a revival class to this JSON column.
	 *
	 * The class must implement {@see JsonOfInterface}.
	 * On read, the stored JSON is decoded and passed to `ClassName::revive($decoded)`
	 * so the ORM getter returns a typed object.
	 * On write, instances of the class are JSON-encoded directly; plain arrays are
	 * first revived then encoded.
	 *
	 * @param class-string<JsonOfInterface> $class
	 *
	 * @return $this
	 */
	public function jsonOf(string $class): static
	{
		if (!\is_a($class, JsonOfInterface::class, true)) {
			throw new InvalidArgumentException(
				\sprintf(
					'json_of class "%s" must implement %s.',
					$class,
					JsonOfInterface::class
				)
			);
		}

		return $this->setOption('json_of', $class);
	}

	/**
	 * Returns the revival class name set via {@see jsonOf()}, or null if none.
	 *
	 * @return null|class-string<JsonOfInterface>
	 */
	public function getJsonOf(): ?string
	{
		/** @var null|class-string<JsonOfInterface> $v */
		return $this->getOption('json_of');
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

	public function configure(array $options): static
	{
		if (isset($options['json_of'])) {
			$this->jsonOf((string) $options['json_of']);
		}

		if (isset($options['native_json'])) {
			$this->nativeJson((bool) $options['native_json']);
		}

		if (isset($options['big'])) {
			$this->big((bool) $options['big']);
		}

		if (isset($options['json_data_type'])) {
			$this->jsonDataType((string) $options['json_data_type']);
		}

		return parent::configure($options);
	}

	/**
	 * Restricts the kind of JSON document stored in this column.
	 *
	 * Accepted values:
	 *  - `'any'`    -- any JSON object or array (default)
	 *  - `'array'`  -- only a JSON array (sequential PHP array)
	 *  - `'object'` -- only a JSON object (associative PHP array or object)
	 *
	 * This option has no effect when {@see jsonOf()} is also set (the revival class
	 * defines its own structure contract).
	 *
	 * @param string $type one of 'any', 'array', 'object'
	 *
	 * @return $this
	 *
	 * @throws TypesException when an unknown type value is supplied
	 */
	public function jsonDataType(string $type): static
	{
		if (!\in_array($type, ['any', 'array', 'object'], true)) {
			throw new TypesException(\sprintf(
				'json_data_type must be "any", "array", or "object", got "%s".',
				$type
			));
		}

		return $this->setOption('json_data_type', $type);
	}

	/**
	 * Returns the configured json_data_type, defaulting to `'any'`.
	 */
	public function getJsonDataType(): string
	{
		return $this->getOption('json_data_type', 'any');
	}

	/**
	 * {@inheritDoc}
	 *
	 * When `json_of` is set, decodes the stored JSON and revives it via the configured class.
	 * Otherwise returns the raw JSON string.
	 */
	public function dbToPhp(mixed $value, RDBMSInterface $rdbms): mixed
	{
		if (null === $value) {
			return null;
		}

		$json_of = $this->getJsonOf();

		if (null !== $json_of) {
			$decoded = \json_decode((string) $value, true);

			return $json_of::revive($decoded);
		}

		return (string) $value;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Validates the value first then JSON-encodes it for DB storage.
	 *
	 * @throws TypesInvalidValueException
	 */
	public function phpToDb(mixed $value, RDBMSInterface $rdbms): ?string
	{
		$value = $this->validate($value)->getCleanValue();

		if (null === $value) {
			return null;
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

	public function getEmptyValueOfType(): mixed
	{
		return $this->isNullable() ? null : 'null';
	}

	/**
	 * {@inheritDoc}
	 *
	 * When `json_of` is set, returns a PHP-typed hint for the revival class.
	 * Otherwise returns a string hint (raw JSON text).
	 */
	public function getReadTypeHint(): ORMTypeHint
	{
		$json_of = $this->getJsonOf();

		if (null !== $json_of) {
			$hint = ORMTypeHint::unknown();
			$hint->setPHPType(new PHPType('\\' . $json_of));

			return $hint;
		}

		return ORMTypeHint::string();
	}

	/**
	 * {@inheritDoc}
	 *
	 * When `json_of` is set, accepts instances of the revival class or plain arrays.
	 * Otherwise accepts any JSON-serializable value (UNKNOWN|LIST|MAP).
	 */
	public function getWriteTypeHint(): ORMTypeHint
	{
		$json_of = $this->getJsonOf();

		if (null !== $json_of) {
			$hint = ORMTypeHint::unknown();
			$hint->setPHPType(new PHPType('\\' . $json_of, 'array'));

			return $hint;
		}

		return ORMTypeHint::unknown()->addUniversalTypes(ORMUniversalType::LIST, ORMUniversalType::MAP);
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

	/**
	 * {@inheritDoc}
	 *
	 * Accepts array, object, {@see JsonPatch}, or (when `json_of` is set) instances of
	 * the configured revival class. Scalar values are always rejected -- use TypeString,
	 * TypeInt, or TypeBool for scalar columns.
	 * When used as base type for TypeMap/TypeList, validation is handled by the wrapper type.
	 * A {@see JsonPatch} instance is coerced to its underlying array before being returned.
	 * When `json_of` is set and the value is an array, it is revived via `ClassName::revive($value)`
	 * so the returned value is always the typed object.
	 *
	 * @throws TypesInvalidValueException
	 */
	protected function runValidation(ValidationSubjectInterface $subject): void
	{
		$value = $subject->getUnsafeValue();

		if ($value instanceof JsonPatch) {
			$value = $value->toArray();
		}

		$json_of = $this->getJsonOf();

		if (null === $value) {
			$def = $this->getDefault();

			if (null !== $def) {
				$value = $def;
			} elseif ($this->isNullable()) {
				$subject->accept(null);

				return;
			}
		}

		// When json_of is set: accept instances directly, revive plain arrays.
		if (null !== $json_of) {
			if (\is_array($value)) {
				$subject->accept($json_of::revive($value));

				return;
			}

			if (!$value instanceof $json_of) {
				$subject->reject($this->msg('invalid_json_type'), ['value' => $value]);

				return;
			}

			$subject->accept($value);

			return;
		}

		// Without json_of: only arrays and objects are accepted.
		if (!\is_array($value) && !\is_object($value)) {
			$subject->reject($this->msg('invalid_json_type'), ['value' => $value]);

			return;
		}

		// json_data_type enforcement (only when json_of is not set)
		$json_data_type = $this->getJsonDataType();

		if ('array' === $json_data_type) {
			if (!\is_array($value) || !\array_is_list($value)) {
				$subject->reject($this->msg('invalid_json_array_type'), ['value' => $value]);

				return;
			}
		} elseif ('object' === $json_data_type) {
			$is_assoc_array = \is_array($value) && !\array_is_list($value);
			$is_obj         = \is_object($value);

			if (!$is_assoc_array && !$is_obj) {
				$subject->reject($this->msg('invalid_json_object_type'), ['value' => $value]);

				return;
			}
		}

		$subject->accept($value);
	}
}
