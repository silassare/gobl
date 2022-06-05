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
	public static function createInstance(DbConfig $config): self;

	/**
	 * Lock this column to prevent edit.
	 */
	public function lock(): self;

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
	 * Adds table.
	 *
	 * @param \Gobl\DBAL\Table $table The table to add
	 *
	 * @return $this
	 */
	public function addTable(Table $table): self;

	/**
	 * Adds table from options.
	 *
	 * @param string $namespace The namespace to use
	 * @param array  $tables    The tables options
	 *
	 * @return $this
	 */
	public function addTablesToNamespace(string $namespace, array $tables): self;

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
	 * @return \Gobl\DBAL\Table[]
	 */
	public function getTables(?string $namespace = null): array;

	/**
	 * Returns the rdbms type.
	 *
	 * @return string
	 */
	public function getType(): string;

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
