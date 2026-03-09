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

namespace Gobl\DBAL\Drivers;

use Closure;
use Exception;
use Gobl\DBAL\Db;
use Gobl\DBAL\DbConfig;
use Gobl\DBAL\Drivers\MySQL\MySQL;
use Gobl\DBAL\Drivers\SQLite\SQLite;
use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Queries\QBUtils;
use Gobl\Gobl;
use PDOException;
use PDOStatement;

/**
 * Class SQLDriverBase.
 */
abstract class SQLDriverBase extends Db
{
	protected int $transaction_counter = 0;

	protected string $transaction_name_prefix = 'gobl_transaction_';

	/**
	 * SQLDriverBase constructor.
	 *
	 * @param DbConfig $config
	 */
	protected function __construct(protected DbConfig $config) {}

	public function getConfig(): DbConfig
	{
		return $this->config;
	}

	public function runInTransaction(Closure $callable): mixed
	{
		$failed  = true;
		$started = false;

		try {
			$started = $this->beginTransaction();
			$result  = $callable();
			$failed  = !$this->commit();
		} finally {
			$started && $failed && $this->rollBack();
		}

		return $result;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Implements **nested transaction emulation via SAVEPOINTs**.
	 * - When `$transaction_counter` is 0 (outermost call), delegates to `PDO::beginTransaction()`.
	 * - For every subsequent nested call, issues `SAVEPOINT sp_N` where `N` is the new counter value.
	 *
	 * @throws DBALException
	 */
	public function beginTransaction(): bool
	{
		$con = $this->getConnection();

		++$this->transaction_counter;

		if (1 === $this->transaction_counter) {
			return $con->beginTransaction();
		}

		$con->exec(\sprintf('SAVEPOINT %s_%s', $this->transaction_name_prefix, $this->transaction_counter));

		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Decrements `$transaction_counter`:
	 * - When it reaches 0 (outermost transaction), calls `PDO::commit()`.
	 * - For nested transactions, issues `RELEASE SAVEPOINT sp_N` to commit the savepoint.
	 *
	 * @throws DBALException
	 */
	public function commit(): bool
	{
		if ($this->transaction_counter > 0) {
			--$this->transaction_counter;

			$con = $this->getConnection();

			if (0 === $this->transaction_counter) {
				return $con->commit();
			}

			$con->exec(\sprintf('RELEASE SAVEPOINT %s_%s', $this->transaction_name_prefix, $this->transaction_counter + 1));
		}

		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Decrements `$transaction_counter`:
	 * - When it reaches 0 (outermost transaction), calls `PDO::rollback()`.
	 * - For nested transactions, issues `ROLLBACK TO SAVEPOINT sp_N` to roll back to the savepoint
	 *   without aborting the outer transaction.
	 *
	 * @throws DBALException
	 */
	public function rollBack(): bool
	{
		if ($this->transaction_counter > 0) {
			--$this->transaction_counter;

			$con = $this->getConnection();

			if (0 === $this->transaction_counter) {
				return $con->rollback();
			}

			$con->exec(\sprintf('ROLLBACK TO %s_%s', $this->transaction_name_prefix, $this->transaction_counter + 1));
		}

		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws DBALException
	 */
	public function execute(
		$sql,
		?array $params = null,
		?array $params_types = null,
		bool $is_multi_queries = false,
		bool $in_transaction = false,
		bool $auto_close_transaction = false
	): PDOStatement {
		if (empty($sql)) {
			throw new DBALException('Your query is empty.');
		}

		$ql = Gobl::ql()->start($sql, $params, $params_types);

		if ($in_transaction) {
			$this->beginTransaction();
		}

		$connection = $this->getConnection();

		try {
			$stmt = $connection->prepare($sql);

			if (null !== $params) {
				foreach ($params as $key => $value) {
					$param_type = $params_types[$key] ?? QBUtils::paramType($value);

					$stmt->bindValue(\is_int($key) ? $key + 1 : $key, $value, $param_type);
				}
			}

			$stmt->execute();

			$ql('executed');

			if ($is_multi_queries) {
				/* https://bugs.php.net/bug.php?id=61613 */
				while (1) {
					try {
						if ($stmt->nextRowset()) {
							continue;
						}
					} catch (PDOException $e) {
						// Some drivers (e.g. SQLite) do not support nextRowset().
						if ('IM001' === $e->getCode()) {
							break;
						}

						throw $e;
					}

					break;
				}
			}

			if ($in_transaction && $auto_close_transaction) {
				$this->commit();
			}

			$ql('end');

			return $stmt;
		} catch (PDOException $e) {
			if ($in_transaction && $auto_close_transaction) {
				$this->rollBack();
			}

			$ql('end');

			throw $e;
		}
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws Exception
	 */
	public function executeMulti($sql): PDOStatement
	{
		$db_type = $this->getType();

		if (SQLite::NAME === $db_type || 'postgresql' === $db_type) {
			// SQLite's PDO prepare() only compiles the first SQL statement when given a
			// multi-statement string; PDO::exec() forwards to sqlite3_exec() which
			// properly handles all semicolon-separated statements in one call.
			//
			// PostgreSQL's PDO prepare() rejects multi-statement strings with
			// "cannot insert multiple commands into a prepared statement".
			// PDO::exec() uses libpq's PQexec(), which handles multiple commands.

			$connection = $this->getConnection();
			$connection->exec($sql);

			// DDL operations do not produce result sets;
			// return a trivial prepared statement to satisfy the return type.
			return $connection->prepare('SELECT 1');
		}

		// Mysql seems to auto commit if there is a DDL query (CREATE OR DROP Table)
		// so we avoid running in a transaction
		$in_transaction = MySQL::NAME !== $db_type;

		return $this->execute($sql, null, null, true, $in_transaction, $in_transaction);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws DBALException
	 */
	public function select($sql, ?array $params = null, array $params_types = []): PDOStatement
	{
		return $this->execute($sql, $params, $params_types);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws DBALException
	 */
	public function delete($sql, ?array $params = null, array $params_types = []): int
	{
		return $this->query($sql, $params, $params_types);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws DBALException
	 */
	public function insert($sql, ?array $params = null, array $params_types = []): false|string
	{
		// To be able to get the last inserted id
		// This statement should not be run in a new transaction
		// if we are in a transaction, no problem, we benefit from calling
		// the pdo lastInsertId method before the commit happens
		// also take in consideration that the lastInsertId method is not reliable
		// when using multiple insert in one query
		// see https://www.php.net/manual/en/pdo.lastinsertid.php#107622

		$stmt    = $this->execute($sql, $params, $params_types);
		$last_id = $this->getConnection()
			->lastInsertId();

		$stmt->closeCursor();

		return $last_id;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws DBALException
	 */
	public function update($sql, ?array $params = null, array $params_types = []): int
	{
		return $this->query($sql, $params, $params_types);
	}

	/**
	 * Executes a write SQL statement (INSERT, UPDATE, DELETE, DDL) and returns the affected-row count.
	 *
	 * Delegates to `execute()` with default options (no explicit transaction) and returns
	 * the statement's `rowCount()` value.
	 *
	 * @param string     $sql
	 * @param null|array $params
	 * @param array      $params_types
	 *
	 * @return int number of rows affected
	 *
	 * @throws DBALException
	 */
	protected function query(string $sql, ?array $params = null, array $params_types = []): int
	{
		return $this->execute($sql, $params, $params_types)
			->rowCount();
	}
}
