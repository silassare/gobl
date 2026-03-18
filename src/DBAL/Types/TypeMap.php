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
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\Interfaces\ValidationSubjectInterface;
use Gobl\DBAL\Types\Utils\JsonOfInterface;
use Gobl\DBAL\Types\Utils\JsonPatch;
use Gobl\DBAL\Types\Utils\Map;
use Gobl\ORM\ORMTableQuery;
use Gobl\ORM\ORMTypeHint;
use Gobl\ORM\ORMUniversalType;
use InvalidArgumentException;
use JsonException;
use OLIUP\CG\PHPType;
use Override;

/**
 * Class TypeMap.
 *
 * @extends Type<mixed, null|Map>
 */
class TypeMap extends Type
{
	public const NAME = 'map';

	/**
	 * TypeMap constructor.
	 *
	 * @param null|string $message
	 */
	public function __construct(?string $message = null)
	{
		!empty($message) && $this->msg('invalid_map_type', $message);

		$base = new TypeJson();
		$base->jsonOf(ORMUniversalType::MAP); // enforce JSON object semantics on the base type for schema reflection

		parent::__construct($base);
	}

	#[Override]
	public static function getInstance(array $options): static
	{
		return (new static())->configure($options);
	}

	/**
	 * {@inheritDoc}
	 *
	 * When `map_of` is a {@see JsonOfInterface} class, each map value is revived.
	 *
	 * @throws JsonException
	 */
	#[Override]
	public function dbToPhp(mixed $value, RDBMSInterface $rdbms): ?Map
	{
		if (null === $value || '' === $value) {
			return null;
		}

		$v = \json_decode($value, true, 512, \JSON_THROW_ON_ERROR);

		if (!\is_array($v)) {
			$v = [];
		}

		$class = $this->getMapOfClass();

		if (null !== $class) {
			$v = \array_map(static fn (mixed $item) => $class::revive($item), $v);
		}

		return new Map($v);
	}

	#[Override]
	public function getEmptyValueOfType(): ?Map
	{
		return $this->isNullable() ? null : new Map();
	}

	#[Override]
	public function getName(): string
	{
		return self::NAME;
	}

	/**
	 * Enable or disable the native JSON column type in supporting RDBMS (MySQL >= 5.7, PostgreSQL).
	 *
	 * Native JSON is the default. Pass `false` to opt out and store as TEXT.
	 *
	 * @param bool $native_json
	 *
	 * @return static
	 */
	public function nativeJson(bool $native_json = true): static
	{
		/** @var TypeJson $bt */
		$bt = $this->base_type;

		$bt->nativeJson($native_json);

		return $this->setOption('native_json', $native_json);
	}

	/**
	 * Sets whether the map is big (can hold large data).
	 */
	public function big(bool $big = true): static
	{
		/** @var TypeJson $bt */
		$bt = $this->base_type;

		$bt->big($big);

		return $this->setOption('big', $big);
	}

	/**
	 * Sets the value type of the map for code generation type hints and runtime revival.
	 *
	 * Accepts either:
	 *  - a scalar {@see ORMUniversalType} case (e.g. STRING, INT, BIGINT, FLOAT, BOOL) for
	 *    hint-only metadata (no runtime coercion);
	 *  - a FQCN implementing {@see JsonOfInterface} for typed revival: each value of the
	 *    stored JSON object is passed through `ClassName::revive($value)` on read, and
	 *    instances are JSON-encoded on write.
	 *
	 * Defaults to no type constraint when not set.
	 *
	 * @param class-string<JsonOfInterface>|ORMUniversalType $of Value type or revival class
	 *
	 * @return static
	 */
	public function mapOf(ORMUniversalType|string $of): static
	{
		if (\is_string($of)) {
			if (!\is_a($of, JsonOfInterface::class, true)) {
				throw new InvalidArgumentException(
					\sprintf(
						'map_of class "%s" must implement %s.',
						$of,
						JsonOfInterface::class
					)
				);
			}

			return $this->setOption('map_of', $of);
		}

		return $this->setOption('map_of', $of->value);
	}

	/**
	 * Returns the revival class name set via `map_of`, or null if none (or if it is a universal type).
	 *
	 * @return null|class-string<JsonOfInterface>
	 */
	public function getMapOfClass(): ?string
	{
		$v = $this->getOption('map_of');

		if (\is_string($v) && \is_a($v, JsonOfInterface::class, true)) {
			/** @var class-string<JsonOfInterface> $v */
			return $v;
		}

		return null;
	}

	/**
	 * Returns the universal type hint set via `mapOf()`, or null if none (or if it is a revival class).
	 *
	 * @return null|ORMUniversalType
	 */
	public function getMapOfUniversalType(): ?ORMUniversalType
	{
		$v = $this->getOption('map_of');

		if (\is_string($v) && !\is_a($v, JsonOfInterface::class, true)) {
			return ORMUniversalType::tryFrom($v);
		}

		return null;
	}

	#[Override]
	public function configure(array $options): static
	{
		if (isset($options['map_of'])) {
			$raw = (string) $options['map_of'];
			// Try as a universal type first (case-insensitive), then as a class name.
			$of = ORMUniversalType::tryFrom(\strtoupper($raw));

			if ($of) {
				$this->mapOf($of);
			} elseif (\is_a($raw, JsonOfInterface::class, true)) {
				$this->mapOf($raw);
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
	 * Delegates to the base type (TypeJson) so that JSON path operators
	 * (EQ, NEQ, LIKE, NOT_LIKE with a path string as first arg, plus
	 * CONTAINS and HAS_KEY including their path-aware variants) are handled
	 * correctly by TypeJson's path-aware implementation.
	 */
	#[Override]
	public function queryBuilderApplyFilter(ORMTableQuery $qb, Column $column, Operator $operator, array $args): void
	{
		$this->safelyCallOnBaseType(__FUNCTION__, [$qb, $column, $operator, $args]);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getReadTypeHint(): ORMTypeHint
	{
		$class = $this->getMapOfClass();

		if (null !== $class) {
			return ORMTypeHint::map()->setMapOfClass($class);
		}

		$of = $this->getMapOfUniversalType();

		return ORMTypeHint::map($of)->setPHPType(new PHPType('\\' . Map::class, 'array<string,' . ($of && ORMUniversalType::UNKNOWN !== $of ? $of->toPHPType() : 'mixed') . '>', '\\' . JsonPatch::class));
	}

	/**
	 * {@inheritDoc}
	 *
	 * Accepts {@see Map}, array, or {@see JsonPatch}.
	 * A {@see JsonPatch} instance is coerced to a {@see Map} inside {@see validate()}.
	 * When `map_of` is set with a revival class, values in the array must be instances of
	 * that class or plain arrays that can be passed through `ClassName::revive()`.
	 */
	#[Override]
	public function getWriteTypeHint(): ORMTypeHint
	{
		$class = $this->getMapOfClass();

		if (null !== $class) {
			return ORMTypeHint::map()->setMapOfClass($class)
				->setPHPType(
					new PHPType('\\' . Map::class, 'array<string,\\' . $class . '>', '\\' . JsonPatch::class)
				);
		}

		$of = $this->getMapOfUniversalType();

		return ORMTypeHint::map($of)
			->setPHPType(
				new PHPType('\\' . Map::class, 'array<string,' . ($of && ORMUniversalType::UNKNOWN !== $of ? $of->toPHPType() : 'mixed') . '>', '\\' . JsonPatch::class)
			);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws JsonException
	 * @throws TypesInvalidValueException
	 */
	#[Override]
	public function phpToDb(mixed $value, RDBMSInterface $rdbms): ?string
	{
		$value = $this->validate($value)->getCleanValue();

		if (null === $value) {
			return null;
		}

		return \json_encode($value, \JSON_THROW_ON_ERROR);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws TypesInvalidValueException
	 */
	#[Override]
	public function getDefault(): ?Map
	{
		$default = parent::getDefault();

		if (null === $default) {
			return null;
		}

		return $this->ensureMap($default);
	}

	/**
	 * {@inheritDoc}
	 *
	 * When `map_of` is a {@see JsonOfInterface} class, each map value that is an array
	 * is revived via `ClassName::revive($value)` before JSON-encoding.
	 *
	 * @throws TypesInvalidValueException
	 */
	#[Override]
	protected function runValidation(ValidationSubjectInterface $subject): void
	{
		$value = $subject->getUnsafeValue();

		if ($value instanceof JsonPatch) {
			$value = $value->toMap();
		}

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
				$subject->reject($this->msg('invalid_map_type'), $debug);

				return;
			}
		}

		$map_class = $this->getMapOfClass();

		if (null !== $map_class && ($value instanceof Map || \is_array($value))) {
			$raw = $value instanceof Map ? $value->getData() : $value;

			try {
				$revived = [];

				foreach ($raw as $key => $item) {
					if (\is_array($item)) {
						$revived[(string) $key] = $map_class::revive($item);

						continue;
					}

					if ($item instanceof $map_class) {
						$revived[(string) $key] = $item;

						continue;
					}

					throw new TypesInvalidValueException('Invalid map value type.', [
						'value_item'    => $item,
						'key'           => $key,
						'expected_type' => $map_class,
					] + $debug);
				}

				$value = $revived;
			} catch (TypesInvalidValueException $e) {
				$subject->reject($e);

				return;
			}
		}

		$of_u_type = $this->getMapOfUniversalType();

		if (null !== $of_u_type && ($value instanceof Map || \is_array($value))) {
			$raw = $value instanceof Map ? $value->getData() : $value;

			foreach ($raw as $key => $item) {
				if (!$of_u_type->isValidValue($item)) {
					$subject->reject($this->msg('invalid_map_of_type'), $debug + [
						'value_item'    => $item,
						'key'           => $key,
						'expected_type' => $of_u_type->value,
					]);

					return;
				}
			}
		}

		try {
			// this checks if we can serialize to JSON
			$_ = \json_encode($value, \JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			$subject->reject(new TypesInvalidValueException($this->msg('unable_to_serialize_map_value'), $debug, $e));

			return;
		}

		try {
			$subject->accept($this->ensureMap($value));
		} catch (TypesInvalidValueException $e) {
			$subject->reject($e);
		}
	}

	/**
	 * Ensure the value is a Map.
	 *
	 * @param mixed $value
	 *
	 * @return Map
	 *
	 * @throws TypesInvalidValueException
	 */
	private function ensureMap(mixed $value): Map
	{
		if ($value instanceof Map) {
			return $value;
		}

		if (!\is_array($value)) {
			throw new TypesInvalidValueException($this->msg('invalid_map_type'), [
				'value' => $value,
			]);
		}

		return new Map($value);
	}
}
