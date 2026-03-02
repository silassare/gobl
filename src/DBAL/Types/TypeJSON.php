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
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\Interfaces\BaseTypeInterface;
use Gobl\ORM\ORMTypeHint;
use Gobl\ORM\ORMUniversalType;

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
	 * Enable native JSON column type in supporting RDBMS (MySQL ≥ 5.7, PostgreSQL).
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
	 * Big JSON — hints to the schema generator that the column may hold larger data.
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
	 * Long JSON — hints to the schema generator that the column may hold very large data.
	 *
	 * When native_json is disabled this maps to LONGTEXT in MySQL.
	 *
	 * @param bool $long
	 *
	 * @return $this
	 */
	public function long(bool $long = true): static
	{
		return $this->setOption('long', $long);
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
}
