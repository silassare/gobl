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

use Gobl\DBAL\Column;
use Gobl\DBAL\Constraints\ForeignKey;
use Gobl\DBAL\Constraints\ForeignKeyAction;
use Gobl\DBAL\Constraints\PrimaryKey;
use Gobl\DBAL\Constraints\Unique;
use Gobl\DBAL\DbConfig;
use Gobl\DBAL\Diff\Actions\ColumnAdded;
use Gobl\DBAL\Diff\Actions\ColumnDeleted;
use Gobl\DBAL\Diff\Actions\ColumnRenamed;
use Gobl\DBAL\Diff\Actions\ColumnTypeChanged;
use Gobl\DBAL\Diff\Actions\DBCharsetChanged;
use Gobl\DBAL\Diff\Actions\DBCollateChanged;
use Gobl\DBAL\Diff\Actions\ForeignKeyConstraintAdded;
use Gobl\DBAL\Diff\Actions\ForeignKeyConstraintDeleted;
use Gobl\DBAL\Diff\Actions\PrimaryKeyConstraintAdded;
use Gobl\DBAL\Diff\Actions\PrimaryKeyConstraintDeleted;
use Gobl\DBAL\Diff\Actions\TableCharsetChanged;
use Gobl\DBAL\Diff\Actions\TableCollateChanged;
use Gobl\DBAL\Diff\Actions\TableDeleted;
use Gobl\DBAL\Diff\Actions\TableRenamed;
use Gobl\DBAL\Diff\Actions\UniqueConstraintAdded;
use Gobl\DBAL\Diff\Actions\UniqueConstraintDeleted;
use Gobl\DBAL\Diff\DiffAction;
use Gobl\DBAL\Diff\DiffActionType;
use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Filters\Filter;
use Gobl\DBAL\Filters\FilterGroup;
use Gobl\DBAL\Interfaces\QueryGeneratorInterface;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Operator;
use Gobl\DBAL\Queries\Interfaces\QBInterface;
use Gobl\DBAL\Queries\QBDelete;
use Gobl\DBAL\Queries\QBInsert;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\DBAL\Queries\QBType;
use Gobl\DBAL\Queries\QBUpdate;
use Gobl\DBAL\Table;
use Gobl\DBAL\Types\Interfaces\BaseTypeInterface;
use Gobl\DBAL\Types\TypeBigint;
use Gobl\DBAL\Types\TypeBool;
use Gobl\DBAL\Types\TypeDecimal;
use Gobl\DBAL\Types\TypeFloat;
use Gobl\DBAL\Types\TypeInt;
use Gobl\DBAL\Types\TypeString;
use Gobl\Gobl;

use const GOBL_VERSION;

/**
 * Class SQLGeneratorBase.
 */
abstract class SQLQueryGeneratorBase implements QueryGeneratorInterface
{
	/**
	 * SQLGeneratorBase constructor.
	 *
	 * @param \Gobl\DBAL\Interfaces\RDBMSInterface $db
	 * @param \Gobl\DBAL\DbConfig                  $config
	 */
	public function __construct(protected RDBMSInterface $db, protected DbConfig $config)
	{
	}

	/**
	 * Destructor.
	 */
	public function __destruct()
	{
		unset($this->db, $this->config);
	}

	/**
	 * {@inheritDoc}
	 */
	public function buildQuery(QBInterface $qb): string
	{
		$type = $qb->getType();

		return match ($type) {
			QBType::SELECT => $this->getSelectQuery(/** @var QBSelect $qb */ $qb),
			QBType::INSERT => $this->getInsertQuery(/** @var QBInsert $qb */ $qb),
			QBType::UPDATE => $this->getUpdateQuery(/** @var QBUpdate $qb */ $qb),
			QBType::DELETE => $this->getDeleteQuery(/** @var QBDelete $qb */ $qb)
		};
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function buildDiffActionQuery(DiffAction $action): string
	{
		switch ($action->getType()) {
			case DiffActionType::DB_CHARSET_CHANGED:
				/** @var \Gobl\DBAL\Diff\Actions\DBCharsetChanged $action */
				return $this->getDBCharsetChangeString($action);

			case DiffActionType::DB_COLLATE_CHANGED:
				/** @var \Gobl\DBAL\Diff\Actions\DBCollateChanged $action */
				return $this->getDBCollateChangeString($action);

			case DiffActionType::TABLE_CHARSET_CHANGED:
				/** @var \Gobl\DBAL\Diff\Actions\TableCharsetChanged $action */
				return $this->getTableCharsetChangeString($action);

			case DiffActionType::TABLE_COLLATE_CHANGED:
				/** @var \Gobl\DBAL\Diff\Actions\TableCollateChanged $action */
				return $this->getTableCollateChangeString($action);

			case DiffActionType::TABLE_RENAMED:
				/** @var \Gobl\DBAL\Diff\Actions\TableRenamed $action */
				return $this->getTableRenamedString($action);

			case DiffActionType::TABLE_ADDED:
				/** @var \Gobl\DBAL\Diff\Actions\TableAdded $action */
				return $this->getTableDefinitionString($action->getTable(), false);

			case DiffActionType::TABLE_DELETED:
				/** @var \Gobl\DBAL\Diff\Actions\TableDeleted $action */
				return $this->getTableDeletedString($action);

			case DiffActionType::COLUMN_DELETED:
				/** @var \Gobl\DBAL\Diff\Actions\ColumnDeleted $action */
				return $this->getColumnDeletedString($action);

			case DiffActionType::COLUMN_ADDED:
				/** @var \Gobl\DBAL\Diff\Actions\ColumnAdded $action */
				return $this->getColumnAddedString($action);

			case DiffActionType::COLUMN_RENAMED:
				/** @var \Gobl\DBAL\Diff\Actions\ColumnRenamed $action */
				return $this->getColumnRenamedString($action);

			case DiffActionType::COLUMN_TYPE_CHANGED:
				/** @var \Gobl\DBAL\Diff\Actions\ColumnTypeChanged $action */
				return $this->getColumnTypeChangedString($action);

			case DiffActionType::PRIMARY_KEY_CONSTRAINT_ADDED:
				/** @var \Gobl\DBAL\Diff\Actions\PrimaryKeyConstraintAdded $action */
				return $this->getPrimaryKeyConstraintAddedString($action);

			case DiffActionType::PRIMARY_KEY_CONSTRAINT_DELETED:
				/** @var \Gobl\DBAL\Diff\Actions\PrimaryKeyConstraintDeleted $action */
				return $this->getPrimaryKeyConstraintDeletedString($action);

			case DiffActionType::FOREIGN_KEY_CONSTRAINT_ADDED:
				/** @var \Gobl\DBAL\Diff\Actions\ForeignKeyConstraintAdded $action */
				return $this->getForeignKeyConstraintAddedString($action);

			case DiffActionType::FOREIGN_KEY_CONSTRAINT_DELETED:
				/** @var \Gobl\DBAL\Diff\Actions\ForeignKeyConstraintDeleted $action */
				return $this->getForeignKeyConstraintDeletedString($action);

			case DiffActionType::UNIQUE_CONSTRAINT_ADDED:
				/** @var \Gobl\DBAL\Diff\Actions\UniqueConstraintAdded $action */
				return $this->getUniqueConstraintAddedString($action);

			case DiffActionType::UNIQUE_CONSTRAINT_DELETED:
				/** @var \Gobl\DBAL\Diff\Actions\UniqueConstraintDeleted $action */
				return $this->getUniqueConstraintDeletedString($action);
		}

		throw new DBALException('Build diff action query not implemented for: ' . \get_debug_type($action));
	}

	/**
	 * {@inheritDoc}
	 */
	public function buildTotalRowCountQuery(QBSelect $qb): string
	{
		return 'SELECT COUNT(1) FROM (' . $this->buildQuery($qb) . ') as __gobl_alias';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function toExpression(FilterGroup|Filter $filter): string
	{
		if ($filter instanceof Filter) {
			return $this->filterToExpression($filter);
		}

		$filters    = $filter->getFilters(true);
		$cond       = $filter->isAnd() ? ' AND ' : ' OR ';
		$expression = null;

		foreach ($filters as $f) {
			$entry_exp = $f instanceof Filter ? $this->filterToExpression($f) : $this->toExpression($f);

			if (!empty($entry_exp)) {
				$expression = null === $expression ? $entry_exp : $expression . $cond . $entry_exp;
			}
		}

		return empty($expression) ? '' : '(' . $expression . ')';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return bool
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function hasSameColumnTypeDefinition(Column $a, Column $b): bool
	{
		return $this->getColumnDefinitionString($a) === $this->getColumnDefinitionString($b);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function buildDatabase(?string $namespace = null): string
	{
		// checks all foreign key constraints
		$tables             = $this->db->getTables($namespace);
		$create_table_parts = [];
		$alter_table_parts  = [];

		foreach ($tables as $table) {
			$create_table_parts[] = $this->getTableDefinitionString($table, false);
			$foreign_keys         = $this->getTableForeignKeysDefinitionString($table, true);
			$unique_keys          = $this->getTableUniqueConstraintsDefinitionString($table, true);

			if (!empty($foreign_keys)) {
				$alter_table_parts[] = $foreign_keys;
			}

			if (!empty($unique_keys)) {
				$alter_table_parts[] = $unique_keys;
			}
		}

		$create_sql = \implode(\PHP_EOL . \PHP_EOL, $create_table_parts);
		$alter_sql  = \implode(\PHP_EOL . \PHP_EOL, $alter_table_parts);

		$charset = $this->config->getDbCharset();

		return Gobl::runTemplate($this->dbQueryTemplate(), [
			'gobl_time'    => Gobl::getGeneratedAtDate(),
			'gobl_version' => GOBL_VERSION,
			'charset'      => $charset,
			'create_sql'   => $create_sql,
			'alter_sql'    => $alter_sql,
		]);
	}

	/**
	 * @param \Gobl\DBAL\Diff\Actions\TableRenamed $action
	 *
	 * @return string
	 */
	protected function getTableRenamedString(TableRenamed $action): string
	{
		$old_table_name = $action->getOldTable()
			->getFullName();
		$new_table_name = $action->getNewTable()
			->getFullName();

		return 'ALTER TABLE `' . $old_table_name . '` RENAME TO `' . $new_table_name . '`;';
	}

	/**
	 * @param \Gobl\DBAL\Diff\Actions\TableDeleted $action
	 *
	 * @return string
	 */
	protected function getTableDeletedString(TableDeleted $action): string
	{
		$table_name = $action->getTable()
			->getFullName();

		return 'DROP TABLE `' . $table_name . '`;';
	}

	protected function getColumnDeletedString(ColumnDeleted $action): string
	{
		$table_name  = $action->getTable()
			->getFullName();
		$column_name = $action->getColumn()
			->getFullName();

		return 'ALTER TABLE `' . $table_name . '` DROP `' . $column_name . '`;';
	}

	/**
	 * @param \Gobl\DBAL\Diff\Actions\ColumnAdded $action
	 *
	 * @return string
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	protected function getColumnAddedString(ColumnAdded $action): string
	{
		$table_name = $action->getTable()
			->getFullName();
		$sql        = $this->getColumnDefinitionString($action->getColumn());

		return 'ALTER TABLE `' . $table_name . '` ADD ' . $sql . ';';
	}

	/**
	 * @param \Gobl\DBAL\Diff\Actions\ColumnRenamed $action
	 *
	 * @return string
	 */
	protected function getColumnRenamedString(ColumnRenamed $action): string
	{
		$table_name      = $action->getTable()
			->getFullName();
		$old_column_name = $action->getOldColumn()
			->getFullName();
		$new_column_name = $action->getNewColumn()
			->getFullName();

		return 'ALTER TABLE `' . $table_name . '` RENAME COLUMN `' . $old_column_name . '` TO `' . $new_column_name . '`;';
	}

	/**
	 * @param \Gobl\DBAL\Diff\Actions\ColumnTypeChanged $action
	 *
	 * @return string
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	protected function getColumnTypeChangedString(ColumnTypeChanged $action): string
	{
		$column_definition = $this->getColumnDefinitionString($action->getNewColumn());
		$table_name        = $action->getTable()
			->getFullName();

		return 'ALTER TABLE `' . $table_name . '` ALTER COLUMN ' . $column_definition . ';';
	}

	/**
	 * @param \Gobl\DBAL\Diff\Actions\PrimaryKeyConstraintAdded $action
	 *
	 * @return string
	 */
	protected function getPrimaryKeyConstraintAddedString(PrimaryKeyConstraintAdded $action): string
	{
		return $this->getPrimaryKeySQL($action->getConstraint(), true);
	}

	/**
	 * @param \Gobl\DBAL\Diff\Actions\ForeignKeyConstraintAdded $action
	 *
	 * @return string
	 */
	protected function getForeignKeyConstraintAddedString(ForeignKeyConstraintAdded $action): string
	{
		return $this->getForeignKeySQL($action->getConstraint(), true);
	}

	/**
	 * @param \Gobl\DBAL\Diff\Actions\UniqueConstraintAdded $action
	 *
	 * @return string
	 */
	protected function getUniqueConstraintAddedString(UniqueConstraintAdded $action): string
	{
		return $this->getUniqueSQL($action->getConstraint(), true);
	}

	/**
	 * @param \Gobl\DBAL\Diff\Actions\PrimaryKeyConstraintDeleted $action
	 *
	 * @return string
	 */
	protected function getPrimaryKeyConstraintDeletedString(PrimaryKeyConstraintDeleted $action): string
	{
		$constraint      = $action->getConstraint();
		$table_name      = $constraint->getHostTable()
			->getFullName();
		$constraint_name = $constraint->getName();

		return 'ALTER TABLE `' . $table_name . '` DROP CONSTRAINT ' . $constraint_name . ';';
	}

	/**
	 * @param \Gobl\DBAL\Diff\Actions\ForeignKeyConstraintDeleted $action
	 *
	 * @return string
	 */
	protected function getForeignKeyConstraintDeletedString(ForeignKeyConstraintDeleted $action): string
	{
		$constraint      = $action->getConstraint();
		$table_name      = $constraint->getHostTable()
			->getFullName();
		$constraint_name = $constraint->getName();

		return 'ALTER TABLE `' . $table_name . '` DROP CONSTRAINT ' . $constraint_name . ';';
	}

	/**
	 * @param \Gobl\DBAL\Diff\Actions\UniqueConstraintDeleted $action
	 *
	 * @return string
	 */
	protected function getUniqueConstraintDeletedString(UniqueConstraintDeleted $action): string
	{
		$constraint      = $action->getConstraint();
		$table_name      = $constraint->getHostTable()
			->getFullName();
		$constraint_name = $constraint->getName();

		return 'ALTER TABLE `' . $table_name . '` DROP CONSTRAINT ' . $constraint_name . ';';
	}

	/**
	 * @param \Gobl\DBAL\Diff\Actions\DBCharsetChanged $action
	 *
	 * @return string
	 */
	protected function getDBCharsetChangeString(DBCharsetChanged $action): string
	{
		$db_name = $action->getDb()
			->getConfig()
			->getDbName();
		$charset = $action->getCharset();

		return 'ALTER DATABASE `' . $db_name . '` CHARACTER SET ' . $charset . ';';
	}

	/**
	 * @param \Gobl\DBAL\Diff\Actions\DBCollateChanged $action
	 *
	 * @return string
	 */
	protected function getDBCollateChangeString(DBCollateChanged $action): string
	{
		$db_name   = $action->getDb()
			->getConfig()
			->getDbName();
		$collation = $action->getCollate();

		return 'ALTER DATABASE `' . $db_name . '` COLLATE ' . $collation . ';';
	}

	/**
	 * @param \Gobl\DBAL\Diff\Actions\TableCharsetChanged $action
	 *
	 * @return string
	 */
	protected function getTableCharsetChangeString(TableCharsetChanged $action): string
	{
		$table_name = $action->getTable()
			->getFullName();
		$charset    = $action->getCharset();

		return 'ALTER TABLE `' . $table_name . '` CONVERT TO CHARACTER SET ' . $charset . ';';
	}

	/**
	 * @param \Gobl\DBAL\Diff\Actions\TableCollateChanged $action
	 *
	 * @return string
	 */
	protected function getTableCollateChangeString(TableCollateChanged $action): string
	{
		$table_name = $action->getTable()
			->getFullName();
		$collation  = $action->getCollate();

		return 'ALTER TABLE `' . $table_name . '` DEFAULT COLLATE ' . $collation . ';';
	}

	/**
	 * Gets column definition query string.
	 *
	 * @param \Gobl\DBAL\Column     $column
	 * @param null|\Gobl\DBAL\Table $table_for_alter
	 *
	 * @return string
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	protected function getColumnDefinitionString(Column $column, ?Table $table_for_alter = null): string
	{
		$base_type_name = $column->getType()
			->getBaseType()
			->getName();

		$sql = match ($base_type_name) {
			TypeString::NAME  => $this->getStringColumnDefinition($column),
			TypeBool::NAME    => $this->getBoolColumnDefinition($column),
			TypeInt::NAME     => $this->getIntColumnDefinition($column),
			TypeBigint::NAME  => $this->getBigintColumnDefinition($column),
			TypeFloat::NAME   => $this->getFloatColumnDefinition($column),
			TypeDecimal::NAME => $this->getDecimalColumnDefinition($column),
			default           => throw new DBALException(\sprintf(
				'Unknown base type "%s" for column "%s".',
				$base_type_name,
				$column->getName()
			)),
		};

		if ($table_for_alter) {
			return 'ALTER TABLE `' . $table_for_alter->getFullName() . '` ADD ' . $sql . ';';
		}

		return $sql;
	}

	/**
	 * Gets table definition query string.
	 *
	 * @param \Gobl\DBAL\Table $table
	 * @param bool             $include_fq_or_uq_alter
	 *
	 * @return string
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	protected function getTableDefinitionString(Table $table, bool $include_fq_or_uq_alter): string
	{
		$columns = $table->getColumns();
		$sql     = [];

		foreach ($columns as $column) {
			$sql[] = $this->getColumnDefinitionString($column);
		}

		if ($table->hasPrimaryKeyConstraint()) {
			$sql[] = $this->getTablePrimaryKeysDefinitionString($table, false);
		}

		$table_name = $table->getFullName();
		$table_body = \implode(',' . \PHP_EOL, $sql);
		$charset    = $table->getCharset() ?? $this->config->getDbCharset();
		$collate    = $table->getCollate() ?? $this->config->getDbCollate();

		$alter_table = '';

		if ($include_fq_or_uq_alter) {
			$alter_table = \PHP_EOL
						   . $this->getTableUniqueConstraintsDefinitionString($table, true)
						   . \PHP_EOL . $this->getTableForeignKeysDefinitionString($table, true);
		}

		return Gobl::runTemplate($this->createTableQueryTemplate(), [
			'table_name'  => $table_name,
			'charset'     => $charset,
			'collate'     => $collate,
			'table_body'  => $table_body,
			'alter_table' => $alter_table,
		]);
	}

	/**
	 * Gets table unique constraints definition query string.
	 *
	 * @param \Gobl\DBAL\Table $table
	 * @param bool             $alter
	 *
	 * @return string
	 */
	protected function getTableUniqueConstraintsDefinitionString(Table $table, bool $alter): string
	{
		$alter_table = [];
		$uc_list     = $table->getUniqueConstraints();

		foreach ($uc_list as /* $uc_name => */ $uc) {
			$alter_table[] = $this->getUniqueSQL($uc, $alter);
		}

		$table_name  = $table->getFullName();
		$alter_table = \implode(\PHP_EOL, $alter_table);

		if (empty($alter_table)) {
			return '';
		}

		return "
--
-- Unique constraints definition for table `{$table_name}`
--
{$alter_table}";
	}

	/**
	 * Gets table foreign keys definition query string.
	 *
	 * @param \Gobl\DBAL\Table $table
	 * @param bool             $alter
	 *
	 * @return string
	 */
	protected function getTableForeignKeysDefinitionString(Table $table, bool $alter): string
	{
		$alter_table = [];
		$fk_list     = $table->getForeignKeyConstraints();

		foreach ($fk_list as /* $fk_name => */ $fk) {
			$alter_table[] = $this->getForeignKeySQL($fk, $alter);
		}

		$table_name  = $table->getFullName();
		$alter_table = \implode(\PHP_EOL, $alter_table);

		if (empty($alter_table)) {
			return '';
		}

		return "
--
-- Foreign keys constraints definition for table `{$table_name}`
--
{$alter_table}";
	}

	/**
	 * Gets table primary keys definition query string.
	 *
	 * @param \Gobl\DBAL\Table $table
	 * @param bool             $alter
	 *
	 * @return string
	 */
	protected function getTablePrimaryKeysDefinitionString(Table $table, bool $alter): string
	{
		$pk = $table->getPrimaryKeyConstraint();

		// only one primary key per table
		if (!$pk) {
			return '';
		}

		$table_name  = $table->getFullName();
		$alter_table = $this->getPrimaryKeySQL($pk, $alter);

		return "
--
-- Primary key constraints definition for table `{$table_name}`
--
{$alter_table}";
	}

	/**
	 * Gets bool column definition query string.
	 *
	 * @param \Gobl\DBAL\Column $column
	 *
	 * @return string
	 */
	protected function getBoolColumnDefinition(Column $column): string
	{
		$column_name = $column->getFullName();
		$type        = $column->getType()
			->getBaseType();

		$sql = ["`{$column_name}` tinyint(1)"];

		self::defaultAndNullChunks($type, $sql);

		return \implode(' ', $sql);
	}

	/**
	 * Gets int column definition query string.
	 *
	 * @param \Gobl\DBAL\Column $column
	 *
	 * @return string
	 */
	protected function getIntColumnDefinition(Column $column): string
	{
		$column_name = $column->getFullName();
		$type        = $column->getType()
			->getBaseType();
		$unsigned    = $type->getOption('unsigned');
		$min         = $type->getOption('min', -\INF);
		$max         = $type->getOption('max', \INF);

		$sql = ["`{$column_name}`"];

		if ($unsigned) {
			if ($max <= 255) {
				$sql[] = 'tinyint';
			} elseif ($max <= 65535) {
				$sql[] = 'smallint';
			} else {
				$sql[] = 'int(11)';
			}

			$sql[] = 'unsigned';
		} elseif ($min >= -128 && $max <= 127) {
			$sql[] = 'tinyint';
		} elseif ($min >= -32768 && $max <= 32767) {
			$sql[] = 'smallint';
		} else {
			$sql[] = 'integer(11)';
		}

		self::defaultAndNullChunks($type, $sql);

		if ($type->isAutoIncremented()) {
			$sql[] = 'AUTO_INCREMENT';
		}

		return \implode(' ', $sql);
	}

	/**
	 * Gets bigint column definition query string.
	 *
	 * @param \Gobl\DBAL\Column $column
	 *
	 * @return string
	 */
	protected function getBigintColumnDefinition(Column $column): string
	{
		$column_name = $column->getFullName();
		$type        = $column->getType()
			->getBaseType();
		$unsigned    = $type->getOption('unsigned');

		$sql = ["`{$column_name}` bigint(20)"];

		if ($unsigned) {
			$sql[] = 'unsigned';
		}

		self::defaultAndNullChunks($type, $sql);

		if ($type->isAutoIncremented()) {
			$sql[] = 'AUTO_INCREMENT';
		}

		return \implode(' ', $sql);
	}

	/**
	 * Checks float column.
	 *
	 * @param \Gobl\DBAL\Column $column
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	protected function checkFloatColumn(Column $column): void
	{
		$mantissa = $column->getType()
			->getOption('mantissa');

		if (null !== $mantissa) {
			$min = 0;
			$max = 53;

			if ($min > $mantissa || $mantissa > $max) {
				throw new DBALException(\sprintf(
					'[%s] Column %s with float type should have a "mantissa" between %s and %s.',
					$this->db->getType(),
					$column->getFullName(),
					$min,
					$max,
				));
			}
		}
	}

	/**
	 * Gets float column definition query string.
	 *
	 * @param \Gobl\DBAL\Column $column
	 *
	 * @return string
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	protected function getFloatColumnDefinition(Column $column): string
	{
		$this->checkFloatColumn($column);

		$column_name = $column->getFullName();
		$type        = $column->getType()
			->getBaseType();

		$unsigned = $type->getOption('unsigned');

		$mantissa = $column->getType()
			->getOption('mantissa');
		$sql      = [];

		if (null !== $mantissa) {
			$sql[] = "`{$column_name}` float({$mantissa})";
		} else {
			$sql[] = "`{$column_name}` float";
		}

		if ($unsigned) {
			$sql[] = 'unsigned';
		}

		self::defaultAndNullChunks($type, $sql);

		return \implode(' ', $sql);
	}

	/**
	 * Checks decimal column.
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	protected function checkDecimalColumn(Column $column): void
	{
		$type      = $column->getType();
		$precision = $type->getOption('precision');
		$scale     = $type->getOption('scale');
		$min       = 1;
		$max       = 38;

		if (null !== $precision) {
			if ($min > $precision || $precision > $max) {
				throw new DBALException(\sprintf(
					'[%s] Column %s with decimal type should have a "precision" between %s and %s.',
					$this->db->getType(),
					$column->getFullName(),
					$min,
					$max,
				));
			}

			if (null !== $scale) {
				$max = $precision;

				if ($min > $scale || $scale > $max) {
					throw new DBALException(\sprintf(
						'[%s] Column %s with decimal type should have a "scale" between %s and %s.',
						$this->db->getType(),
						$column->getFullName(),
						$min,
						$max,
					));
				}
			}
		}
	}

	/**
	 * Gets decimal column definition query string.
	 *
	 * @param \Gobl\DBAL\Column $column
	 *
	 * @return string
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	protected function getDecimalColumnDefinition(Column $column): string
	{
		$this->checkDecimalColumn($column);

		$column_name = $column->getFullName();
		$type        = $column->getType()
			->getBaseType();
		$unsigned    = $type->getOption('unsigned');
		$precision   = $type->getOption('precision');
		$scale       = $type->getOption('scale');
		$sql         = [];

		if (null !== $precision) {
			if (null !== $scale) {
				$sql[] = "`{$column_name}` decimal({$precision}, {$scale})";
			} else {
				$sql[] = "`{$column_name}` decimal({$precision})";
			}
		} else {
			$sql[] = "`{$column_name}` decimal";
		}

		if ($unsigned) {
			$sql[] = 'unsigned';
		}

		self::defaultAndNullChunks($type, $sql);

		return \implode(' ', $sql);
	}

	/**
	 * Gets string column definition query string.
	 *
	 * @param \Gobl\DBAL\Column $column
	 *
	 * @return string
	 */
	protected function getStringColumnDefinition(Column $column): string
	{
		$column_name      = $column->getFullName();
		$type             = $column->getType()
			->getBaseType();
		$null             = $type->isNullAble();
		$default          = $type->getDefault();
		$force_no_default = false;
		$min              = $type->getOption('min', 0);
		$max              = $type->getOption('max', \INF);
		// char(c) c in range(0,255);
		// varchar(c) c in range(0,65535);
		$c   = $max;
		$sql = ["`{$column_name}`"];

		if ($c <= 255 && $min === $max) {
			$sql[] = "char({$c})";
		} elseif ($c <= 65535) {
			$sql[] = "varchar({$c})";
		} else {
			$sql[]            = 'text';
			$force_no_default = true;
		}

		if (!$null) {
			$sql[] = 'NOT NULL';

			if (!$force_no_default && null !== $default) {
				$sql[] = \sprintf('DEFAULT %s', static::singleQuote((string) $default));
			}
		} else {
			$sql[] = 'NULL';

			if (!$force_no_default) {
				if (null === $default) {
					$sql[] = 'DEFAULT NULL';
				} else {
					$sql[] = \sprintf('DEFAULT %s', static::singleQuote((string) $default));
				}
			}
		}

		return \implode(' ', $sql);
	}

	/**
	 * Gets unique constraint definition query string.
	 *
	 * @param \Gobl\DBAL\Constraints\Unique $uc    the unique constraint
	 * @param bool                          $alter use alter syntax
	 *
	 * @return string
	 */
	protected function getUniqueSQL(Unique $uc, bool $alter): string
	{
		$host_table_name = $uc->getHostTable()
			->getFullName();
		$columns_list    = static::quoteCols($uc->getColumns());
		$sql             = $alter ? 'ALTER TABLE `' . $host_table_name . '` ADD ' : '';
		$sql .= 'CONSTRAINT ' . $uc->getName() . ' UNIQUE (' . $columns_list . ')' . ($alter ? ';' : '');

		return $sql;
	}

	/**
	 * Gets primary key constraint definition query string.
	 *
	 * @param \Gobl\DBAL\Constraints\PrimaryKey $pk    the primary key constraint
	 * @param bool                              $alter use alter syntax
	 *
	 * @return string
	 */
	protected function getPrimaryKeySQL(PrimaryKey $pk, bool $alter): string
	{
		$columns_list    = static::quoteCols($pk->getColumns());
		$host_table_name = $pk->getHostTable()
			->getFullName();
		$sql             = $alter ? 'ALTER TABLE `' . $host_table_name . '` ADD ' : '';
		$sql .= 'CONSTRAINT ' . $pk->getName() . ' PRIMARY KEY (' . $columns_list . ')' . ($alter ? ';' : '');

		return $sql;
	}

	/**
	 * Gets foreign key constraint definition query string.
	 *
	 * @param \Gobl\DBAL\Constraints\ForeignKey $fk    the foreign key constraint
	 * @param bool                              $alter use alter syntax
	 *
	 * @return string
	 */
	protected function getForeignKeySQL(ForeignKey $fk, bool $alter): string
	{
		$host_table_name   = $fk->getHostTable()
			->getFullName();
		$ref_table         = $fk->getReferenceTable();
		$update_action     = $fk->getUpdateAction();
		$delete_action     = $fk->getDeleteAction();
		$host_columns      = static::quoteCols($fk->getHostColumns());
		$reference_columns = static::quoteCols($fk->getReferenceColumns());
		$sql               = $alter ? 'ALTER TABLE `' . $host_table_name . '` ADD ' : '';
		$sql .= 'CONSTRAINT ' . $fk->getName() . ' FOREIGN KEY (' . $host_columns
							  . ') REFERENCES ' . $ref_table->getFullName() . ' (' . $reference_columns . ')';

		$sql .= ' ON UPDATE ';

		if (ForeignKeyAction::SET_NULL === $update_action) {
			$sql .= 'SET NULL';
		} elseif (ForeignKeyAction::CASCADE === $update_action) {
			$sql .= 'CASCADE';
		} elseif (ForeignKeyAction::RESTRICT === $update_action) {
			$sql .= 'RESTRICT';
		} else {
			$sql .= 'NO ACTION';
		}

		$sql .= ' ON DELETE ';

		if (ForeignKeyAction::SET_NULL === $delete_action) {
			$sql .= 'SET NULL';
		} elseif (ForeignKeyAction::CASCADE === $delete_action) {
			$sql .= 'CASCADE';
		} elseif (ForeignKeyAction::RESTRICT === $delete_action) {
			$sql .= 'RESTRICT';
		} else {
			$sql .= 'NO ACTION';
		}

		$sql .= ($alter ? ';' : '');

		return $sql;
	}

	/**
	 * Returns sql SELECT query.
	 *
	 * @param \Gobl\DBAL\Queries\QBSelect $qb
	 *
	 * @return string
	 */
	protected function getSelectQuery(QBSelect $qb): string
	{
		$columns  = $this->getSelectedColumnsQuery($qb);
		$where    = $this->getWhereQuery($qb);
		$from     = $this->getFromQuery($qb);
		$group_by = $this->getGroupByQuery($qb);
		$having   = $this->getHavingQuery($qb);
		$order_by = $this->getOrderByQuery($qb);
		$limit    = $this->getLimitQuery($qb);

		return 'SELECT ' . $columns . ' FROM ' . $from . ' WHERE ' . $where
			   . $group_by . $having . $order_by . $limit;
	}

	/**
	 * Returns sql HAVING query part.
	 *
	 * @param \Gobl\DBAL\Queries\QBSelect $qb
	 *
	 * @return string
	 */
	protected function getHavingQuery(QBSelect $qb): string
	{
		$having = (string) ($qb->getOptionsHaving() ?? '');

		if (!empty($having)) {
			return ' HAVING ' . $having;
		}

		return '';
	}

	/**
	 * Returns sql LIMIT query part.
	 *
	 * @param QBDelete|QBSelect|QBUpdate $qb
	 *
	 * @return string
	 */
	protected function getLimitQuery(QBSelect|QBUpdate|QBDelete $qb): string
	{
		$offset = $qb->getOptionsLimitOffset();
		$max    = $qb->getOptionsLimitMax();
		$sql    = '';

		if (\is_int($max)) {
			$sql = ' LIMIT ' . $max;

			if (\is_int($offset)) {
				$sql .= ' OFFSET ' . $offset;
			}
		}

		return $sql;
	}

	/**
	 * Returns sql UPDATE query.
	 *
	 * @param \Gobl\DBAL\Queries\QBUpdate $qb
	 *
	 * @return string
	 */
	protected function getUpdateQuery(QBUpdate $qb): string
	{
		$x = [];

		foreach ($qb->getOptionsColumns() as $column => $key_bind_name) {
			$x[] = $column . ' = ' . $key_bind_name;
		}

		$set   = \implode(' , ', $x);
		$where = $this->getWhereQuery($qb);
		$table = $qb->getOptionsTable();
		$alias = $qb->getOptionsUpdateTableAlias() ?? '';

		if (empty($table)) {
			throw new DBALRuntimeException('Table name required for update query.');
		}

		return 'UPDATE ' . $table . ' ' . $alias . ' SET ' . $set . ' WHERE ' . $where;
	}

	/**
	 * Returns sql DELETE query.
	 *
	 * @param \Gobl\DBAL\Queries\QBDelete $qb
	 *
	 * @return string
	 */
	protected function getDeleteQuery(QBDelete $qb): string
	{
		$where = $this->getWhereQuery($qb);
		$from  = $this->getFromQuery($qb);

		$x = [];

		foreach ($qb->getOptionsFrom() as /* $table_name => */ $alias) {
			if (null !== $alias) {
				$x[] = $alias;
			}
		}

		$delete_alias = \implode(' , ', $x);

		return 'DELETE ' . $delete_alias . ' FROM ' . $from . ' WHERE ' . $where;
	}

	/**
	 * Returns sql INSERT query.
	 *
	 * @param \Gobl\DBAL\Queries\QBInsert $qb
	 *
	 * @return string
	 */
	protected function getInsertQuery(QBInsert $qb): string
	{
		$cols    = $qb->getOptionsColumns();
		$columns = \implode(' , ', \array_keys($cols));
		$values  = \implode(' , ', $cols);
		$table   = $qb->getOptionsTable();

		if (empty($table)) {
			throw new DBALRuntimeException('Table name required for insert query.');
		}

		return 'INSERT INTO ' . $table . ' (' . $columns . ') VALUES(' . $values . ')';
	}

	/**
	 * Returns sql WHERE query part.
	 *
	 * @param \Gobl\DBAL\Queries\QBDelete|\Gobl\DBAL\Queries\QBSelect|\Gobl\DBAL\Queries\QBUpdate $qb
	 *
	 * @return string
	 */
	protected function getWhereQuery(QBSelect|QBDelete|QBUpdate $qb): string
	{
		$rule = $qb->getOptionsWhere();

		if (null !== $rule) {
			$rule = (string) $rule;
		}

		return empty($rule) ? '1 = 1' : $rule;
	}

	/**
	 * Returns sql selected columns.
	 *
	 * @param \Gobl\DBAL\Queries\QBSelect $qb
	 *
	 * @return string
	 */
	protected function getSelectedColumnsQuery(QBSelect $qb): string
	{
		$columns = \array_unique($qb->getOptionsSelect());

		if (!empty($columns)) {
			return \implode(' , ', $columns);
		}

		return '*';
	}

	/**
	 * Returns sql FROM query part.
	 *
	 * @param \Gobl\DBAL\Queries\QBDelete|\Gobl\DBAL\Queries\QBSelect $qb
	 *
	 * @return string
	 */
	protected function getFromQuery(QBSelect|QBDelete $qb): string
	{
		$from = $qb->getOptionsFrom();
		$x    = [];

		foreach ($from as $table => $alias) {
			if (null === $alias) {
				$x[] = $table . $this->getJoinQueryFor($qb, $table);
			} else {
				$x[] = $table . ' ' . $alias . ' ' . $this->getJoinQueryFor($qb, $alias);
			}
		}

		return \implode(' , ', $x);
	}

	/**
	 * Returns sql GROUP BY query part.
	 *
	 * @param \Gobl\DBAL\Queries\QBSelect $qb
	 *
	 * @return string
	 */
	protected function getGroupByQuery(QBSelect $qb): string
	{
		$group_by = $qb->getOptionsGroupBy();

		if (!empty($group_by)) {
			return ' GROUP BY ' . \implode(' , ', $group_by);
		}

		return '';
	}

	/**
	 * Returns sql ORDER BY query part.
	 *
	 * @param \Gobl\DBAL\Queries\QBSelect $qb
	 *
	 * @return string
	 */
	protected function getOrderByQuery(QBSelect $qb): string
	{
		$order_by = $qb->getOptionsOrderBy();

		if (!empty($order_by)) {
			return ' ORDER BY ' . \implode(' , ', $order_by);
		}

		return '';
	}

	/**
	 * Returns sql JOIN for a given table.
	 *
	 * @param \Gobl\DBAL\Queries\QBDelete|\Gobl\DBAL\Queries\QBSelect $qb
	 * @param string                                                  $table the table name
	 *
	 * @return string
	 */
	protected function getJoinQueryFor(QBSelect|QBDelete $qb, string $table): string
	{
		$sql   = '';
		$joins = $qb->getOptionsJoins();

		if (isset($joins[$table])) {
			$table_joins = $joins[$table];

			foreach ($table_joins as $join) {
				$type      = $join['type'];
				$condition = (string) $join['condition'];
				$sql .= ' ' . $type
							  . ' JOIN ' . $join['secondTable'] . ' ' . $join['secondTableAlias']
							  . ' ON ' . (!empty($condition) ? $condition : '1 = 1');
			}

			foreach ($table_joins as $join) {
				$sql .= $this->getJoinQueryFor($qb, $join['secondTableAlias']);
			}
		}

		return $sql;
	}

	/**
	 * Converts filter to sql expression.
	 *
	 * @param \Gobl\DBAL\Filters\Filter $filter
	 *
	 * @return string
	 */
	protected function filterToExpression(Filter $filter): string
	{
		$left   = $filter->getLeftOperand();
		$right  = $filter->getRightOperand();
		$op     = $filter->getOperator();
		$sql_op = match ($op) {
			Operator::EQ          => '=',
			Operator::NEQ         => '<>',
			Operator::LT          => '<',
			Operator::LTE         => '<=',
			Operator::GT          => '>',
			Operator::GTE         => '>=',
			Operator::LIKE        => 'LIKE',
			Operator::NOT_LIKE    => 'NOT LIKE',
			Operator::IS_NULL     => 'IS NULL',
			Operator::IS_NOT_NULL => 'IS NOT NULL',
			Operator::IN          => 'IN',
			Operator::NOT_IN      => 'NOT IN',
			Operator::IS_TRUE     => '= 1',
			Operator::IS_FALSE    => '= 0',
		};

		return $left . ' ' . $sql_op . ($op->isUnary() ? '' : ' ' . ($right ?? 'NULL'));
	}

	/**
	 * Should return the db query template.
	 *
	 * @return string
	 */
	abstract protected function dbQueryTemplate(): string;

	/**
	 * Should return the create table query template.
	 *
	 * @return string
	 */
	abstract protected function createTableQueryTemplate(): string;

	/**
	 * Add default and null parts to sql.
	 *
	 * @param \Gobl\DBAL\Types\Interfaces\BaseTypeInterface $type
	 * @param array                                         &$sql_parts
	 */
	protected static function defaultAndNullChunks(BaseTypeInterface $type, array &$sql_parts): void
	{
		$null    = $type->isNullAble();
		$default = $type->getDefault();

		if (!$null) {
			$sql_parts[] = 'NOT NULL';

			if (null !== $default) {
				$sql_parts[] = \sprintf('DEFAULT %s', static::singleQuote((string) $default));
			}
		} else {
			$sql_parts[] = 'NULL';

			if (null === $default) {
				$sql_parts[] = 'DEFAULT NULL';
			} else {
				$sql_parts[] = \sprintf('DEFAULT %s', static::singleQuote((string) $default));
			}
		}
	}

	/**
	 * Quote columns name in a given list.
	 *
	 * @param array $list
	 *
	 * @return string
	 */
	protected static function quoteCols(array $list): string
	{
		return '`' . \implode('` , `', $list) . '`';
	}

	/**
	 * Wrap string, int... in single quote.
	 *
	 * @param string $value
	 *
	 * @return string
	 */
	protected static function singleQuote(string $value): string
	{
		return "'" . \str_replace("'", "''", $value) . "'";
	}
}
