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
	 * @return bool|int|PDOStatement|string
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
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
	 * Checks if a parameter name is bound.
	 *
	 * @param string $name
	 *
	 * @return bool
	 */
	public function isBoundParam(string $name): bool;

	/**
	 * Merge binds parameters from another query builder.
	 *
	 * @return $this
	 */
	public function bindMergeFrom(self $qb): static;

	/**
	 * Sets the main alias for a given table.
	 *
	 * @param \Gobl\DBAL\Table|string $table
	 * @param string                  $alias
	 *
	 * @return $this
	 */
	public function setMainAlias(string|Table $table, string $alias): static;

	/**
	 * Resolve a table name or alias to a table instance.
	 *
	 * @param \Gobl\DBAL\Table|string $table_name_or_alias
	 *
	 * @return null|\Gobl\DBAL\Table
	 */
	public function resolveTable(string|Table $table_name_or_alias): ?Table;

	/**
	 * Returns the fully qualified name of a column.
	 *
	 * @param \Gobl\DBAL\Table|string $table_name_or_alias
	 * @param string                  $column
	 *
	 * @return string
	 */
	public function fullyQualifiedName(string|Table $table_name_or_alias, string $column): string;

	/**
	 * Returns the fully qualified name of columns in an array.
	 *
	 * If columns is empty, it returns the fully qualified name used to select all ie: users.*
	 *
	 * @param \Gobl\DBAL\Table|string $table_name_or_alias
	 * @param array                   $columns
	 *
	 * @return array
	 */
	public function fullyQualifiedNameArray(string|Table $table_name_or_alias, array $columns = []): array;

	/**
	 * Gets the main alias for a given table.
	 *
	 * @param \Gobl\DBAL\Table|string $table
	 * @param bool                    $declare
	 *
	 * @return string
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
	 * Adds table alias to query.
	 *
	 * @param \Gobl\DBAL\Table|string $table
	 * @param string                  $alias
	 *
	 * @return $this
	 */
	public function alias(string|Table $table, string $alias): static;
}
