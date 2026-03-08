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

namespace Gobl\DBAL\Queries\Interfaces;

use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Queries\QBType;
use Gobl\DBAL\Table;
use PDOStatement;

/**
 * Interface QBInterface.
 */
interface QBInterface
{
	/**
	 * Gets query type.
	 *
	 * @return QBType
	 */
	public function getType(): QBType;

	/**
	 * Executes the query.
	 *
	 * The return type depends on the query type:
	 *
	 * | Query type   | Return value                                               |
	 * |--------------|------------------------------------------------------------|
	 * | `SELECT`     | `PDOStatement` - open result statement, ready to iterate   |
	 * | `INSERT`     | `string` - last insert ID as returned by the RDBMS         |
	 * | `UPDATE`     | `int` - number of affected rows                            |
	 * | `DELETE`     | `int` - number of affected rows                            |
	 * | DDL / other  | `bool` - `true` on success                                 |
	 *
	 * @return bool|int|PDOStatement|string
	 */
	public function execute(): bool|int|PDOStatement|string;

	/**
	 * Returns query string to be executed by the rdbms.
	 *
	 * @return string
	 */
	public function getSqlQuery(): string;

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
	public function quote(string $value): string;

	/**
	 * Returns the RDBMS.
	 *
	 * @return RDBMSInterface
	 */
	public function getRDBMS(): RDBMSInterface;

	/**
	 * Returns bound values.
	 *
	 * @return array
	 */
	public function getBoundValues(): array;

	/**
	 * Returns bound values types.
	 *
	 * @return array
	 */
	public function getBoundValuesTypes(): array;

	/**
	 * Resets bounds parameters.
	 */
	public function resetParameters(): void;

	/**
	 * Binds parameters array to query.
	 *
	 * @param array $params The params to bind
	 * @param array $types  The params types
	 *
	 * @return $this
	 */
	public function bindArray(array $params, array $types = []): static;

	/**
	 * Binds named parameter.
	 *
	 * @param string   $name  The param name to bind
	 * @param mixed    $value The param value
	 * @param null|int $type  Any \PDO::PARAM_* constants
	 *
	 * @return $this
	 */
	public function bindNamed(string $name, mixed $value, ?int $type = null): static;

	/**
	 * Binds positional parameter.
	 *
	 * @param int      $offset The param offset
	 * @param mixed    $value  The param value
	 * @param null|int $type   Any \PDO::PARAM_* constants
	 *
	 * @return $this
	 */
	public function bindPositional(int $offset, mixed $value, ?int $type = null): static;

	/**
	 * Binds items to be used for IN and NOT_IN condition.
	 *
	 * Each item in `$list` is bound to a unique named parameter.
	 * Duplicate values are deduplicated before binding.
	 *
	 * @param array $list                the values to bind (duplicates are removed)
	 * @param array $types               PDO type per list index (`\PDO::PARAM_*`)
	 * @param bool  $return_placeholders When `true`, returns SQL placeholders (e.g. [':p_0', ':p_1']).
	 *                                   When `false` (default), returns raw parameter names (e.g. ['p_0', 'p_1']).
	 *                                   Use `true` when embedding the list directly into a raw SQL expression.
	 *
	 * @return array list of parameter names or SQL placeholders
	 */
	public function bindArrayForInList(array $list, array $types = [], bool $return_placeholders = false): array;

	/**
	 * Checks if a parameter name is bound.
	 *
	 * @param string $name
	 *
	 * @return bool
	 */
	public function isBoundParam(string $name): bool;

	/**
	 * Merges bound parameters from another query builder into this one.
	 *
	 * Useful when composing queries that reference subqueries or derived tables:
	 * the sub-query's bound values are pulled into the outer query so that
	 * `PDO::execute()` receives all parameters in one flat array.
	 *
	 * Merging a query builder into itself is a no-op.
	 *
	 * @param self $qb the source query builder whose parameters are merged in
	 *
	 * @return $this
	 */
	public function bindMergeFrom(self $qb): static;

	/**
	 * Sets the main alias for a given table.
	 *
	 * The main alias is what {@see fullyQualifiedName()} and
	 * {@see fullyQualifiedNameArray()} use to prefix column references when the
	 * table is referenced by its name rather than directly by an alias.
	 *
	 * **Precondition:** `$alias` must have been previously registered for this
	 * table via {@see alias()} (or implicitly through `from()`).
	 * Calling this with an undeclared alias, or an alias already registered for
	 * a different table, throws a {@see DBALRuntimeException}.
	 *
	 * @param string|Table $table the target table (name, full name, or `Table` instance)
	 * @param string       $alias an alias that was already declared for this table
	 *
	 * @return $this
	 */
	public function setMainAlias(string|Table $table, string $alias): static;

	/**
	 * Resolves a table name, alias, or `Table` instance to the corresponding registered `Table` object.
	 *
	 * Resolution order:
	 *  1. `Table` instance - returned as-is (no database lookup performed).
	 *  2. String looked up as a table name / full name in the current database.
	 *  3. String treated as a declared alias: the mapped table name is looked up
	 *     in the database.
	 *
	 * Returns `null` when none of the above steps produce a match.
	 *
	 * @param string|Table $table_name_or_alias table name, declared alias, or `Table` instance
	 *
	 * @return null|Table the resolved `Table`, or `null` if not found
	 */
	public function resolveTable(string|Table $table_name_or_alias): ?Table;

	/**
	 * Returns the fully qualified name of a column.
	 *
	 * @param string|Table $table_name_or_alias
	 * @param string       $column
	 *
	 * @return string
	 */
	public function fullyQualifiedName(string|Table $table_name_or_alias, string $column): string;

	/**
	 * Returns the fully qualified names of the given columns for a table.
	 *
	 * The prefix is resolved in this priority order:
	 *  1. If `$table_name_or_alias` is itself a declared alias, it is used verbatim.
	 *  2. Otherwise the table's main alias (set via {@see setMainAlias()}) is used.
	 *  3. If no main alias exists, the table full name is used as the prefix.
	 *
	 * When `$columns` is empty the wildcard selector is returned,
	 * e.g. `['u.*']` (or `['gobl_users.*']` when no alias is active).
	 *
	 * @param string|Table $table_name_or_alias table name, declared alias, or `Table` instance
	 * @param array        $columns             column names to qualify; empty means wildcard
	 *
	 * @return array fully qualified column references, e.g. `['u.user_id', 'u.user_name']`
	 */
	public function fullyQualifiedNameArray(string|Table $table_name_or_alias, array $columns = []): array;

	/**
	 * Gets the main alias for a given table.
	 *
	 * The main alias is the one registered via {@see alias()} with `$main = true`,
	 * or explicitly set via {@see setMainAlias()}, or implicitly assigned by `from()`.
	 *
	 * @param string|Table $table   table name, declared alias, or `Table` instance
	 * @param bool         $declare when `true`, a new alias is auto-generated and
	 *                              registered as the main alias when none exists,
	 *                              instead of throwing a runtime exception
	 *
	 * @return string the main alias
	 */
	public function getMainAlias(string|Table $table, bool $declare = false): string;

	/**
	 * Check if a given string is a declared alias.
	 *
	 * @param string $str
	 *
	 * @return bool
	 */
	public function isDeclaredAlias(string $str): bool;

	/**
	 * Gets table name for a given alias.
	 *
	 * @param string $alias
	 *
	 * @return null|string
	 */
	public function getAliasTable(string $alias): ?string;

	/**
	 * Declares an alias for a table in this query.
	 *
	 * Registers the alias in the query's alias map so that it can be used in
	 * FROM, JOIN, and WHERE clauses.  Passing the same alias for the same table
	 * a second time is a no-op; passing it for a *different* table throws.
	 *
	 * @param string|Table $table table name, full name, or `Table` instance
	 * @param string       $alias alias string (must match {@see Table::ALIAS_PATTERN})
	 * @param bool         $main  when `true`, also marks this alias as the main alias
	 *                            for the table (equivalent to calling {@see setMainAlias()} afterwards)
	 *
	 * @return $this
	 */
	public function alias(string|Table $table, string $alias, bool $main = false): static;
}
