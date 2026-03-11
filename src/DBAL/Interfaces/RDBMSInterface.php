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

namespace Gobl\DBAL\Interfaces;

use Closure;
use Gobl\DBAL\Builders\NamespaceBuilder;
use Gobl\DBAL\DbConfig;
use Gobl\DBAL\Table;
use Gobl\Exceptions\GoblException;
use PDO;
use PDOStatement;
use PHPUtils\Interfaces\LockInterface;

/**
 * Interface RDBMSInterface.
 */
interface RDBMSInterface extends LockInterface
{
	/**
	 * Create instance.
	 */
	public static function new(DbConfig $config): static;

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
	 * @param Table $table The table to add
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
	public function loadSchema(array $schema, ?string $desired_namespace = null): static;

	/**
	 * Exports the registered tables as a plain array compatible with {@see loadSchema()}.
	 *
	 * @param null|string $namespace when provided, only exports tables in that namespace
	 *
	 * @return array<string, array>
	 */
	public function toSchemaArray(?string $namespace = null): array;

	/**
	 * Exports the registered tables as a formatted JSON string.
	 *
	 * @param null|string $namespace when provided, only exports tables in that namespace
	 * @param int         $flags     flags forwarded to json_encode() (default: JSON_PRETTY_PRINT)
	 *
	 * @return string
	 */
	public function toSchemaJson(?string $namespace = null, int $flags = \JSON_PRETTY_PRINT): string;

	/**
	 * Returns db namespace builder for a given namespace.
	 *
	 * @param string $namespace
	 *
	 * @return NamespaceBuilder
	 */
	public function namespace(string $namespace): NamespaceBuilder;

	/**
	 * Alias of {@see namespace()}.
	 *
	 * @param string $namespace
	 *
	 * @return NamespaceBuilder
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
	 * @return null|Table
	 */
	public function getTable(string $name): ?Table;

	/**
	 * Gets table with a given name or fail.
	 *
	 * @param string $name the table name or table full name
	 *
	 * @return Table
	 */
	public function getTableOrFail(string $name): Table;

	/**
	 * Gets table with a given morph type.
	 *
	 * @param string $morph_type the table morph type
	 *
	 * @return null|Table
	 */
	public function getTableByMorphType(string $morph_type): ?Table;

	/**
	 * Gets tables.
	 *
	 * @param null|string $namespace
	 *
	 * @return array<string, Table>
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
	 * @throws GoblException
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
	 * Executes a raw SQL string and returns the resulting PDO statement.
	 *
	 * @param string     $sql                    the SQL query string to execute
	 * @param null|array $params                 bound parameters (named or positional)
	 * @param null|array $params_types           PDO type for each parameter (`\PDO::PARAM_*`)
	 * @param bool       $is_multi_queries       `true` when `$sql` contains multiple
	 *                                           semicolon-separated statements
	 * @param bool       $in_transaction         when `true`, wraps the execution in a transaction
	 * @param bool       $auto_close_transaction when `true` (and `$in_transaction` is `true`),
	 *                                           automatically commits on success or rolls back on
	 *                                           failure; when `false`, the caller retains full
	 *                                           control and **must** commit or roll back manually
	 *
	 * @return PDOStatement
	 */
	public function execute(
		string $sql,
		?array $params = null,
		?array $params_types = null,
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
	public function select(string $sql, ?array $params = null, array $params_types = []): PDOStatement;

	/**
	 * Executes delete queries.
	 *
	 * @param string     $sql          Your sql select query
	 * @param null|array $params       Your sql select params
	 * @param array      $params_types Your sql params types
	 *
	 * @return int Affected row count
	 */
	public function delete(string $sql, ?array $params = null, array $params_types = []): int;

	/**
	 * Executes insert queries.
	 *
	 * @param string     $sql          Your sql select query
	 * @param null|array $params       Your sql select params
	 * @param array      $params_types Your sql params types
	 *
	 * @return false|string The last insert id
	 */
	public function insert(string $sql, ?array $params = null, array $params_types = []): false|string;

	/**
	 * Executes update queries.
	 *
	 * @param string     $sql          Your sql select query
	 * @param null|array $params       Your sql select params
	 * @param array      $params_types Your sql params types
	 *
	 * @return int Affected row count
	 */
	public function update(string $sql, ?array $params = null, array $params_types = []): int;

	/**
	 * Gets this rdbms query generator.
	 *
	 * @return QueryGeneratorInterface
	 */
	public function getGenerator(): QueryGeneratorInterface;
}
