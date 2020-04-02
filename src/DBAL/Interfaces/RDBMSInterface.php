<?php

/**
 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gobl\DBAL\Interfaces;

use Gobl\DBAL\QueryBuilder;

/**
 * Interface RDBMSInterface
 */
interface RDBMSInterface
{
	const MYSQL = 'mysql';

	/**
	 * The Relational DataBase Management System constructor.
	 *
	 * @param array $config
	 */
	public function __construct(array $config);

	/**
	 * Begin a new transaction.
	 *
	 * @return bool
	 */
	public function beginTransaction();

	/**
	 * Commit current transaction.
	 *
	 * @return bool
	 */
	public function commit();

	/**
	 * Rollback current transaction.
	 *
	 * @return bool
	 */
	public function rollBack();

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
	 * @return \PDOStatement
	 */
	public function execute($sql, array $params = null, array $params_types = null, $is_multi_queries = false, $in_transaction = false, $auto_close_transaction = false);

	/**
	 * Executes sql string with multiples query.
	 *
	 * Suitable for sql file content.
	 *
	 * @param string $sql the sql query string
	 *
	 * @return \PDOStatement
	 */
	public function executeMulti($sql);

	/**
	 * Executes select queries.
	 *
	 * @param string     $sql          Your sql select query
	 * @param null|array $params       Your sql select params
	 * @param array      $params_types Your sql params types
	 *
	 * @return \PDOStatement
	 */
	public function select($sql, array $params = null, array $params_types = []);

	/**
	 * Executes delete queries.
	 *
	 * @param string     $sql          Your sql select query
	 * @param null|array $params       Your sql select params
	 * @param array      $params_types Your sql params types
	 *
	 * @return int Affected row count
	 */
	public function delete($sql, array $params = null, array $params_types = []);

	/**
	 * Executes insert queries.
	 *
	 * @param string     $sql          Your sql select query
	 * @param null|array $params       Your sql select params
	 * @param array      $params_types Your sql params types
	 *
	 * @return string The last insert id
	 */
	public function insert($sql, array $params = null, array $params_types = []);

	/**
	 * Executes update queries.
	 *
	 * @param string     $sql          Your sql select query
	 * @param null|array $params       Your sql select params
	 * @param array      $params_types Your sql params types
	 *
	 * @return int Affected row count
	 */
	public function update($sql, array $params = null, array $params_types = []);

	/**
	 * Builds database query.
	 *
	 * When namespace is not empty,
	 * only tables with the given namespace will be generated.
	 *
	 * @param null|string $namespace the table namespace to generate
	 *
	 * @return string
	 */
	public function buildDatabase($namespace = null);

	/**
	 * Gets this rdbms query generator.
	 *
	 * @param \Gobl\DBAL\QueryBuilder $query
	 *
	 * @return \Gobl\DBAL\Generators\SQLGeneratorBase
	 */
	public function getQueryGenerator(QueryBuilder $query);
}
