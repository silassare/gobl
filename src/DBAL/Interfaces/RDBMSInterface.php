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

namespace Gobl\DBAL\Interfaces;

use Closure;
use Gobl\DBAL\Builders\NamespaceBuilder;
use Gobl\DBAL\DbConfig;
use Gobl\DBAL\Table;
use PDO;
use PDOStatement;

/**
 * Interface RDBMSInterface.
 */
interface RDBMSInterface
{
	/**
	 * Create instance.
	 */
	public static function new(DbConfig $config): static;

	/**
	 * Locks this db instance to prevent further changes.
	 */
	public function lock(): static;

	/**
	 * Gets PDO connection.
	 *
	 * @return PDO
	 */
	public function getConnection(): PDO;

	/**
	 * Gets db config.
	 *
	 * @return DbConfig
	 */
	public function getConfig(): DbConfig;

	/**
	 * Resolve reference column.
	 *
	 * @param string $reference          The reference column path
	 * @param string $used_in_table_name The table in which the reference is being used
	 */
	public function resolveColumn(string $reference, string $used_in_table_name): array;

	/**
	 * Adds table.
	 *
	 * @param \Gobl\DBAL\Table $table The table to add
	 *
	 * @return $this
	 */
	public function addTable(Table $table): static;

	/**
	 * Loads tables from a given schema definition.
	 *
	 * When a desired namespace is given, it will override any namespace defined in each table.
	 *
	 * @param array<string, array|Table> $schema            The schema definition
	 * @param null|string                $desired_namespace The desired namespace
	 *
	 * @return $this
	 */
	public function loadSchema(array $schema, string $desired_namespace = null): static;

	/**
	 * Returns db namespace builder for a given namespace.
	 *
	 * @param string $namespace
	 *
	 * @return \Gobl\DBAL\Builders\NamespaceBuilder
	 */
	public function namespace(string $namespace): NamespaceBuilder;

	/**
	 * Alias of {@see namespace()}.
	 *
	 * @param string $namespace
	 *
	 * @return \Gobl\DBAL\Builders\NamespaceBuilder
	 */
	public function ns(string $namespace): NamespaceBuilder;

	/**
	 * Checks if a given table is defined.
	 *
	 * @param string $name the table name or full name
	 *
	 * @return bool
	 */
	public function hasTable(string $name): bool;

	/**
	 * Asserts if a given table name is defined.
	 *
	 * @param string $name the table name or full name
	 */
	public function assertHasTable(string $name): void;

	/**
	 * Gets table with a given name.
	 *
	 * @param string $name the table name or table full name
	 *
	 * @return null|\Gobl\DBAL\Table
	 */
	public function getTable(string $name): ?Table;

	/**
	 * Gets table with a given name or fail.
	 *
	 * @param string $name the table name or table full name
	 *
	 * @return \Gobl\DBAL\Table
	 */
	public function getTableOrFail(string $name): Table;

	/**
	 * Gets tables.
	 *
	 * @param null|string $namespace
	 *
	 * @return array<string, \Gobl\DBAL\Table>
	 */
	public function getTables(?string $namespace = null): array;

	/**
	 * Returns the rdbms type.
	 *
	 * @return string
	 */
	public function getType(): string;

	/**
	 * Runs a given callable in a transaction and return the value returned by the callable.
	 *
	 * @param Closure $callable
	 *
	 * @return mixed
	 *
	 * @throws \Gobl\Exceptions\GoblException
	 */
	public function runInTransaction(Closure $callable): mixed;

	/**
	 * Begin a new transaction.
	 *
	 * @return bool
	 */
	public function beginTransaction(): bool;

	/**
	 * Commit current transaction.
	 *
	 * @return bool
	 */
	public function commit(): bool;

	/**
	 * Rollback current transaction.
	 *
	 * @return bool
	 */
	public function rollBack(): bool;

	/**
	 * Executes raw sql string.
	 *
	 * @param string     $sql                    the sql query string
	 * @param null|array $params                 Your sql params
	 * @param null|array $params_types           Your sql params type
	 * @param bool       $is_multi_queries       the sql string contains multiple query
	 * @param bool       $in_transaction         run the query in a transaction
	 * @param bool       $auto_close_transaction auto commit or rollback
	 *
	 * @return PDOStatement
	 */
	public function execute(
		string $sql,
		array $params = null,
		array $params_types = null,
		bool $is_multi_queries = false,
		bool $in_transaction = false,
		bool $auto_close_transaction = false
	): PDOStatement;

	/**
	 * Executes sql string with multiples query.
	 *
	 * Suitable for sql file content.
	 *
	 * @param string $sql the sql query string
	 *
	 * @return PDOStatement
	 */
	public function executeMulti(string $sql): PDOStatement;

	/**
	 * Executes select queries.
	 *
	 * @param string     $sql          Your sql select query
	 * @param null|array $params       Your sql select params
	 * @param array      $params_types Your sql params types
	 *
	 * @return PDOStatement
	 */
	public function select(string $sql, array $params = null, array $params_types = []): PDOStatement;

	/**
	 * Executes delete queries.
	 *
	 * @param string     $sql          Your sql select query
	 * @param null|array $params       Your sql select params
	 * @param array      $params_types Your sql params types
	 *
	 * @return int Affected row count
	 */
	public function delete(string $sql, array $params = null, array $params_types = []): int;

	/**
	 * Executes insert queries.
	 *
	 * @param string     $sql          Your sql select query
	 * @param null|array $params       Your sql select params
	 * @param array      $params_types Your sql params types
	 *
	 * @return false|string The last insert id
	 */
	public function insert(string $sql, array $params = null, array $params_types = []): string|false;

	/**
	 * Executes update queries.
	 *
	 * @param string     $sql          Your sql select query
	 * @param null|array $params       Your sql select params
	 * @param array      $params_types Your sql params types
	 *
	 * @return int Affected row count
	 */
	public function update(string $sql, array $params = null, array $params_types = []): int;

	/**
	 * Gets this rdbms query generator.
	 *
	 * @return \Gobl\DBAL\Interfaces\QueryGeneratorInterface
	 */
	public function getGenerator(): QueryGeneratorInterface;
}
