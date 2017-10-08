<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the Gobl package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace Gobl\DBAL;

	/**
	 * Interface RDBMS
	 *
	 * @package Gobl\DBAL
	 */
	interface RDBMS
	{
		/**
		 * the relational database management system constructor.
		 *
		 * @param array $config
		 */
		public function __construct(array $config);

		/**
		 * connect to the relational database management system.
		 *
		 * @return \PDO
		 */
		public function connect();

		/**
		 * Builds database query.
		 *
		 * When namespace is not empty,
		 * only tables with the given namespace will be generated.
		 *
		 * @param \Gobl\DBAL\Db $db        the database object
		 * @param string|null          $namespace the table namespace to generate
		 *
		 * @return string
		 */
		public function buildDatabase(Db $db, $namespace = null);

		/**
		 * get this rdbms query generator.
		 *
		 * @param \Gobl\DBAL\QueryBuilder $query
		 *
		 * @return \Gobl\DBAL\Generators\BaseSQLGenerator
		 */
		public function getQueryGenerator(QueryBuilder $query);
	}
