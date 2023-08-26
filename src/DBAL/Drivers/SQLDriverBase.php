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

namespace Gobl\DBAL\Drivers;

use Closure;
use Exception;
use Gobl\DBAL\Db;
use Gobl\DBAL\DbConfig;
use Gobl\DBAL\Drivers\MySQL\MySQL;
use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Queries\QBUtils;
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
	 * @param \Gobl\DBAL\DbConfig $config
	 */
	protected function __construct(protected DbConfig $config)
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function getConfig(): DbConfig
	{
		return $this->config;
	}

	/**
	 * {@inheritDoc}
	 */
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function execute(
		$sql,
		array $params = null,
		array $params_types = null,
		bool $is_multi_queries = false,
		bool $in_transaction = false,
		bool $auto_close_transaction = false
	): PDOStatement {
		if (empty($sql)) {
			throw new DBALException('Your query is empty.');
		}

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

			if ($is_multi_queries) {
				/* https://bugs.php.net/bug.php?id=61613 */
				while (1) {
					if (!$stmt->nextRowset()) {
						break;
					}
				}
			}

			if ($in_transaction && $auto_close_transaction) {
				$this->commit();
			}

			return $stmt;
		} catch (PDOException $e) {
			if ($in_transaction && $auto_close_transaction) {
				$this->rollBack();
			}

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
		// Mysql seems to auto commit if there is a DDL query (CREATE OR DROP Table)
		$in_transaction = MySQL::NAME !== $this->getType();

		return $this->execute($sql, null, null, true, $in_transaction, $in_transaction);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function select($sql, array $params = null, array $params_types = []): PDOStatement
	{
		return $this->execute($sql, $params, $params_types);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function delete($sql, array $params = null, array $params_types = []): int
	{
		return $this->query($sql, $params, $params_types);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function insert($sql, array $params = null, array $params_types = []): string|false
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function update($sql, array $params = null, array $params_types = []): int
	{
		return $this->query($sql, $params, $params_types);
	}

	/**
	 * @param string     $sql
	 * @param null|array $params
	 * @param array      $params_types
	 *
	 * @return int
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	protected function query(string $sql, array $params = null, array $params_types = []): int
	{
		return $this->execute($sql, $params, $params_types)
			->rowCount();
	}
}
