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

namespace Gobl\DBAL;

/**
 * Enum Operator.
 */
enum Operator: string
{
	case EQ = 'eq';

	case NEQ = 'neq';

	case LT = 'lt';

	case LTE = 'lte';

	case GT = 'gt';

	case GTE = 'gte';

	case LIKE = 'like';

	case NOT_LIKE = 'not_like';

	case IS_NULL = 'is_null';

	case IS_NOT_NULL = 'is_not_null';

	case IN = 'in';

	case NOT_IN = 'not_in';

	case IS_TRUE = 'is_true';

	case IS_FALSE = 'is_false';

	/**
	 * JSON whole-column or sub-path containment check.
	 *
	 * - Whole-column (no path): `JSON_CONTAINS(col, value)` / `col @> value::jsonb`
	 * - Sub-path (with path):   same but applied to `json_extract(col, '$.path')` / `col->'path'`
	 * - SQLite:                 not supported (throws)
	 */
	case CONTAINS = 'contains';

	/**
	 * JSON key existence check.
	 *
	 * Single-segment path (top-level key):
	 * - MySQL:      `JSON_CONTAINS_PATH(col, 'one', CONCAT('$.', key))`
	 * - PostgreSQL: `jsonb_exists(col, key)`
	 * - SQLite:     `json_extract(col, '$.'||key) IS NOT NULL`
	 *
	 * Multi-segment path (nested key, e.g. 'user.role'):
	 * - MySQL:      `json_extract(col, '$.user.role') IS NOT NULL`
	 * - PostgreSQL: `(col #> '{user,role}') IS NOT NULL`
	 * - SQLite:     `json_extract(col, '$.user.role') IS NOT NULL`
	 */
	case HAS_KEY = 'has_key';

	/**
	 * Gets operand filter suffix used in ORM.
	 *
	 * @param Column $column
	 *
	 * @return string
	 */
	public function getFilterSuffix(Column $column): string
	{
		$name = $column->getName();

		if (\str_starts_with($name, 'is_')) {
			$verb = \substr($name, 3);
			if (self::IS_TRUE === $this) {
				return 'is_' . $verb;
			}
			if (self::IS_FALSE === $this) {
				return 'is_not_' . $verb;
			}
		}
		if (\str_ends_with($name, 'ed')) {
			if (self::IS_TRUE === $this) {
				return 'is_' . $name;
			}
			if (self::IS_FALSE === $this) {
				return 'is_not_' . $name;
			}
		}

		return $name . '_' . match ($this) {
			self::EQ          => 'is',
			self::NEQ         => 'is_not',
			self::LT          => 'is_lt',
			self::LTE         => 'is_lte',
			self::GT          => 'is_gt',
			self::GTE         => 'is_gte',
			self::LIKE        => 'is_like',
			self::NOT_LIKE    => 'is_not_like',
			self::IS_NULL     => 'is_null',
			self::IS_NOT_NULL => 'is_not_null',
			self::IN          => 'is_in',
			self::NOT_IN      => 'is_not_in',
			self::IS_TRUE     => 'is_true',
			self::IS_FALSE    => 'is_false',
			self::CONTAINS    => 'contains',
			self::HAS_KEY     => 'has_key',
		};
	}

	/**
	 * Checks if this is an unary operator.
	 *
	 * @return bool
	 */
	public function isUnary(): bool
	{
		return match ($this) {
			self::IS_NULL, self::IS_NOT_NULL, self::IS_TRUE, self::IS_FALSE => true,
			default => false
		};
	}
}
