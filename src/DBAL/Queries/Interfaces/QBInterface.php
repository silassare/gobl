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
use PDO;
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
	 * ```
	 * Returned type
	 *   int: affected rows count (for DELETE, UPDATE)
	 *   string: last insert id (for INSERT)
	 *   PDOStatement: the statement (for SELECT ...)
	 *```
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 *
	 * @return bool|int|PDOStatement|string
	 */
	public function execute(): bool|int|string|PDOStatement;

	/**
	 * Returns query string to be executed by the rdbms.
	 *
	 * @return string
	 */
	public function getSqlQuery(): string;

	/**
	 * Alias for {@see \PDO::quote()}.
	 *
	 * @param int   $type
	 * @param mixed $value
	 *
	 * @return string
	 */
	public function quote(mixed $value, int $type = PDO::PARAM_STR): string;

	/**
	 * Returns the RDBMS.
	 *
	 * @return \Gobl\DBAL\Interfaces\RDBMSInterface
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
	 * @param array $list                The items to bind
	 * @param array $types               The params types
	 * @param bool  $return_placeholders Should we return placeholders or params name
	 *
	 * @return array the list of all named params used
	 */
	public function bindArrayForInList(array $list, array $types = [], bool $return_placeholders = false): array;

	/**
	 * Merge binds parameters from another query builder.
	 *
	 * @return $this
	 */
	public function bindMergeFrom(self $qb): static;

	/**
	 * Try to get a given table full name.
	 *
	 * @param string $table_name_or_alias the table name or alias
	 *
	 * @return null|string
	 */
	public function resolveTableFullName(string $table_name_or_alias): ?string;

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
	 * Automatically prefix column(s) in a given table.
	 *
	 * ```php
	 * $qb = new QBSelect($db);
	 * $qb->alias([
	 *    'u' => 'user',
	 *    'c' => 'command'
	 * ]);
	 *
	 * $qb->prefixColumnsString('u', 'name'); // u.col_prefix_name
	 * $qb->prefixColumnsString('c', 'id', 'title'); // c.col_prefix_id, c.col_prefix_title
	 * $qb->prefixColumnsString('user', 'phone'); // tbl_prefix_user.col_prefix_phone
	 *
	 * ```
	 *
	 * @param string   $table   the table to use
	 * @param string[] $columns the columns to auto prefix
	 *
	 * @return string
	 */
	public function prefixColumnsString(string $table, ...$columns): string;

	/**
	 * Automatically prefix column(s) in a given table.
	 *
	 * The table should be defined.
	 * The table could be an alias that was declared
	 *
	 * ```php
	 * $qb = new QBSelect($db);
	 * $qb->alias([
	 *    'u' => 'user',
	 *    'c' => 'command'
	 * ]);
	 *
	 * $qb->prefixColumnsArray('u', ['name'], true); // ['u.col_prefix_name']
	 * $qb->prefixColumnsArray('c', ['id', 'title'], false); // ['col_prefix_id', 'col_prefix_title']
	 * $qb->prefixColumnsArray('user', ['phone'], true); // ['tbl_prefix_user.col_prefix_phone']
	 *
	 * ```
	 *
	 * @param string $table    the table to use
	 * @param array  $columns  the column to auto prefix
	 * @param bool   $absolute
	 *
	 * @return array
	 */
	public function prefixColumnsArray(string $table, array $columns, bool $absolute = false): array;

	/**
	 * Adds table(s) alias(es) to query.
	 *
	 * @param array $aliases_to_tables_map aliases map
	 *
	 * @return $this
	 */
	public function alias(array $aliases_to_tables_map): static;
}
