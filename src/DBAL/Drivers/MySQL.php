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

			$pdo_options[\PDO::ATTR_ERRMODE]            = \PDO::ERRMODE_EXCEPTION;
			$pdo_options[\PDO::ATTR_DEFAULT_FETCH_MODE] = \PDO::FETCH_ASSOC;

			$db = new \PDO('mysql:host=' . $host . ';dbname=' . $dbname . ';charset=' . $charset, $user, $password, $pdo_options);

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

			return implode(PHP_EOL, $parts);
		}

		/**
		 * {@inheritdoc}
		 */
		public function getQueryGenerator(QueryBuilder $query)
		{
			return new MySQLGenerator($query);
		}
	}
