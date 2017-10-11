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

	use Gobl\DBAL\QueryBuilder;
	use Gobl\DBAL\Generators\BaseSQLGenerator;
	use Gobl\DBAL\Types\Type;

	/**
	 * Class MySQLGenerator
	 *
	 * @package Gobl\DBAL\Drivers
	 */
	class MySQLGenerator extends BaseSQLGenerator
	{
		/**
		 * MySQLSyntax constructor.
		 *
		 * @param \Gobl\DBAL\QueryBuilder $query
		 */
		public function __construct(QueryBuilder $query)
		{
			parent::__construct($query);
		}

		/**
		 * {@inheritdoc}
		 */
		public function buildQuery()
		{
			$sql = '';

			switch ($this->query->getType()) {
				case QueryBuilder::QUERY_TYPE_CREATE_TABLE :
					$sql = $this->getTableDefinitionString();
					break;
				case QueryBuilder::QUERY_TYPE_SELECT :
					$sql = $this->getSelectQuery();
					break;
				case QueryBuilder::QUERY_TYPE_INSERT :
					$sql = $this->getInsertQuery();
					break;
				case QueryBuilder::QUERY_TYPE_UPDATE :
					$sql = $this->getUpdateQuery();
					break;
				case QueryBuilder::QUERY_TYPE_DELETE :
					$sql = $this->getDeleteQuery();
					break;
			}

			return $sql;
		}

		/**
		 * Get table definition query string.
		 *
		 * @return string
		 */
		public function getTableDefinitionString()
		{
			/** @var \Gobl\DBAL\Table $table */
			$table   = $this->options['createTable'];
			$columns = $table->getColumns();
			$sql     = [];

			foreach ($columns as $column) {
				$type = $column->getTypeObject();
				$c    = $type->getTypeConstant();

				switch ($c) {
					case
					Type::TYPE_INT:
						$sql[] = $this->getIntColumnDefinition($column);
						break;
					case Type::TYPE_BIGINT:
						$sql[] = $this->getBigintColumnDefinition($column);
						break;
					case Type::TYPE_FLOAT:
						$sql[] = $this->getFloatColumnDefinition($column);
						break;
					case Type::TYPE_STRING:
						$sql[] = $this->getStringColumnDefinition($column);
						break;
					case Type::TYPE_BOOL:
						$sql[] = $this->getBoolColumnDefinition($column);
						break;
				}
			}

			$table_alter = [];
			$uc          = $table->getUniqueConstraints();
			$pk          = $table->getPrimaryKeyConstraints();
			$fk          = $table->getForeignKeyConstraints();

			// only one primary key per table
			foreach ($pk as $name => $columns) {
				$sql[] = $this->getPrimaryKeyConstraintDefinition($table, $columns, $name, false);
			}

			foreach ($uc as $name => $columns) {
				$table_alter[] = $this->getUniqueConstraintDefinition($table, $columns, $name);
			}

			foreach ($fk as $name => $rule) {
				$reference     = $rule['reference'];
				$map           = $rule['map'];
				$table_alter[] = $this->getForeignKeyConstraintDefinition($table, $reference, $map, $name);
			}

			$table_name  = $table->getFullName();
			$table_body  = implode(',' . PHP_EOL, $sql);
			$table_alter = implode(PHP_EOL, $table_alter);
			$mysql       = <<<OTPL
--
-- Table structure for table `$table_name`
--

DROP TABLE IF EXISTS `$table_name`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `$table_name` (
$table_body
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
$table_alter
/*!40101 SET character_set_client = @saved_cs_client */;
OTPL;

			return $mysql;
		}
	}
