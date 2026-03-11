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

namespace Gobl\DBAL\Queries\Traits;

use Gobl\DBAL\Builders\JoinBuilder;
use Gobl\DBAL\Queries\JoinType;
use Gobl\DBAL\Table;

/**
 * Trait QBJoinsTrait.
 *
 * ## Join model
 *
 * Joins are keyed by the **host alias** - the alias of the left-hand (FROM) table
 * that owns the join chain.
 *
 * Storage: `$options_joins[host_alias][] = JoinBuilder`
 *
 * The SQL generator iterates all FROM aliases. For each alias it:
 *  1. Emits `table AS alias`
 *  2. Calls `getJoinQueryFor($alias)` - appends all joins anchored there
 *  3. Recurses into each joined alias, enabling arbitrarily deep chains
 *
 * ## Correct API
 *
 * ```php
 * // Single join: clients (c) -> accounts (a)
 * $qb->from('clients', 'c');
 * $qb->innerJoin('c')             // 'c' is the host - must already be declared via from()
 *    ->to('accounts', 'a')         // 'a' is the new table being joined
 *    ->on($filters);               // ON condition
 *
 * // Chained join: c -> a -> transactions (t)
 * $qb->innerJoin('a')             // 'a' was registered by the previous ->to() call
 *    ->to('transactions', 't')
 *    ->on($filters);
 *
 * // Multiple independent FROM roots each with their own join chain:
 * $qb->from('orders', 'o');
 * $qb->from('users', 'u');
 * $qb->innerJoin('o')->to('order_items', 'oi')->on($f1);
 * $qb->leftJoin('u')->to('profiles', 'p')->on($f2);
 * // SQL: ... FROM gObL_orders AS o INNER JOIN ... , gObL_users AS u LEFT JOIN ...
 * ```
 *
 * ## Common mistake
 *
 * ```php
 * // WRONG: passes target table name instead of host alias.
 * // Stored under 'a' (the target alias), never found when iterating FROM 'c'.
 * $qb->innerJoin('accounts', 'a')->on($filters);
 *
 * // CORRECT
 * $qb->innerJoin('c')->to('accounts', 'a')->on($filters);
 * ```
 */
trait QBJoinsTrait
{
	/**
	 * Joins anchored by the host (left-hand) table alias.
	 *
	 * Structure: `[ host_alias => JoinBuilder[] ]`
	 *
	 * Each `JoinBuilder` describes one JOIN emanating from that host.
	 * The SQL generator iterates FROM aliases and calls `getJoinQueryFor(alias)`
	 * which looks up this map and then recurses into each joined alias,
	 * allowing arbitrarily deep join chains from a single FROM root.
	 *
	 * @var array<string, JoinBuilder[]>
	 */
	protected array $options_joins = [];

	/**
	 * @return array<string, JoinBuilder[]>
	 */
	public function getOptionsJoins(): array
	{
		return $this->options_joins;
	}

	/**
	 * Inner join.
	 *
	 * Selects records that have matching values in both tables.
	 *
	 * @param string|Table $table
	 * @param null|string  $alias
	 *
	 * @return JoinBuilder
	 */
	public function innerJoin(
		string|Table $table,
		?string $alias = null,
	): JoinBuilder {
		return $this->join(JoinType::INNER, $table, $alias);
	}

	/**
	 * Left join.
	 *
	 * Selects all records from the left table, and the matched records from the right table.
	 *
	 * @param string|Table $table
	 * @param null|string  $alias
	 *
	 * @return JoinBuilder
	 */
	public function leftJoin(
		string|Table $table,
		?string $alias = null,
	): JoinBuilder {
		return $this->join(JoinType::LEFT, $table, $alias);
	}

	/**
	 * Right join.
	 *
	 * Selects all records from the right table, and the matched records from the left table.
	 *
	 * @param string|Table $table
	 * @param null|string  $alias
	 *
	 * @return JoinBuilder
	 */
	public function rightJoin(
		string|Table $table,
		?string $alias = null,
	): JoinBuilder {
		return $this->join(JoinType::RIGHT, $table, $alias);
	}

	/**
	 * Creates a join builder anchored at the given host table.
	 *
	 * `$table` must be an **already-declared alias** (registered via `from()` or a prior
	 * `->to()` call) or a `Table` instance.  The returned `JoinBuilder` is stored under
	 * that host alias so the SQL generator can locate it when emitting the FROM clause.
	 *
	 * Always complete the builder with `->to(target, alias)->on(condition)`:
	 *
	 * ```php
	 * $qb->innerJoin('c')           // host alias 'c' already in FROM
	 *    ->to('accounts', 'a')      // target table + alias
	 *    ->on($qb->filters()->eq('a.account_client_id', $qb->expr('c.client_id')));
	 * ```
	 *
	 * @param JoinType     $type  JOIN type (INNER, LEFT, RIGHT)
	 * @param string|Table $table host alias (already declared) or Table instance
	 * @param null|string  $alias explicit alias override (rarely needed; omit when $table is an alias)
	 *
	 * @return JoinBuilder call ->to() then ->on() on the returned builder
	 */
	public function join(
		JoinType $type,
		string|Table $table,
		?string $alias = null,
	): JoinBuilder {
		if ($table instanceof Table) {
			$resolved_table_name = $table->getFullName();
		} else {
			$resolved_table_name = $this->getAliasTable($table);

			// argument is an alias
			if ($resolved_table_name) {
				$alias ??= $table;
			} else {
				$resolved_table_name = $this->resolveTable($table)
					?->getFullName() ?? $table;
			}
		}

		if ($alias) {
			// this will throw an exception if the alias already exists
			// and is not for the same table
			$this->alias($resolved_table_name, $alias);
		} else {
			$alias = $this->getMainAlias($resolved_table_name);
		}

		$jb                            = new JoinBuilder($type, $this, $resolved_table_name, $alias);
		$this->options_joins[$alias][] = $jb;

		return $jb;
	}
}
