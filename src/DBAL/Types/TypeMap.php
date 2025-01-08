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

use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Operator;
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\Utils\Map;
use Gobl\ORM\ORMTypeHint;
use Gobl\ORM\ORMUniversalType;
use JsonException;

/**
 * Class TypeMap.
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

		parent::__construct(new TypeString());
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
	 * @throws JsonException
	 */
	public function dbToPhp(mixed $value, RDBMSInterface $rdbms): ?Map
	{
		if (null !== $value) {
			$v = \json_decode($value, true, 512, \JSON_THROW_ON_ERROR);

			if (!\is_array($v)) {
				$v = [];
			}

			return new Map($v);
		}

		return null;
	}

	/**
	 * {@inheritDoc}
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

		return $operators;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getEmptyValueOfType(): ?Map
	{
		return $this->isNullable() ? null : new Map();
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
	 */
	public function getReadTypeHint(): ORMTypeHint
	{
		return ORMTypeHint::map();
	}

	/**
	 * {@inheritDoc}
	 */
	public function getWriteTypeHint(): ORMTypeHint
	{
		return ORMTypeHint::map()->addUniversalTypes(ORMUniversalType::ARRAY);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws JsonException
	 * @throws TypesInvalidValueException
	 */
	public function phpToDb(mixed $value, RDBMSInterface $rdbms): ?string
	{
		$value = $this->validate($value);

		if (null === $value) {
			return null;
		}

		return \json_encode($value->getData(), \JSON_THROW_ON_ERROR);
	}

	/**
	 * {@inheritDoc}
	 */
	public function validate(mixed $value): ?Map
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

		try {
			// this checks if we can serialize to JSON
			\json_encode($value, \JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			throw new TypesInvalidValueException($this->msg('unable_to_serialize_map_value'), $debug, $e);
		}

		return $this->ensureMap($value);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws TypesInvalidValueException
	 */
	public function getDefault(): ?Map
	{
		$default = parent::getDefault();

		if (null === $default) {
			return null;
		}

		return $this->ensureMap($default);
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
		$is_map = $value instanceof Map;
		if (!$is_map && !\is_array($value)) {
			throw new TypesInvalidValueException($this->msg('invalid_map_type'), $value);
		}

		if ($is_map) {
			return $value;
		}

		return new Map($value);
	}
}
