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
use Gobl\DBAL\Exceptions\DBALUniqueViolationException;
use Gobl\DBAL\Queries\QBUtils;
use Gobl\Gobl;
use Override;
use PDO;
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

	#[Override]
	public function getConfig(): DbConfig
	{
		return $this->config;
	}

	#[Override]
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
	 * - When `$transaction_counter` is 0 (outermost call), delegates to {@see beginOuterTransaction()}.
	 * - For every subsequent nested call, issues `SAVEPOINT sp_N` where `N` is the new counter value.
	 *
	 * @throws DBALException
	 */
	#[Override]
	public function beginTransaction(): bool
	{
		$con = $this->getConnection();

		++$this->transaction_counter;

		if (1 === $this->transaction_counter) {
			return $this->beginOuterTransaction($con);
		}

		$con->exec(\sprintf('SAVEPOINT %s_%s', $this->transaction_name_prefix, $this->transaction_counter));

		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Decrements `$transaction_counter`:
	 * - When it reaches 0 (outermost transaction), calls {@see commitOuterTransaction()}.
	 * - For nested transactions, issues `RELEASE SAVEPOINT sp_N` to commit the savepoint.
	 *
	 * @throws DBALException
	 */
	#[Override]
	public function commit(): bool
	{
		if ($this->transaction_counter > 0) {
			--$this->transaction_counter;

			$con = $this->getConnection();

			if (0 === $this->transaction_counter) {
				return $this->commitOuterTransaction($con);
			}

			$con->exec(\sprintf('RELEASE SAVEPOINT %s_%s', $this->transaction_name_prefix, $this->transaction_counter + 1));
		}

		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Decrements `$transaction_counter`:
	 * - When it reaches 0 (outermost transaction), calls {@see rollBackOuterTransaction()}.
	 * - For nested transactions, issues `ROLLBACK TO SAVEPOINT sp_N` to roll back to the savepoint
	 *   without aborting the outer transaction.
	 *
	 * @throws DBALException
	 */
	#[Override]
	public function rollBack(): bool
	{
		if ($this->transaction_counter > 0) {
			--$this->transaction_counter;

			$con = $this->getConnection();

			if (0 === $this->transaction_counter) {
				return $this->rollBackOuterTransaction($con);
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
	#[Override]
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

			throw $this->toUniqueViolation($e) ?? $e;
		}
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws Exception
	 */
	#[Override]
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
	#[Override]
	public function select($sql, ?array $params = null, array $params_types = []): PDOStatement
	{
		return $this->execute($sql, $params, $params_types);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws DBALException
	 */
	#[Override]
	public function delete($sql, ?array $params = null, array $params_types = []): int
	{
		return $this->query($sql, $params, $params_types);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws DBALException
	 */
	#[Override]
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
	#[Override]
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

	/**
	 * Reads, from the database's error, the key a write met: the table's full name when the error says
	 * it, the constraint's name when it says it, otherwise the columns' full names.
	 *
	 * @return null|array{table: null|string, constraint: null|string, columns: list<string>} null when
	 *                                                                                         the error is not a duplicate key
	 */
	abstract protected function readUniqueViolation(PDOException $e): ?array;

	/**
	 * Starts the outermost transaction.
	 */
	protected function beginOuterTransaction(PDO $con): bool
	{
		return $con->beginTransaction();
	}

	/**
	 * Commits the outermost transaction.
	 */
	protected function commitOuterTransaction(PDO $con): bool
	{
		return $con->commit();
	}

	/**
	 * Rolls the outermost transaction back.
	 */
	protected function rollBackOuterTransaction(PDO $con): bool
	{
		return $con->rollBack();
	}

	/**
	 * The database's duplicate-key error, as the key of this database's schema it names.
	 *
	 * Null when the error is not a duplicate key, or names a key this schema does not declare (a key
	 * added by hand): the database's own error is then kept.
	 */
	private function toUniqueViolation(PDOException $e): ?DBALUniqueViolationException
	{
		$read = $this->readUniqueViolation($e);

		if (null === $read) {
			return null;
		}

		foreach ($this->getTables() as $table) {
			if (null !== $read['table'] && 0 !== \strcasecmp($read['table'], $table->getFullName())) {
				continue;
			}

			$pk   = $table->getPrimaryKeyConstraint();
			$keys = \array_values($table->getUniqueKeyConstraints());

			if (null !== $pk) {
				$keys[] = $pk;
			}

			foreach ($keys as $key) {
				if (null !== $read['constraint']) {
					// MySQL names every primary key `PRIMARY`; PostgreSQL folds an unquoted name to
					// lowercase.
					$found = 0 === \strcasecmp($read['constraint'], $key->getName())
						|| ($key === $pk && null !== $read['table'] && 'PRIMARY' === $read['constraint']);
				} else {
					$a = $read['columns'];
					$b = $key->getColumns();

					\sort($a);
					\sort($b);

					$found = $a === $b;
				}

				if ($found) {
					return DBALUniqueViolationException::of($key, $e);
				}
			}
		}

		return null;
	}
}
