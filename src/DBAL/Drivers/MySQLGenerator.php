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
use Gobl\DBAL\Exceptions\DBALException;
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
	 *
	 * @param \Gobl\DBAL\QueryBuilder $qb
	 * @param \Gobl\DBAL\DbConfig     $config
	 */
	public function __construct(QueryBuilder $qb, DbConfig $config)
	{
		parent::__construct($qb, $config);
	}

	/**
	 * @inheritdoc
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
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
	 * @param bool $include_alter
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 *
	 * @return string
	 */
	public function getTableDefinitionString($include_alter = true)
	{
		if ($this->qb->getType() !== QueryBuilder::QUERY_TYPE_CREATE_TABLE) {
			throw new DBALException('Invalid query builder type.');
		}

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

		$pk_sql = $this->getTablePrimaryKeysDefinitionString();

		if ($pk_sql) {
			$sql[] = $this->getTablePrimaryKeysDefinitionString();
		}

		$table_name  = $table->getFullName();
		$table_body  = \implode(',' . \PHP_EOL, $sql);
		$charset     = $this->config->getDbCharset();
		$collate     = $this->config->getDbCollate();

		$alter_table = '';

		if ($include_alter) {
			$alter_table = \PHP_EOL
							 . $this->getTableUniqueConstraintsDefinitionString()
							 . \PHP_EOL . $this->getTableForeignKeysDefinitionString();
		}

		return <<<GOBL_MySQL
--
-- Table structure for table `$table_name`
--
DROP TABLE IF EXISTS `$table_name`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = $charset */;
CREATE TABLE `$table_name` (
$table_body
) ENGINE=InnoDB DEFAULT CHARSET=$charset COLLATE=$collate;
$alter_table
/*!40101 SET character_set_client = @saved_cs_client */;
GOBL_MySQL;
	}

	/**
	 * Gets table primary keys definition query string.
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 *
	 * @return string
	 */
	public function getTablePrimaryKeysDefinitionString()
	{
		if ($this->qb->getType() !== QueryBuilder::QUERY_TYPE_CREATE_TABLE) {
			throw new DBALException('Invalid query builder type, table creation query required.');
		}

		/** @var \Gobl\DBAL\Table $table */
		$table       = $this->options['createTable'];
		$pk          = $table->getPrimaryKeyConstraint();

		// only one primary key per table
		if ($pk instanceof PrimaryKey) {
			return $this->getPrimaryKeySQL($table, $pk, false);
		}

		return '';
	}

	/**
	 * Gets table unique constraints definition query string.
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 *
	 * @return string
	 */
	public function getTableUniqueConstraintsDefinitionString()
	{
		if ($this->qb->getType() !== QueryBuilder::QUERY_TYPE_CREATE_TABLE) {
			throw new DBALException('Invalid query builder type, table creation query required.');
		}

		/** @var \Gobl\DBAL\Table $table */
		$table       = $this->options['createTable'];
		$table_alter = [];
		$uc_list     = $table->getUniqueConstraints();

		foreach ($uc_list as $name => $uc) {
			$table_alter[] = $this->getUniqueSQL($table, $uc);
		}

		$table_name  = $table->getFullName();
		$table_alter = \implode(\PHP_EOL, $table_alter);

		if (empty($table_alter)) {
			return '';
		}

		return <<<GOBL_MySQL
--
-- Unique constraints definition for table `$table_name`
--
$table_alter
GOBL_MySQL;
	}

	/**
	 * Gets table foreign keys definition query string.
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 *
	 * @return string
	 */
	public function getTableForeignKeysDefinitionString()
	{
		if ($this->qb->getType() !== QueryBuilder::QUERY_TYPE_CREATE_TABLE) {
			throw new DBALException('Invalid query builder type, table creation query required.');
		}

		/** @var \Gobl\DBAL\Table $table */
		$table       = $this->options['createTable'];
		$table_alter = [];
		$fk_list     = $table->getForeignKeyConstraints();

		foreach ($fk_list as $name => $fk) {
			$table_alter[] = $this->getForeignKeySQL($table, $fk);
		}

		$table_name  = $table->getFullName();
		$table_alter = \implode(\PHP_EOL, $table_alter);

		if (empty($table_alter)) {
			return '';
		}

		return <<<GOBL_MySQL
--
-- Foreign keys constraints definition for table `$table_name`
--
$table_alter
GOBL_MySQL;
	}
}
