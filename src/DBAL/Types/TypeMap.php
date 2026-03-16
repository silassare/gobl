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
use Gobl\DBAL\Types\Utils\JsonPatch;
use Gobl\DBAL\Types\Utils\Map;
use Gobl\ORM\ORMTableQuery;
use Gobl\ORM\ORMTypeHint;
use Gobl\ORM\ORMUniversalType;
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

		$base = new TypeJSON();
		$base->jsonOf(ORMUniversalType::MAP); // enforce JSON object semantics on the base type for schema reflection

		parent::__construct($base);
	}

	#[Override]
	public static function getInstance(array $options): static
	{
		return (new self())->configure($options);
	}

	/**
	 * {@inheritDoc}
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
	 * Enable native JSON column type in supporting RDBMS (MySQL >= 5.7, PostgreSQL).
	 *
	 * @param bool $native_json
	 *
	 * @return $this
	 */
	public function nativeJson(bool $native_json = true): static
	{
		/** @var TypeJSON $bt */
		$bt = $this->base_type;

		$bt->nativeJson($native_json);

		return $this->setOption('native_json', $native_json);
	}

	/**
	 * Sets whether the map is big (can hold large data).
	 */
	public function big(bool $big = true): static
	{
		/** @var TypeJSON $bt */
		$bt = $this->base_type;

		$bt->big($big);

		return $this->setOption('big', $big);
	}

	#[Override]
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
	 * Delegates to the base type (TypeJSON) so that JSON path operators
	 * (EQ, NEQ, LIKE, NOT_LIKE with a path string as first arg, plus
	 * CONTAINS and HAS_KEY including their path-aware variants) are handled
	 * correctly by TypeJSON's path-aware implementation.
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
		return ORMTypeHint::map();
	}

	/**
	 * {@inheritDoc}
	 *
	 * Accepts {@see Map}, array, or {@see JsonPatch}.
	 * A {@see JsonPatch} instance is coerced to a {@see Map} inside {@see validate()}.
	 */
	#[Override]
	public function getWriteTypeHint(): ORMTypeHint
	{
		return ORMTypeHint::map()
			->setPHPType(
				new PHPType('\\' . Map::class, 'array', '\\' . JsonPatch::class)
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
