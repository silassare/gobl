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
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\Interfaces\ValidationSubjectInterface;
use Gobl\DBAL\Types\Utils\JsonOfInterface;
use Gobl\DBAL\Types\Utils\JsonPatch;
use Gobl\ORM\ORM;
use Gobl\ORM\ORMTableQuery;
use Gobl\ORM\ORMTypeHint;
use Gobl\ORM\ORMUniversalType;
use InvalidArgumentException;
use JsonSerializable;
use OLIUP\CG\PHPMethod;
use OLIUP\CG\PHPType;
use Override;

/**
 * Class TypeJson.
 *
 * @extends BaseType<mixed, mixed>
 *
 * A JSON-capable base type that falls back to TEXT when native JSON is disabled or
 * the RDBMS does not support it.  TypeMap and TypeList use this as their base type
 * so that schema builders can opt-in to native JSON columns per-driver.
 *
 * When `json_of` is not set, any JSON-serialisable PHP value is accepted (including
 * scalars, arrays, and objects). `phpToDb()` runs `json_encode()` and `dbToPhp()`
 * runs `json_decode()`, so scalars are stored as their JSON representation and
 * decoded back to their PHP equivalent on read.
 *
 * ## json_of option
 *
 * When `json_of` is a FQCN implementing {@see JsonOfInterface}, TypeJson:
 *  - accepts instances of that class directly in `validate()` (they are JSON-encoded);
 *  - accepts plain arrays and passes them through `ClassName::revive($decoded)` to
 *    produce the typed object before re-encoding;
 *  - revives the stored JSON on `dbToPhp()` by calling `ClassName::revive($decoded)`
 *    so the getter returns a typed object;
 *  - emits the correct PHP type hint in generated ORM classes.
 *
 * When `json_of` is an {@see ORMUniversalType} enum case, no revival is performed --
 * `dbToPhp()` still decodes the JSON value. The universal type is used as a code-generation
 * hint (TypeScript / Dart generators emit typed annotations) and also enforces the root
 * document shape during validation: {@see ORMUniversalType::LIST} accepts only sequential
 * arrays; {@see ORMUniversalType::MAP} accepts only associative arrays or objects.
 *
 * Usage as a standalone column type:
 * ```php
 * $t->column('data', (new TypeJson())->nativeJson());
 * $t->column('meta', (new TypeJson())->jsonOf(MyMeta::class));
 * ```
 *
 * Usage via the builder shorthand (when added to TableBuilder):
 * ```php
 * $t->json('data')->nativeJson();
 * $t->json('meta')->jsonOf(MyMeta::class);
 * ```
 *
 * TypeMap/TypeList automatically use TypeJson as their base; to enable the native
 * JSON column type for those columns:
 * ```php
 * $t->map('meta')->nativeJson();
 * ```
 */
class TypeJson extends BaseType
{
	public const NAME = 'json';

	/**
	 * TypeJson constructor.
	 *
	 * @param null|string $message custom error message
	 */
	public function __construct(?string $message = null)
	{
		!empty($message) && $this->msg('invalid_json_type', $message);

		parent::__construct($this);
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

	/**
	 * Binds a revival class or a universal type hint to this JSON column.
	 *
	 * When a FQCN implementing {@see JsonOfInterface} is given:
	 *  - On read, the stored JSON is decoded and passed to `ClassName::revive($decoded)`
	 *    so the ORM getter returns a typed object.
	 *  - On write, instances of the class are JSON-encoded directly; plain arrays are
	 *    first revived then encoded.
	 *
	 * When an {@see ORMUniversalType} is given, it is used only as a code-generation
	 * hint (TypeScript / Dart type annotations). No revival is performed at runtime;
	 * `dbToPhp()` decodes the JSON value and returns it as-is.
	 *
	 * @param class-string<JsonOfInterface>|ORMUniversalType $class revival class or universal type hint
	 *
	 * @return static
	 */
	public function jsonOf(ORMUniversalType|string $class): static
	{
		if (\is_string($class)) {
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

		return $this->setOption('json_of', $class->value);
	}

	/**
	 * Returns the raw `json_of` option value: a class-string, an {@see ORMUniversalType}
	 * enum value string, or null if not set.
	 *
	 * Use {@see getJsonOfClass()} when you need only the revival class-string.
	 *
	 * @return null|string
	 */
	public function getJsonOf(): ?string
	{
		return $this->getOption('json_of');
	}

	/**
	 * Returns the revival class set via {@see jsonOf()}, or null if none (or if it is a universal type).
	 *
	 * @return null|class-string<JsonOfInterface>
	 */
	public function getJsonOfClass(): ?string
	{
		$v = $this->getOption('json_of');

		if (\is_string($v) && \is_a($v, JsonOfInterface::class, true)) {
			/** @var class-string<JsonOfInterface> $v */
			return $v;
		}

		return null;
	}

	/**
	 * Returns the universal type hint set via `jsonOf()`, or null if none (or if it is a revival class).
	 *
	 * @return null|ORMUniversalType
	 */
	public function getJsonOfUniversalType(): ?ORMUniversalType
	{
		$v = $this->getOption('json_of');

		if (\is_string($v) && !\is_a($v, JsonOfInterface::class, true)) {
			return ORMUniversalType::tryFrom($v);
		}

		return null;
	}

	/**
	 * Enable native JSON column type in supporting RDBMS (MySQL >= 5.7, PostgreSQL).
	 *
	 * When disabled (the default) the column is stored as TEXT, which is compatible
	 * with every RDBMS and maintains backward-compatibility with existing schemas.
	 *
	 * @param bool $native_json
	 *
	 * @return static
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
	 * @return static
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

	#[Override]
	public function configure(array $options): static
	{
		if (isset($options['json_of'])) {
			$raw = (string) $options['json_of'];
			$of  = ORMUniversalType::tryFrom(\strtoupper($raw));

			if ($of) {
				$this->jsonOf($of);
			} elseif (\is_a($raw, JsonOfInterface::class, true)) {
				$this->jsonOf($raw);
			}
		}

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
	 * Decodes the stored JSON string:
	 *  - When `json_of` is a revival class, decodes then calls `ClassName::revive($decoded)`
	 *    so the getter returns a typed object.
	 *  - Otherwise, decodes and returns the PHP value directly: JSON objects/arrays become
	 *    PHP arrays; JSON scalars (numbers, booleans, strings) become their PHP equivalents.
	 */
	#[Override]
	public function dbToPhp(mixed $value, RDBMSInterface $rdbms): mixed
	{
		if (null === $value) {
			return null;
		}

		$str           = (string) $value;
		$json_of_class = $this->getJsonOfClass();

		$decoded = \json_decode($str, true);

		if (null !== $json_of_class) {
			return $json_of_class::revive($decoded);
		}

		return $decoded;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Validates the value first then JSON-encodes it for DB storage.
	 *
	 * @throws TypesInvalidValueException
	 */
	#[Override]
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
	#[Override]
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

	#[Override]
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
	#[Override]
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

	#[Override]
	public function getEmptyValueOfType(): mixed
	{
		return $this->isNullable() ? null : 'null';
	}

	/**
	 * {@inheritDoc}
	 *
	 * - When `json_of` is a revival class, returns a PHP type hint for that class.
	 * - When `json_of` is an {@see ORMUniversalType}, returns a hint for that universal type.
	 * - Otherwise returns an UNKNOWN hint (decoded value shape is not statically known).
	 */
	#[Override]
	public function getReadTypeHint(): ORMTypeHint
	{
		$json_of_class = $this->getJsonOfClass();

		if (null !== $json_of_class) {
			$hint = ORMTypeHint::unknown();
			$hint->setPHPType(new PHPType('\\' . $json_of_class));

			return $hint;
		}

		$of = $this->getJsonOfUniversalType();

		if (null !== $of) {
			return new ORMTypeHint($of);
		}

		return ORMTypeHint::unknown();
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getWriteTypeHint(): ORMTypeHint
	{
		return $this->getReadTypeHint();
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
	 * When `json_of` is not set, accepts any JSON-serialisable PHP value (scalars,
	 * arrays, objects). A {@see JsonPatch} instance is coerced to its underlying array.
	 * When `json_of` is a revival class: plain arrays are revived via
	 * `ClassName::revive($value)`; instances of the class are accepted directly;
	 * all other values are rejected.
	 * When `json_of` is an {@see ORMUniversalType}, its {@see ORMUniversalType::isValidValue()}
	 * method enforces the expected shape -- e.g. LIST requires a sequential array,
	 * MAP requires an associative array or {@see Map} instance.
	 *
	 * @throws TypesInvalidValueException
	 */
	#[Override]
	protected function runValidation(ValidationSubjectInterface $subject): void
	{
		$value = $subject->getUnsafeValue();

		if ($value instanceof JsonPatch) {
			$value = $value->toArray();
		}

		if (null === $value) {
			$def = $this->getDefault();

			if (null !== $def) {
				$value = $def;
			} elseif ($this->isNullable()) {
				$subject->accept(null);

				return;
			} else {
				$subject->reject($this->msg('invalid_json_type'), ['value' => $value]);

				return;
			}
		}

		$json_of_class = $this->getJsonOfClass();

		// When json_of is a revival class: accept instances directly, revive plain arrays.
		if (null !== $json_of_class) {
			if (\is_array($value)) {
				$subject->accept($json_of_class::revive($value));

				return;
			}

			if (!$value instanceof $json_of_class) {
				$subject->reject($this->msg('invalid_json_type'), ['value' => $value]);

				return;
			}

			$subject->accept($value);

			return;
		}

		$of_u_type = $this->getJsonOfUniversalType();

		// ORMUniversalType-based shape enforcement when json_of is a universal type.
		if (null !== $of_u_type && !$of_u_type->isValidValue($value)) {
			$subject->reject($this->msg('invalid_json_of_type'), [
				'value'         => $value,
				'expected_type' => $of_u_type->value,
			]);

			return;
		}

		$subject->accept($value);
	}
}
