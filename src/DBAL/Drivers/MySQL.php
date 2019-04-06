<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the Gobl package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace Gobl\DBAL\Drivers;

	use Gobl\DBAL\Db;
	use Gobl\DBAL\Exceptions\DBALException;
	use Gobl\DBAL\QueryBuilder;
	use Gobl\DBAL\QueryTokenParser;

	/**
	 * Class MySQL
	 *
	 * @package Gobl\DBAL\Drivers
	 */
	class MySQL extends Db
	{
		/**
		 * @var array
		 */
		private $config;

		/**
		 * @var int
		 */
		private $transaction_counter = 0;

		/**
		 * MySQL constructor.
		 *
		 * @param array $config
		 */
		public function __construct(array $config)
		{
			$this->config = $config;
		}

		/**
		 * @inheritdoc
		 */
		public function connect()
		{
			$host     = $this->config['db_host'];
			$dbname   = $this->config['db_name'];
			$user     = $this->config['db_user'];
			$password = $this->config['db_pass'];
			$charset  = $this->config['db_charset'];

			$pdo_options = [
				\PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
				\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
			];

			// DSN => DATA SOURCE NAME
			$pdo_dsn    = 'mysql:host=' . $host . ';dbname=' . $dbname . ';charset=' . $charset;
			$connection = new \PDO($pdo_dsn, $user, $password, $pdo_options);

			return $connection;
		}

		/**
		 * @inheritdoc
		 */
		public function beginTransaction()
		{
			$con = $this->getConnection();

			$this->transaction_counter++;

			if ($this->transaction_counter === 1) {
				return $con->beginTransaction();
			}

			$con->exec('SAVEPOINT gobl_trans_' . $this->transaction_counter);

			return true;
		}

		/**
		 * @inheritdoc
		 */
		public function commit()
		{
			if ($this->transaction_counter > 0) {
				--$this->transaction_counter;

				$con = $this->getConnection();

				if (!$this->transaction_counter) {
					return $con->commit();
				}

				$con->exec('RELEASE SAVEPOINT gobl_trans_' . ($this->transaction_counter + 1));
			}

			return true;
		}

		/**
		 * @inheritdoc
		 */
		public function rollBack()
		{
			if ($this->transaction_counter > 0) {
				--$this->transaction_counter;

				$con = $this->getConnection();

				if (!$this->transaction_counter) {
					return $con->rollback();
				}

				$con->exec('ROLLBACK TO gobl_trans_' . ($this->transaction_counter + 1));
			}

			return true;
		}

		/**
		 * @inheritdoc
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function execute($sql, array $params = null, array $params_types = null, $is_multi_queries = false, $in_transaction = false, $auto_close_transaction = false)
		{
			if (empty($sql)) {
				throw new DBALException('Your query is empty.');
			}

			if ($in_transaction) {
				$this->beginTransaction();
			}

			$connection = $this->getConnection();

			try {
				$stmt = $connection->prepare($sql);

				if ($params !== null) {
					foreach ($params as $key => $value) {
						if (isset($params_types[$key])) {
							$param_type = $params_types[$key];
						} else {
							$param_type = QueryTokenParser::paramType($value);
						}

						$stmt->bindValue(is_int($key) ? $key + 1 : $key, $value, $param_type);
					}
				}

				$stmt->execute();

				if ($is_multi_queries) {
					/* https://bugs.php.net/bug.php?id=61613 */
					$i = 0;
					while ($stmt->nextRowset()) {
						$i++;
					}
				}

				if ($in_transaction AND $auto_close_transaction) {
					$this->commit();
				}

				return $stmt;
			} catch (\PDOException $e) {
				if ($in_transaction AND $auto_close_transaction) {
					$this->rollBack();
				}

				throw $e;
			}
		}

		/**
		 * @inheritdoc
		 * @throws \Exception
		 */
		public function executeMulti($sql)
		{
			return $this->execute($sql, null, null, true, true, true);
		}

		/**
		 * @inheritdoc
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		private function query($sql, array $params = null, array $params_types = [])
		{
			$stmt = $this->execute($sql, $params, $params_types);

			return $stmt->rowCount();
		}

		/**
		 * @inheritdoc
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function select($sql, array $params = null, array $params_types = [])
		{
			return $this->execute($sql, $params, $params_types);
		}

		/**
		 * @inheritdoc
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function delete($sql, array $params = null, array $params_types = [])
		{
			return $this->query($sql, $params, $params_types);
		}

		/**
		 * @inheritdoc
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function insert($sql, array $params = null, array $params_types = [])
		{
			$stmt    = $this->execute($sql, $params, $params_types);
			$last_id = $this->getConnection()
							->lastInsertId();

			$stmt->closeCursor();

			return $last_id;
		}

		/**
		 * @inheritdoc
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function update($sql, array $params = null, array $params_types = [])
		{
			return $this->query($sql, $params, $params_types);
		}

		/**
		 * @inheritdoc
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function buildDatabase($namespace = null)
		{
			// checks all foreign key constraints
			$tables = $this->getTables($namespace);
			$parts  = [];

			foreach ($tables as $table) {
				$fk_list = $table->getForeignKeyConstraints();
				foreach ($fk_list as $fk) {
					$columns = array_values($fk->getConstraintColumns());
					// necessary when whe have
					// table_a.col_1 => table_b.col_x
					// table_a.col_2 => table_b.col_x
					$columns = array_unique($columns);

					if (!$fk->getReferenceTable()
							->isPrimaryKey($columns)) {
						$ref_name = $fk->getReferenceTable()
									   ->getName();
						throw new DBALException(sprintf('Foreign key "%s" of table "%s" should be primary key in the reference table "%s".', implode(',', $columns), $table->getName(), $ref_name));
					}
				}

				$qb = new QueryBuilder($this);
				$qb->createTable($table);
				$parts[] = $this->getQueryGenerator($qb)
								->getTableDefinitionString();
			}

			$mysql = implode(PHP_EOL, $parts);

			return <<<GOBL_MySQL
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

$mysql

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
GOBL_MySQL;
		}

		/**
		 * @inheritdoc
		 */
		public function getQueryGenerator(QueryBuilder $query)
		{
			return new MySQLGenerator($query);
		}
	}
