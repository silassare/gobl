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

namespace Gobl\DBAL\Drivers\MySQL;

use Gobl\DBAL\Constraints\ForeignKey;
use Gobl\DBAL\Constraints\ForeignKeyAction;
use Gobl\DBAL\DbConfig;
use Gobl\DBAL\Diff\Actions\ColumnTypeChanged;
use Gobl\DBAL\Diff\Actions\ForeignKeyConstraintDeleted;
use Gobl\DBAL\Diff\Actions\PrimaryKeyConstraintDeleted;
use Gobl\DBAL\Diff\Actions\UniqueKeyConstraintDeleted;
use Gobl\DBAL\Drivers\SQLQueryGeneratorBase;
use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Queries\QBDelete;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\DBAL\Queries\QBUpdate;
use Gobl\Gobl;

use const GOBL_ASSETS_DIR;

/**
 * Class MySQLQueryGenerator.
 */
class MySQLQueryGenerator extends SQLQueryGeneratorBase
{
	protected int $decimal_precision_max      = 65;
	protected int $decimal_scale_max          = 30;
	private static bool $templates_registered = false;

	/**
	 * MySQLQueryGenerator constructor.
	 *
	 * @param RDBMSInterface $db
	 * @param DbConfig       $config
	 */
	public function __construct(RDBMSInterface $db, DbConfig $config)
	{
		parent::__construct($db, $config);

		if (!self::$templates_registered) {
			self::$templates_registered = true;

			Gobl::addTemplates([
				'mysql_db'           => ['path' => GOBL_ASSETS_DIR . '/mysql/db.sql'],
				'mysql_create_table' => ['path' => GOBL_ASSETS_DIR . '/mysql/create_table.sql'],
			]);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function wrapDatabaseDefinitionQuery(string $query): string
	{
		$charset = $this->config->getDbCharset();

		return <<<SQL
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES {$charset} */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

{$query}

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
SQL;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws DBALException
	 */
	protected function getForeignKeySQL(ForeignKey $fk, bool $alter): string
	{
		$update_action = $fk->getUpdateAction();
		$delete_action = $fk->getDeleteAction();
		$error_message = \sprintf(
			'MySQL default engine InnoDB does not support SET DEFAULT action. Defined on table "%s" for columns (%s).',
			$fk->getHostTable()->getFullName(),
			\implode(', ', \array_keys($fk->getColumnsMapping()))
		);
		if (ForeignKeyAction::SET_DEFAULT === $update_action) {
			throw new DBALException($error_message);
		}
		if (ForeignKeyAction::SET_DEFAULT === $delete_action) {
			throw new DBALException($error_message);
		}

		return parent::getForeignKeySQL($fk, $alter);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function getLimitQuery(QBDelete|QBSelect|QBUpdate $qb): string
	{
		$ignore_offset = $qb instanceof QBUpdate || $qb instanceof QBDelete;
		$offset        = $qb->getOptionsLimitOffset();
		$max           = $qb->getOptionsLimitMax();
		$sql           = '';

		if (\is_int($max)) {
			$sql = ' LIMIT ' . $max;

			if (!$ignore_offset && \is_int($offset)) {
				$sql .= ' OFFSET ' . $offset;
			}
		}

		return $sql;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function getPrimaryKeyConstraintDeletedString(PrimaryKeyConstraintDeleted $action): string
	{
		$table_name = $action->getConstraint()
			->getHostTable()
			->getFullName();

		return 'ALTER TABLE `' . $table_name . '` DROP PRIMARY KEY;';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function getForeignKeyConstraintDeletedString(ForeignKeyConstraintDeleted $action): string
	{
		$constraint = $action->getConstraint();
		$table_name = $constraint->getHostTable()
			->getFullName();

		$constraint_name = $constraint->getName();

		return 'ALTER TABLE `' . $table_name . '` DROP FOREIGN KEY ' . $constraint_name . ';';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function getUniqueKeyConstraintDeletedString(UniqueKeyConstraintDeleted $action): string
	{
		$constraint = $action->getConstraint();
		$table_name = $constraint->getHostTable()
			->getFullName();

		$constraint_name = $constraint->getName();

		return 'ALTER TABLE `' . $table_name . '` DROP INDEX ' . $constraint_name . ';';
	}

	/**
	 * @throws DBALException
	 */
	protected function getColumnTypeChangedString(ColumnTypeChanged $action): string
	{
		$new_column        = $action->getNewColumn();
		$column_definition = $this->getColumnDefinitionString($new_column);
		$table_name        = $action->getTable()
			->getFullName();

		return 'ALTER TABLE `' . $table_name . '` CHANGE `' . $new_column->getFullName() . '` ' . $column_definition . ';';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function dbQueryTemplate(): string
	{
		return 'mysql_db';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function createTableQueryTemplate(): string
	{
		return 'mysql_create_table';
	}
}
