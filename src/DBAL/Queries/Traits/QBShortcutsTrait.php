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

namespace Gobl\DBAL\Queries\Traits;

use Gobl\DBAL\Queries\QBExpression;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\DBAL\Table;

/**
 * Trait QBShortcutsTrait.
 *
 * Provides shorthand factory methods for building raw SQL fragments and
 * sub-select queries inline within query builder chains.
 *
 * These helpers are the primary escape hatch from parameter binding, allowing
 * safe construction of column references, SQL function calls, boolean/null
 * literals, and correlated sub-queries without writing raw SQL strings by hand.
 */
trait QBShortcutsTrait
{
	/**
	 * Shorthand for `new QBExpression($expr)`.
	 *
	 * Use this to pass a raw SQL fragment as a value where the query builder
	 * would otherwise bind the string as a parameter.
	 *
	 * ```php
	 * // column-to-column equality in a filter
	 * $qb->filters()->eq('p.post_author_id', $qb->expr('u.user_id'));
	 *
	 * // raw SQL in a SET clause
	 * $qb->set('updated_at', $qb->expr('NOW()'));
	 * ```
	 *
	 * @param string $expr raw SQL fragment
	 *
	 * @return QBExpression
	 */
	public function expr(string $expr): QBExpression
	{
		return new QBExpression($expr);
	}

	/**
	 * Builds an injection-safe SQL function call expression.
	 *
	 * - `string` arguments are treated as **literal values** and are quoted
	 *   via {@see quote()} — safe for user-supplied data.
	 * - `QBExpression` arguments (created with {@see expr()} or {@see col()}) are
	 *   embedded **verbatim** as raw SQL fragments — use these for column
	 *   references, wildcards, or nested function calls.
	 *
	 * ```php
	 * $qb->fn('COUNT', $qb->expr('*'))                              // COUNT(*)
	 * $qb->fn('NOW')                                                 // NOW()
	 * $qb->fn('COALESCE', $qb->col('u', 'name'), $qb->col('u', 'email'))  // COALESCE(alias.col, alias.col)
	 * $qb->fn('DATE_FORMAT', $qb->col('o', 'date'), '%Y-%m')        // DATE_FORMAT(alias.col, '%Y-%m')  ← quoted
	 * $qb->fn('CONCAT', $qb->col('u', 'fname'), ' ', $qb->col('u', 'lname'))  // CONCAT(…, ' ', …)  ← quoted
	 * ```
	 *
	 * @param string              $function SQL function name (e.g. `'COUNT'`, `'COALESCE'`, `'NOW'`)
	 * @param string|QBExpression ...$args  `string` → quoted literal; `QBExpression` → raw SQL
	 *
	 * @return QBExpression
	 */
	public function fn(string $function, string|QBExpression ...$args): QBExpression
	{
		$parts = [];

		foreach ($args as $arg) {
			$parts[] = $arg instanceof QBExpression ? (string) $arg : $this->quote($arg);
		}

		return new QBExpression($function . '(' . \implode(', ', $parts) . ')');
	}

	/**
	 * Returns a fully-qualified `alias.column` expression.
	 *
	 * Resolves the table prefix automatically, so you can write join conditions
	 * and filters without manually constructing `"alias.prefixed_column_name"`.
	 *
	 * ```php
	 * // Join ON condition — no manual prefix needed:
	 * $qb->leftJoin('p')
	 *    ->to('users', 'u')
	 *    ->on($qb->filters()->eq('p.post_author_id', $qb->col('u', 'user_id')));
	 *
	 * // Filter comparing two columns:
	 * $qb->filters()->eq('a.account_client_id', $qb->col('c', 'id'));
	 * ```
	 *
	 * @param string|Table $table_or_alias table name or declared alias
	 * @param string       $column         column name (without prefix)
	 *
	 * @return QBExpression
	 */
	public function col(string|Table $table_or_alias, string $column): QBExpression
	{
		return new QBExpression($this->fullyQualifiedName($table_or_alias, $column));
	}

	/**
	 * Returns the SQL `NULL` literal as an expression.
	 *
	 * ```php
	 * $qb->set('deleted_at', $qb->sqlNull());  // SET deleted_at = NULL
	 * ```
	 *
	 * @return QBExpression
	 */
	public function sqlNull(): QBExpression
	{
		return new QBExpression('NULL');
	}

	/**
	 * Returns the SQL `TRUE` literal as an expression.
	 *
	 * ```php
	 * $qb->set('is_active', $qb->sqlTrue());  // SET is_active = TRUE
	 * ```
	 *
	 * @return QBExpression
	 */
	public function sqlTrue(): QBExpression
	{
		return new QBExpression('TRUE');
	}

	/**
	 * Returns the SQL `FALSE` literal as an expression.
	 *
	 * ```php
	 * $qb->set('is_active', $qb->sqlFalse());  // SET is_active = FALSE
	 * ```
	 *
	 * @return QBExpression
	 */
	public function sqlFalse(): QBExpression
	{
		return new QBExpression('FALSE');
	}

	/**
	 * Quotes a scalar value as a SQL string literal using the current RDBMS
	 * dialect's escaping rules.
	 *
	 * Unlike `PDO::quote()`, this method requires no live database connection
	 * and is safe to use during query building and schema generation.
	 *
	 * @param string $value raw string to quote
	 *
	 * @return string e.g. `'O''Brien'`
	 */
	public function quote(string $value): string
	{
		return $this->db->getGenerator()
			->quoteLiteral($value);
	}

	/**
	 * Creates a new sub-select query that shares the same database connection.
	 *
	 * Useful for correlated sub-queries inside `IN`, `NOT IN`, `EXISTS`, or
	 * as a derived table in `FROM`.
	 *
	 * ```php
	 * // NOT IN (SELECT …)
	 * $qb->filters()->notIn(
	 *     'u.user_id',
	 *     $qb->sub(fn($s) => $s->from('banned_users', 'b')->select('b', ['id']))
	 * );
	 *
	 * // Derived table in FROM:
	 * $qb->from(
	 *     $qb->sub(fn($s) => $s->from('orders')->select()->where(…)),
	 *     'sub'
	 * );
	 * ```
	 *
	 * When a callable is provided it receives the new `QBSelect` instance and
	 * must return it (or void — the instance is returned regardless).
	 *
	 * @param null|callable(QBSelect):mixed $factory optional factory to configure the sub-select inline
	 *
	 * @return QBSelect
	 */
	public function sub(?callable $factory = null): QBSelect
	{
		$sub = new QBSelect($this->db);

		if ($factory) {
			$factory($sub);
		}

		return $sub;
	}
}
