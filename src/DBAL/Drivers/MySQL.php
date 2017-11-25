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
	use Gobl\DBAL\QueryBuilder;
	use Gobl\DBAL\RDBMS;

	/**
	 * Class MySQL
	 *
	 * @package Gobl\DBAL\Drivers
	 */
	class MySQL implements RDBMS
	{
		private $config = [];

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
		 * {@inheritdoc}
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
			$pdo_dsn = 'mysql:host=' . $host . ';dbname=' . $dbname . ';charset=' . $charset;
			$db      = new \PDO($pdo_dsn, $user, $password, $pdo_options);

			return $db;
		}

		/**
		 * {@inheritdoc}
		 */
		public function buildDatabase(Db $db, $namespace = null)
		{
			$parts  = [];
			$tables = $db->getTables();
			foreach ($tables as $table_name => $table) {
				if (!empty($namespace) AND $namespace !== $table->getNamespace()) {
					continue;
				}

				$qb = new QueryBuilder($db);
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
		 * {@inheritdoc}
		 */
		public function getQueryGenerator(QueryBuilder $query)
		{
			return new MySQLGenerator($query);
		}
	}
