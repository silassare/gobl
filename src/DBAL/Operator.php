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
	 * Case- and accent-insensitive text search, whatever the column stores.
	 *
	 * Takes a term and never a pattern: the compiler wraps it in `%...%` and escapes `%`, `_` and
	 * the escape character itself, so a term holding one of them is not a wildcard by accident.
	 *
	 * What folds depends on the engine, and the asymmetry is declared rather than discovered:
	 * - MySQL:      `CONVERT(expr USING <charset>) COLLATE <collate> LIKE ?` -- case and accents
	 * - PostgreSQL: `lower(expr::text) LIKE lower(?)` -- case only, accents need the unaccent extension
	 * - SQLite:     `lower(expr) LIKE lower(?)` -- ASCII case only
	 *
	 * This is a full table scan on every engine, as `LIKE '%term%'` cannot use a B-tree index
	 * anywhere. It is the right answer into the tens of thousands of rows and is not a substitute
	 * for a search index beyond that.
	 *
	 * On a JSON column a path compares the extracted value; no path compares the whole document
	 * text, which also carries the document's keys.
	 */
	case MATCHES = 'matches';

	/**
	 * Case- and accent-insensitive search for a term the text begins with.
	 *
	 * {@see self::MATCHES} in every respect but the pattern, which is anchored at the start:
	 * the term is a term, the escaping is the compiler's, and what folds depends on the engine.
	 *
	 * This is the one text search an index can serve without anything declared, since a B-tree
	 * reads a prefix -- but only on a column whose stored text is already folded, which is what
	 * `searchable(SearchMatch::PREFIX)` maintains. Without it this is a full scan too.
	 */
	case STARTS_WITH = 'starts_with';

	/**
	 * Gets operand filter suffix used in ORM.
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
			self::MATCHES     => 'matches',
			self::STARTS_WITH => 'starts_with',
		};
	}

	/**
	 * Checks if this is an unary operator.
	 */
	public function isUnary(): bool
	{
		return match ($this) {
			self::IS_NULL, self::IS_NOT_NULL, self::IS_TRUE, self::IS_FALSE => true,
			default                                                         => false
		};
	}
}
