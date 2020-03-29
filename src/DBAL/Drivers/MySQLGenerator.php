<?php

/**
 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gobl\DBAL\Drivers;

use Gobl\DBAL\Constraints\PrimaryKey;
use Gobl\DBAL\DbConfig;
use Gobl\DBAL\Generators\SQLGeneratorBase;
use Gobl\DBAL\QueryBuilder;
use Gobl\DBAL\Types\Interfaces\TypeInterface;

/**
 * Class MySQLGenerator
 */
class MySQLGenerator extends SQLGeneratorBase
{
	/**
	 * MySQLSyntax constructor.
	 */
	public function __construct(QueryBuilder $qb, DbConfig $config)
	{
		parent::__construct($qb, $config);
	}

	/**
	 * @inheritdoc
	 */
	public function buildQuery()
	{
		$sql = '';

		switch ($this->qb->getType()) {
			case QueryBuilder::QUERY_TYPE_CREATE_TABLE:
				$sql = $this->getTableDefinitionString();

				break;
			case QueryBuilder::QUERY_TYPE_SELECT:
				$sql = $this->getSelectQuery();

				break;
			case QueryBuilder::QUERY_TYPE_INSERT:
				$sql = $this->getInsertQuery();

				break;
			case QueryBuilder::QUERY_TYPE_UPDATE:
				$sql = $this->getUpdateQuery();

				break;
			case QueryBuilder::QUERY_TYPE_DELETE:
				$sql = $this->getDeleteQuery();

				break;
		}

		return $sql;
	}

	/**
	 * Gets table definition query string.
	 *
	 * @return string
	 */
	protected function getTableDefinitionString()
	{
		/** @var \Gobl\DBAL\Table $table */
		$table   = $this->options['createTable'];
		$columns = $table->getColumns();
		$sql     = [];

		foreach ($columns as $column) {
			$type = $column->getTypeObject();
			$c    = $type->getTypeConstant();

			switch ($c) {
				case TypeInterface::TYPE_INT:
					$sql[] = $this->getIntColumnDefinition($column);

					break;
				case TypeInterface::TYPE_BIGINT:
					$sql[] = $this->getBigintColumnDefinition($column);

					break;
				case TypeInterface::TYPE_FLOAT:
					$sql[] = $this->getFloatColumnDefinition($column);

					break;
				case TypeInterface::TYPE_STRING:
					$sql[] = $this->getStringColumnDefinition($column);

					break;
				case TypeInterface::TYPE_BOOL:
					$sql[] = $this->getBoolColumnDefinition($column);

					break;
			}
		}

		$table_alter = [];
		$uc_list     = $table->getUniqueConstraints();
		$pk          = $table->getPrimaryKeyConstraint();
		$fk_list     = $table->getForeignKeyConstraints();

		// only one primary key per table
		if ($pk instanceof PrimaryKey) {
			$sql[] = $this->getPrimaryKeySQL($table, $pk, false);
		}

		foreach ($uc_list as $name => $uc) {
			$table_alter[] = $this->getUniqueSQL($table, $uc);
		}

		foreach ($fk_list as $name => $fk) {
			$table_alter[] = $this->getForeignKeySQL($table, $fk);
		}

		$table_name  = $table->getFullName();
		$table_body  = \implode(',' . \PHP_EOL, $sql);
		$table_alter = \implode(\PHP_EOL, $table_alter);
		$charset     = $this->config->getDbCharset();

		return <<<OTPL
--
-- Table structure for table `$table_name`
--

DROP TABLE IF EXISTS `$table_name`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = $charset */;
CREATE TABLE `$table_name` (
$table_body
) ENGINE=InnoDB DEFAULT CHARSET=$charset;
$table_alter
/*!40101 SET character_set_client = @saved_cs_client */;
OTPL;
	}
}
