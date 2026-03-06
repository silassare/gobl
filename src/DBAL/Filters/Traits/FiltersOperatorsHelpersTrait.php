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

namespace Gobl\DBAL\Filters\Traits;

use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Operator;
use Gobl\DBAL\Queries\QBExpression;
use Gobl\DBAL\Queries\QBSelect;
use JsonSerializable;

/**
 * Trait FiltersOperatorsHelpersTrait.
 */
trait FiltersOperatorsHelpersTrait
{
	/**
	 * Adds an equals condition.
	 *
	 * When `$right` is `null`, automatically promotes to `IS NULL` instead of `= NULL`.
	 *
	 * @param string                                           $left
	 * @param null|bool|float|int|QBExpression|QBSelect|string $right
	 *
	 * @return $this
	 */
	public function eq(string $left, bool|float|int|QBExpression|QBSelect|string|null $right): static
	{
		if (null === $right) {
			return $this->add(Operator::IS_NULL, $left);
		}

		return $this->add(Operator::EQ, $left, $right);
	}

	/**
	 * Adds a not-equals condition.
	 *
	 * When `$right` is `null`, automatically promotes to `IS NOT NULL` instead of `<> NULL`.
	 *
	 * @param string                                           $left
	 * @param null|bool|float|int|QBExpression|QBSelect|string $right
	 *
	 * @return $this
	 */
	public function neq(string $left, bool|float|int|QBExpression|QBSelect|string|null $right): static
	{
		if (null === $right) {
			return $this->add(Operator::IS_NOT_NULL, $left);
		}

		return $this->add(Operator::NEQ, $left, $right);
	}

	/**
	 * Adds like condition.
	 *
	 * @param string $left
	 * @param string $right
	 *
	 * @return $this
	 */
	public function like(string $left, string $right): static
	{
		return $this->add(Operator::LIKE, $left, $right);
	}

	/**
	 * Adds not like condition.
	 *
	 * @param string $left
	 * @param string $right
	 *
	 * @return $this
	 */
	public function notLike(string $left, string $right): static
	{
		return $this->add(Operator::NOT_LIKE, $left, $right);
	}

	/**
	 * Adds less than condition.
	 *
	 * @param string                                 $left
	 * @param float|int|QBExpression|QBSelect|string $right
	 *
	 * @return $this
	 */
	public function lt(string $left, float|int|QBExpression|QBSelect|string $right): static
	{
		return $this->add(Operator::LT, $left, $right);
	}

	/**
	 * Adds less than or equal condition.
	 *
	 * @param string                                 $left
	 * @param float|int|QBExpression|QBSelect|string $right
	 *
	 * @return $this
	 */
	public function lte(string $left, float|int|QBExpression|QBSelect|string $right): static
	{
		return $this->add(Operator::LTE, $left, $right);
	}

	/**
	 * Adds greater than condition.
	 *
	 * @param string                                 $left
	 * @param float|int|QBExpression|QBSelect|string $right
	 *
	 * @return $this
	 */
	public function gt(string $left, float|int|QBExpression|QBSelect|string $right): static
	{
		return $this->add(Operator::GT, $left, $right);
	}

	/**
	 * Adds greater than or equal condition.
	 *
	 * @param string                                 $left
	 * @param float|int|QBExpression|QBSelect|string $right
	 *
	 * @return $this
	 */
	public function gte(string $left, float|int|QBExpression|QBSelect|string $right): static
	{
		return $this->add(Operator::GTE, $left, $right);
	}

	/**
	 * Adds IS NULL condition.
	 *
	 * @param string $left
	 *
	 * @return $this
	 */
	public function isNull(string $left): static
	{
		return $this->add(Operator::IS_NULL, $left);
	}

	/**
	 * Adds IS NOT NULL condition.
	 *
	 * @param string $left
	 *
	 * @return $this
	 */
	public function isNotNull(string $left): static
	{
		return $this->add(Operator::IS_NOT_NULL, $left);
	}

	/**
	 * Adds IN list condition.
	 *
	 * @param string                      $left
	 * @param array|QBExpression|QBSelect $right
	 *
	 * @return $this
	 *
	 * @throws DBALException
	 */
	public function in(string $left, array|QBExpression|QBSelect $right): static
	{
		if ('' === $left) {
			throw new DBALException('the left operand must be a non-empty string.');
		}

		if (empty($left)) {
			throw new DBALException('the right operand must not be empty.');
		}

		return $this->add(Operator::IN, $left, $right);
	}

	/**
	 * Adds NOT IN list condition.
	 *
	 * @param string                      $left
	 * @param array|QBExpression|QBSelect $right
	 *
	 * @return $this
	 *
	 * @throws DBALException
	 */
	public function notIn(string $left, array|QBExpression|QBSelect $right): static
	{
		if ('' === $left) {
			throw new DBALException('the left operand must be a non-empty string.');
		}

		if (empty($left)) {
			throw new DBALException('the right operand must not be empty.');
		}

		return $this->add(Operator::NOT_IN, $left, $right);
	}

	/**
	 * Adds IS TRUE condition.
	 *
	 * @param string $left
	 *
	 * @return $this
	 */
	public function isTrue(string $left): static
	{
		return $this->add(Operator::IS_TRUE, $left);
	}

	/**
	 * Adds IS FALSE condition.
	 *
	 * @param string $left
	 *
	 * @return $this
	 */
	public function isFalse(string $left): static
	{
		return $this->add(Operator::IS_FALSE, $left);
	}

	/**
	 * Adds a JSON containment condition.
	 *
	 * Checks whether the JSON column contains the given JSON fragment (whole-column containment).
	 *
	 * - MySQL:      emits `JSON_CONTAINS(col, value)`
	 * - PostgreSQL: emits `col @> value::jsonb`
	 * - SQLite:     throws {@see DBALRuntimeException} (unsupported)
	 *
	 * The column must have `native_json` enabled. Arrays and `\JsonSerializable` values
	 * are automatically JSON-encoded. Strings are assumed to already be valid JSON fragments
	 * (e.g. `'"admin"'`, `'["a","b"]'`, `'{"role":"admin"}'`).
	 *
	 * @param string                                       $left  Column or JSON expression (e.g. `t.data`)
	 * @param null|array|float|int|JsonSerializable|string $right Value to check containment of
	 *
	 * @return $this
	 */
	public function contains(string $left, array|float|int|JsonSerializable|string|null $right): static
	{
		return $this->add(Operator::CONTAINS, $left, $right);
	}

	/**
	 * Adds a JSON path containment condition.
	 *
	 * Checks whether the extracted sub-value at the given path contains the JSON value.
	 * Use `$left` in `table.column#path.segments` notation so the filter operand
	 * resolver builds the correct dialect-specific path expression.
	 *
	 * - MySQL:      emits `JSON_CONTAINS(JSON_EXTRACT(col, '$.path'), value)`
	 * - PostgreSQL: emits `col->'path' @> value::jsonb`
	 * - SQLite:     throws {@see DBALRuntimeException} (unsupported)
	 *
	 * @param string                                       $left_with_path Column+path in `table.column#path.to.key` notation (e.g. `accounts.data#user.tags`)
	 * @param null|array|float|int|JsonSerializable|string $value          Value to check containment of; arrays and \JsonSerializable are auto-serialized to JSON
	 *
	 * @return $this
	 */
	public function containsAtPath(string $left_with_path, array|float|int|JsonSerializable|string|null $value): static
	{
		if (null !== $value && !\is_string($value)) {
			$value = \json_encode($value, \JSON_THROW_ON_ERROR);
		}

		return $this->add(Operator::CONTAINS, $left_with_path, $value);
	}

	/**
	 * Adds a JSON top-level key existence condition.
	 *
	 * Checks whether the top-level key `$key` exists in the JSON column.
	 *
	 * - MySQL:      emits `JSON_CONTAINS_PATH(col, 'one', CONCAT('$.', key))`
	 * - PostgreSQL: emits `jsonb_exists(col, key)`
	 * - SQLite:     emits `json_extract(col, '$.'||key) IS NOT NULL`
	 *
	 * @param string $left The column or JSON expression (e.g. `t.data`)
	 * @param string $key  The top-level key name to check
	 *
	 * @return $this
	 */
	public function hasKey(string $left, string $key): static
	{
		return $this->add(Operator::HAS_KEY, $left, $key);
	}
}
