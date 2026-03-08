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
use Gobl\DBAL\Constraints\UniqueKey;
use Gobl\DBAL\DbConfig;
use Gobl\DBAL\Diff\Actions\ColumnAdded;
use Gobl\DBAL\Diff\Actions\ColumnDeleted;
use Gobl\DBAL\Diff\Actions\ColumnRenamed;
use Gobl\DBAL\Diff\Actions\ColumnTypeChanged;
use Gobl\DBAL\Diff\Actions\DBCharsetChanged;
use Gobl\DBAL\Diff\Actions\DBCollateChanged;
use Gobl\DBAL\Diff\Actions\ForeignKeyConstraintAdded;
use Gobl\DBAL\Diff\Actions\ForeignKeyConstraintDeleted;
use Gobl\DBAL\Diff\Actions\IndexAdded;
use Gobl\DBAL\Diff\Actions\IndexDeleted;
use Gobl\DBAL\Diff\Actions\PrimaryKeyConstraintAdded;
use Gobl\DBAL\Diff\Actions\PrimaryKeyConstraintDeleted;
use Gobl\DBAL\Diff\Actions\TableAdded;
use Gobl\DBAL\Diff\Actions\TableCharsetChanged;
use Gobl\DBAL\Diff\Actions\TableCollateChanged;
use Gobl\DBAL\Diff\Actions\TableDeleted;
use Gobl\DBAL\Diff\Actions\TableRenamed;
use Gobl\DBAL\Diff\Actions\UniqueKeyConstraintAdded;
use Gobl\DBAL\Diff\Actions\UniqueKeyConstraintDeleted;
use Gobl\DBAL\Diff\DiffAction;
use Gobl\DBAL\Diff\DiffActionType;
use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Filters\Filter;
use Gobl\DBAL\Filters\FilterGroup;
use Gobl\DBAL\Filters\FilterRaw;
use Gobl\DBAL\Filters\Filters;
use Gobl\DBAL\Filters\Interfaces\FilterInterface;
use Gobl\DBAL\Indexes\Index;
use Gobl\DBAL\Interfaces\QueryGeneratorInterface;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Operator;
use Gobl\DBAL\Queries\Interfaces\QBInterface;
use Gobl\DBAL\Queries\QBDelete;
use Gobl\DBAL\Queries\QBInsert;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\DBAL\Queries\QBType;
use Gobl\DBAL\Queries\QBUpdate;
use Gobl\DBAL\Queries\QBUtils;
use Gobl\DBAL\Table;
use Gobl\DBAL\Types\TypeBigint;
use Gobl\DBAL\Types\TypeBool;
use Gobl\DBAL\Types\TypeDecimal;
use Gobl\DBAL\Types\TypeFloat;
use Gobl\DBAL\Types\TypeInt;
use Gobl\DBAL\Types\TypeJSON;
use Gobl\DBAL\Types\TypeString;
use Gobl\DBAL\Types\Utils\JsonPath;
use Gobl\Gobl;
use PHPUtils\Str;

use const GOBL_VERSION;

/**
 * Class SQLGeneratorBase.
 */
abstract class SQLQueryGeneratorBase implements QueryGeneratorInterface
{
	protected int $decimal_precision_min = 1;
	protected int $decimal_precision_max = 38;
	protected int $decimal_scale_min     = 0;
	protected int $decimal_scale_max     = 38;

	/**
	 * SQLGeneratorBase constructor.
	 *
	 * @param RDBMSInterface $db
	 * @param DbConfig       $config
	 */
	public function __construct(protected RDBMSInterface $db, protected DbConfig $config) {}

	/**
	 * Destructor.
	 */
	public function __destruct()
	{
		unset($this->db, $this->config);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws DBALException
	 */
	public function buildDiffActionQuery(DiffAction $action): string
	{
		switch ($action->getType()) {
			case DiffActionType::DB_CHARSET_CHANGED:
				/** @var DBCharsetChanged $action */
				$query = $this->getDBCharsetChangeString($action);

				break;

			case DiffActionType::DB_COLLATE_CHANGED:
				/** @var DBCollateChanged $action */
				$query = $this->getDBCollateChangeString($action);

				break;

			case DiffActionType::TABLE_CHARSET_CHANGED:
				/** @var TableCharsetChanged $action */
				$query = $this->getTableCharsetChangeString($action);

				break;

			case DiffActionType::TABLE_COLLATE_CHANGED:
				/** @var TableCollateChanged $action */
				$query = $this->getTableCollateChangeString($action);

				break;

			case DiffActionType::TABLE_RENAMED:
				/** @var TableRenamed $action */
				$query = $this->getTableRenamedString($action);

				break;

			case DiffActionType::TABLE_ADDED:
				/** @var TableAdded $action */
				$query = $this->getTableDefinitionString($action->getTable(), false);

				break;

			case DiffActionType::TABLE_DELETED:
				/** @var TableDeleted $action */
				$query = $this->getTableDeletedString($action);

				break;

			case DiffActionType::COLUMN_DELETED:
				/** @var ColumnDeleted $action */
				$query = $this->getColumnDeletedString($action);

				break;

			case DiffActionType::COLUMN_ADDED:
				/** @var ColumnAdded $action */
				$query = $this->getColumnAddedString($action);

				break;

			case DiffActionType::COLUMN_RENAMED:
				/** @var ColumnRenamed $action */
				$query = $this->getColumnRenamedString($action);

				break;

			case DiffActionType::COLUMN_TYPE_CHANGED:
				/** @var ColumnTypeChanged $action */
				$query = $this->getColumnTypeChangedString($action);

				break;

			case DiffActionType::PRIMARY_KEY_CONSTRAINT_ADDED:
				/** @var PrimaryKeyConstraintAdded $action */
				$query = $this->getPrimaryKeyConstraintAddedString($action);

				break;

			case DiffActionType::PRIMARY_KEY_CONSTRAINT_DELETED:
				/** @var PrimaryKeyConstraintDeleted $action */
				$query = $this->getPrimaryKeyConstraintDeletedString($action);

				break;

			case DiffActionType::FOREIGN_KEY_CONSTRAINT_ADDED:
				/** @var ForeignKeyConstraintAdded $action */
				$query = $this->getForeignKeyConstraintAddedString($action);

				break;

			case DiffActionType::FOREIGN_KEY_CONSTRAINT_DELETED:
				/** @var ForeignKeyConstraintDeleted $action */
				$query = $this->getForeignKeyConstraintDeletedString($action);

				break;

			case DiffActionType::UNIQUE_KEY_CONSTRAINT_ADDED:
				/** @var UniqueKeyConstraintAdded $action */
				$query = $this->getUniqueKeyConstraintAddedString($action);

				break;

			case DiffActionType::UNIQUE_KEY_CONSTRAINT_DELETED:
				/** @var UniqueKeyConstraintDeleted $action */
				$query = $this->getUniqueKeyConstraintDeletedString($action);

				break;

			case DiffActionType::INDEX_ADDED:
				/** @var IndexAdded $action */
				$query = $this->getIndexAddedString($action);

				break;

			case DiffActionType::INDEX_DELETED:
				/** @var IndexDeleted $action */
				$query = $this->getIndexDeletedString($action);

				break;

			default:
				throw new DBALException('Build diff action query not implemented for: ' . \get_debug_type($action));
		}

		$reason = $action->getReason();

		if (!empty($reason)) {
			$query = $this->getCommentQuery($reason) . \PHP_EOL . $query;
		}

		return $query;
	}

	public function buildTotalRowCountQuery(QBSelect $qb): string
	{
		return 'SELECT COUNT(1) FROM (' . $this->buildQuery($qb) . ') AS ' . QBUtils::newAlias();
	}

	public function buildQuery(QBInterface $qb): string
	{
		$type = $qb->getType();

		return match ($type) {
			QBType::SELECT => $this->getSelectQuery(
				/** @var QBSelect $qb */
				$qb
			),
			QBType::INSERT => $this->getInsertQuery(
				/** @var QBInsert $qb */
				$qb
			),
			QBType::UPDATE => $this->getUpdateQuery(
				/** @var QBUpdate $qb */
				$qb
			),
			QBType::DELETE => $this->getDeleteQuery(
				/** @var QBDelete $qb */
				$qb
			)
		};
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function filterToExpression(FilterInterface|Filters $filter): string
	{
		if ($filter instanceof Filters) {
			return (string) $filter;
		}

		if ($filter instanceof Filter) {
			return $this->operatorFilterToExpression($filter);
		}

		if ($filter instanceof FilterRaw) {
			return (string) $filter;
		}

		if ($filter instanceof FilterGroup) {
			$filters    = $filter->getFilters(true);
			$cond       = $filter->isAnd() ? ' AND ' : ' OR ';
			$expression = null;

			foreach ($filters as $f) {
				$entry_exp = $this->filterToExpression($f);

				if (!empty($entry_exp)) {
					$expression = null === $expression ? $entry_exp : $expression . $cond . $entry_exp;
				}
			}

			return empty($expression) ? '' : '(' . $expression . ')';
		}

		throw new DBALRuntimeException('Filter type not supported: ' . \get_debug_type($filter));
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return bool
	 *
	 * @throws DBALException
	 */
	public function hasSameColumnTypeDefinition(Column $a, Column $b): bool
	{
		return $this->getColumnDefinitionString($a) === $this->getColumnDefinitionString($b);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws DBALException
	 */
	public function buildDatabase(?string $namespace = null): string
	{
		// checks all foreign key constraints
		$tables             = $this->db->getTables($namespace);
		$create_table_parts = [];
		$alter_table_parts  = [];
		$create_index_parts = [];

		foreach ($tables as $table) {
			$create_table_parts[] = $this->getTableDefinitionString($table, false);
			$foreign_keys         = $this->getTableForeignKeysDefinitionString($table, true);
			$unique_keys          = $this->getTableUniqueKeyConstraintsDefinitionString($table, true);
			$indexes              = $this->getTableIndexesDefinitionString($table);

			if (!empty($foreign_keys)) {
				$alter_table_parts[] = $foreign_keys;
			}

			if (!empty($unique_keys)) {
				$alter_table_parts[] = $unique_keys;
			}

			if (!empty($indexes)) {
				$create_index_parts[] = $indexes;
			}
		}

		$create_sql = \implode(\PHP_EOL . \PHP_EOL, $create_table_parts);
		$alter_sql  = \implode(\PHP_EOL . \PHP_EOL, $alter_table_parts);
		$index_sql  = \implode(\PHP_EOL . \PHP_EOL, $create_index_parts);

		$sql_query = $create_sql . \PHP_EOL . $alter_sql;

		if (!empty($index_sql)) {
			$sql_query .= \PHP_EOL . $index_sql;
		}

		return Gobl::runTemplate($this->dbQueryTemplate(), [
			'gobl_time'    => Gobl::getGeneratedAtDate(),
			'gobl_version' => GOBL_VERSION,
			'db_sql_query' => $this->wrapDatabaseDefinitionQuery($sql_query),
		]);
	}

	public function getJsonPathExtractionExpression(JsonPath $json_path): string
	{
		$col_fqn  =  $this->getJsonPathColumnFQN($json_path);
		$dot_path =  $this->getJsonPathSegmentsAsString($json_path);

		return 'JSON_UNQUOTE(JSON_EXTRACT(' . $col_fqn . ', ' . $this->quoteLiteral($dot_path) . '))';
	}

	/**
	 * {@inheritDoc}
	 *
	 * Default implementation: MySQL-compatible `JSON_CONTAINS(left, right)`.
	 */
	public function getJsonContainsExpression(string $left, string $right): string
	{
		return 'JSON_CONTAINS(' . $left . ', ' . $right . ')';
	}

	/**
	 * {@inheritDoc}
	 *
	 * Default implementation: MySQL-compatible `JSON_CONTAINS_PATH(col, 'one', CONCAT('$.', key))`.
	 */
	public function getJsonHasKeyExpression(string $col_sql_expression, string $key_expression): string
	{
		return 'JSON_CONTAINS_PATH(' . $col_sql_expression . ', \'one\', CONCAT(\'$.\',' . $key_expression . '))';
	}

	/**
	 * Quote a database identifier (table name, column name, etc.).
	 *
	 * @param string $name
	 *
	 * @return string
	 */
	public function quoteIdentifier(string $name): string
	{
		return '`' . $name . '`';
	}

	/**
	 * {@inheritDoc}
	 *
	 * Uses standard SQL `''` doubling to escape single-quotes — correct for
	 * MySQL (`NO_BACKSLASH_ESCAPES`-safe), PostgreSQL, and SQLite.
	 * No database connection is required.
	 */
	public function quoteLiteral(string $value): string
	{
		return "'" . \str_replace("'", "''", $value) . "'";
	}

	/**
	 * Converts JSON path segments into a properly escaped JSON path string for use in SQL expressions.
	 */
	protected function getJsonPathSegmentsAsString(JsonPath $json_path): string
	{
		$path_segments = $json_path->getPathSegments();

		// Segments that are valid unquoted identifiers (`[a-zA-Z_][a-zA-Z0-9_]*`)
		// are used as-is.  All other segments are wrapped in double-quotes with
		// internal `"` escaped as `\"` and `\` escaped as `\\` (MySQL JSON path
		// syntax inside the path expression string).

		$parts = \array_map(static function (string $seg): string {
			// Safe identifier: starts with letter or underscore, rest alphanumeric/underscore
			if (\preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $seg)) {
				return $seg;
			}

			// Double-quote the segment; escape backslash and double-quote per JSON path spec
			$escaped = \str_replace(['\\', '"'], ['\\\\', '\"'], $seg);

			return '"' . $escaped . '"';
		}, $path_segments);

		return '$.' . \implode('.', $parts);
	}

	/**
	 * Get the fully qualified column reference or JSON path expression for a filter operand.
	 */
	protected function getJsonPathColumnFQN(JsonPath $json_path): string
	{
		$col_name = $json_path->getColumnName();
		$table    = $json_path->getTableOrAlias();

		if (null === $table) {
			// No table or alias specified, return just the column name
			return $this->quoteIdentifier($col_name);
		}

		return $this->quoteIdentifier($table) . '.' . $this->quoteIdentifier($col_name);
	}

	/**
	 * @param DBCharsetChanged $action
	 *
	 * @return string
	 */
	protected function getDBCharsetChangeString(DBCharsetChanged $action): string
	{
		$db_name = $action->getDb()
			->getConfig()
			->getDbName();
		$charset = $action->getCharset();

		return 'ALTER DATABASE ' . $this->quoteIdentifier($db_name) . ' CHARACTER SET ' . $charset . ';';
	}

	/**
	 * @param DBCollateChanged $action
	 *
	 * @return string
	 */
	protected function getDBCollateChangeString(DBCollateChanged $action): string
	{
		$db_name   = $action->getDb()
			->getConfig()
			->getDbName();
		$collation = $action->getCollate();

		return 'ALTER DATABASE ' . $this->quoteIdentifier($db_name) . ' COLLATE ' . $collation . ';';
	}

	/**
	 * @param TableCharsetChanged $action
	 *
	 * @return string
	 */
	protected function getTableCharsetChangeString(TableCharsetChanged $action): string
	{
		$table_name = $action->getTable()
			->getFullName();
		$charset    = $action->getCharset();

		return 'ALTER TABLE ' . $this->quoteIdentifier($table_name) . ' CONVERT TO CHARACTER SET ' . $charset . ';';
	}

	/**
	 * @param TableCollateChanged $action
	 *
	 * @return string
	 */
	protected function getTableCollateChangeString(TableCollateChanged $action): string
	{
		$table_name = $action->getTable()
			->getFullName();
		$collation  = $action->getCollate();

		return 'ALTER TABLE ' . $this->quoteIdentifier($table_name) . ' DEFAULT COLLATE ' . $collation . ';';
	}

	/**
	 * @param TableRenamed $action
	 *
	 * @return string
	 */
	protected function getTableRenamedString(TableRenamed $action): string
	{
		$old_table_name = $action->getOldTable()
			->getFullName();
		$new_table_name = $action->getNewTable()
			->getFullName();

		return 'ALTER TABLE ' . $this->quoteIdentifier($old_table_name) . ' RENAME TO ' . $this->quoteIdentifier($new_table_name) . ';';
	}

	/**
	 * Gets the `CREATE TABLE` body SQL string.
	 *
	 * Generates column definitions using {@see SQLQueryGeneratorBase::getColumnDefinitionString()}, appends the primary-key
	 * definition when it has not already been inlined (e.g. SQLite single-column auto-increment),
	 * then passes the assembled parts through the {@see SQLQueryGeneratorBase::createTableQueryTemplate()} template.
	 *
	 * When `$include_fq_or_uq_alter` is `true`, `ALTER TABLE` statements for unique-key and
	 * foreign-key constraints are appended after the `CREATE TABLE` block (used by drivers that
	 * cannot declare FK/UK inline).
	 *
	 * @param Table $table
	 * @param bool  $include_fq_or_uq_alter when `true`, appends deferred FK/UK ALTER TABLE statements
	 *
	 * @return string
	 *
	 * @throws DBALException
	 */
	protected function getTableDefinitionString(Table $table, bool $include_fq_or_uq_alter): string
	{
		$columns = $table->getColumns();
		$sql     = [];

		foreach ($columns as $column) {
			$sql[] = $this->getColumnDefinitionString($column);
		}

		if ($table->hasPrimaryKeyConstraint()) {
			$pk_def = $this->getTablePrimaryKeysDefinitionString($table, false);

			// When PK is defined inline in the CREATE TABLE statement,
			// e.g. SQLite single-column auto-increment PKs
			// so we only return the PK definition string if it is not empty (not already included in the column definitions).

			if (!empty($pk_def)) {
				$sql[] = $pk_def;
			}
		}

		$table_name = $table->getFullName();
		$table_body = \implode(',' . \PHP_EOL, $sql);
		$charset    = $table->getCharset() ?? $this->config->getDbCharset();
		$collate    = $table->getCollate() ?? $this->config->getDbCollate();

		$alter_table = '';

		if ($include_fq_or_uq_alter) {
			$alter_table = \PHP_EOL
				. $this->getTableUniqueKeyConstraintsDefinitionString($table, true)
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
	 * Gets the SQL fragment for a column definition.
	 *
	 * Dispatches to the appropriate type-specific method based on the column's **base type name**:
	 * `string` -> {@see SQLQueryGeneratorBase::getStringColumnDefinition()}, `json` -> {@see SQLQueryGeneratorBase::getJSONColumnDefinition()}, etc.
	 * Throws `DBALException` for unknown base types.
	 *
	 * When `$table_for_alter` is provided, wraps the result in an `ALTER TABLE ... ADD ...` statement
	 * suitable for adding a new column to an existing table.
	 *
	 * @param Column     $column
	 * @param null|Table $table_for_alter when set, wraps result as ALTER TABLE ... ADD statement
	 *
	 * @return string
	 *
	 * @throws DBALException
	 */
	protected function getColumnDefinitionString(Column $column, ?Table $table_for_alter = null): string
	{
		$base_type_name = $column->getType()
			->getBaseType()
			->getName();

		$sql = match ($base_type_name) {
			TypeString::NAME  => $this->getStringColumnDefinition($column),
			TypeJSON::NAME    => $this->getJSONColumnDefinition($column),
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
			return 'ALTER TABLE ' . $this->quoteIdentifier($table_for_alter->getFullName()) . ' ADD ' . $sql . ';';
		}

		return $sql;
	}

	/**
	 * Gets string column definition query string.
	 *
	 * @param Column $column
	 *
	 * @return string
	 */
	protected function getStringColumnDefinition(Column $column): string
	{
		$column_name      = $column->getFullName();
		$type             = $column->getType()
			->getBaseType();
		$force_no_default = false;
		$min              = $type->getOption('min', 0);
		$max              = $type->getOption('max', \INF);
		$medium           = $type->getOption('medium', false);
		$long         	   = $type->getOption('long', false);
		// for MySQL
		// char(c) c in range(0,255);
		// varchar(c) c in range(0,65535);
		// text c in range(0,65535); // 64 KB
		// mediumtext c in range(0,16777215); // 16 MB
		// longtext c in range(0,4294967295); // 4 GB

		$sql = [$this->quoteIdentifier($column_name)];

		if ($max <= 255 && $min === $max) {
			$sql[] = "char({$max})";
		} elseif ($max <= 65535) {
			$sql[] = "varchar({$max})";
		} else {
			$force_no_default = true;

			if ($medium) {
				$sql[] = 'mediumtext';
			} elseif ($long) {
				$sql[] = 'longtext';
			} else {
				$sql[] = 'text';
			}
		}

		$this->defaultAndNullChunks($column, $sql, $force_no_default);

		return \implode(' ', $sql);
	}

	/**
	 * Gets JSON column definition query string.
	 *
	 * The base implementation falls back to TEXT when native JSON is disabled or the
	 * RDBMS does not support a dedicated JSON type.  Driver subclasses that support
	 * native JSON (MySQL >= 5.7, PostgreSQL) should override this method.
	 *
	 * @param Column $column
	 *
	 * @return string
	 */
	protected function getJSONColumnDefinition(Column $column): string
	{
		// Base: always delegate to text/string regardless of native_json option.
		// Drivers that support native JSON override this method.

		$j_type = $column->getType();

		$nullable = $j_type->getBaseType()->isNullable();
		$big      = $j_type->getOption('big', false);

		$n_col = clone $column; // to allow edit

		$n_col->setType((new TypeString())->medium($big)->nullable($nullable));

		return $this->getStringColumnDefinition($n_col);
	}

	/**
	 * Appends `NULL`/`NOT NULL` and `DEFAULT` fragments to `$sql_parts`.
	 *
	 * Four-way decision tree:
	 * - `NOT NULL` column, has default -> appends `NOT NULL DEFAULT <value>`.
	 * - `NOT NULL` column, no default -> appends `NOT NULL` only.
	 * - `NULL` column, has default -> appends `NULL DEFAULT <value>`.
	 * - `NULL` column, no default -> appends `NULL DEFAULT NULL`.
	 *
	 * Default resolution order (highest priority first):
	 * 1. `Type::dbQueryDefault()` (dialect-specific SQL expression, e.g. `NOW()`).
	 * 2. `BaseType::dbQueryDefault()` (same, on the base type).
	 * 3. `BaseType::getDefault()` -> converted through `Type::phpToDb()`.
	 *
	 * When `$force_no_default` is `true` (e.g. TEXT/BLOB on MySQL), the DEFAULT clause is
	 * omitted entirely.
	 *
	 * @param Column $column
	 * @param array  &$sql_parts
	 * @param bool   $force_no_default skip DEFAULT generation (e.g. for TEXT/BLOB columns)
	 * @param bool   $quote_default    whether to quote the default value as a SQL literal
	 */
	protected function defaultAndNullChunks(
		Column $column,
		array &$sql_parts,
		bool $force_no_default = false,
		bool $quote_default = true
	): void {
		$type           = $column->getType();
		$base_type      = $type->getBaseType();
		$null           = $base_type->isNullable();
		$default_to_use = null;

		if (!$force_no_default && $type->shouldEnforceDefaultValue($this->db)) {
			if (null !== ($d = $type->dbQueryDefault($this->db))) {
				$default_to_use = $d;
				$quote_default  = false;
			} elseif (null !== ($d = $base_type->dbQueryDefault($this->db))) {
				$default_to_use = $d;
				$quote_default  = false;
			} else {
				$default_to_use = $base_type->getDefault();
			}

			if (null === $default_to_use) {
				$type_default = $type->getDefault();

				if (null !== $type_default) {
					$default_to_use = $type->phpToDb($type_default, $this->db);
				}
			}
		}

		if (!$null) {
			$sql_parts[] = 'NOT NULL';

			if (!$force_no_default && null !== $default_to_use) {
				$default_to_use = $quote_default ? $this->quoteLiteral((string) $default_to_use) : (string) $default_to_use;
				$sql_parts[]    = $this->formatDefaultChunk($column, $default_to_use);
			}
		} else {
			$sql_parts[] = 'NULL';

			if (!$force_no_default) {
				if (null === $default_to_use) {
					$sql_parts[] = 'DEFAULT NULL';
				} else {
					$default_to_use = $quote_default ? $this->quoteLiteral((string) $default_to_use) : (string) $default_to_use;
					$sql_parts[]    = $this->formatDefaultChunk($column, $default_to_use);
				}
			}
		}
	}

	/**
	 * Formats the DEFAULT clause chunk for a column definition.
	 *
	 * Override in dialect subclasses when the DEFAULT syntax differs (e.g. MySQL requires
	 * expression defaults to be wrapped in parentheses for JSON columns).
	 *
	 * @param Column $column         The column being defined
	 * @param string $quoted_default The already-quoted (or raw) default value string
	 *
	 * @return string The full `DEFAULT ...` clause fragment
	 */
	protected function formatDefaultChunk(Column $column, string $quoted_default): string
	{
		return 'DEFAULT ' . $quoted_default;
	}

	/**
	 * Gets bool column definition query string.
	 *
	 * @param Column $column
	 *
	 * @return string
	 */
	protected function getBoolColumnDefinition(Column $column): string
	{
		$column_name = $column->getFullName();

		$sql = [$this->quoteIdentifier($column_name) . ' tinyint(1)'];

		$this->defaultAndNullChunks($column, $sql);

		return \implode(' ', $sql);
	}

	/**
	 * Gets int column definition query string.
	 *
	 * @param Column $column
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

		$sql = [$this->quoteIdentifier($column_name)];

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

		$this->defaultAndNullChunks($column, $sql);

		if ($type->isAutoIncremented()) {
			$sql[] = 'AUTO_INCREMENT';
		}

		return \implode(' ', $sql);
	}

	/**
	 * Gets bigint column definition query string.
	 *
	 * @param Column $column
	 *
	 * @return string
	 */
	protected function getBigintColumnDefinition(Column $column): string
	{
		$column_name = $column->getFullName();
		$type        = $column->getType()
			->getBaseType();
		$unsigned    = $type->getOption('unsigned');

		$sql = [$this->quoteIdentifier($column_name) . ' bigint(20)'];

		if ($unsigned) {
			$sql[] = 'unsigned';
		}

		$this->defaultAndNullChunks($column, $sql);

		if ($type->isAutoIncremented()) {
			$sql[] = 'AUTO_INCREMENT';
		}

		return \implode(' ', $sql);
	}

	/**
	 * Gets float column definition query string.
	 *
	 * @param Column $column
	 *
	 * @return string
	 *
	 * @throws DBALException
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
			$sql[] = $this->quoteIdentifier($column_name) . " float({$mantissa})";
		} else {
			$sql[] = $this->quoteIdentifier($column_name) . ' float';
		}

		if ($unsigned) {
			$sql[] = 'unsigned';
		}

		$this->defaultAndNullChunks($column, $sql);

		return \implode(' ', $sql);
	}

	/**
	 * Checks float column.
	 *
	 * @param Column $column
	 *
	 * @throws DBALException
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
	 * Gets decimal column definition query string.
	 *
	 * @param Column $column
	 *
	 * @return string
	 *
	 * @throws DBALException
	 */
	protected function getDecimalColumnDefinition(Column $column): string
	{
		$type = $column->getType()
			->getBaseType();
		$this->checkDecimalColumn($column);

		$column_name = $column->getFullName();
		$unsigned    = $type->getOption('unsigned');
		$precision   = $type->getOption('precision');
		$scale       = $type->getOption('scale');
		$sql         = [];

		if (null !== $precision) {
			if (null !== $scale) {
				$sql[] = $this->quoteIdentifier($column_name) . " decimal({$precision}, {$scale})";
			} else {
				$sql[] = $this->quoteIdentifier($column_name) . " decimal({$precision})";
			}
		} else {
			$sql[] = $this->quoteIdentifier($column_name) . ' decimal';
		}

		if ($unsigned) {
			$sql[] = 'unsigned';
		}

		$this->defaultAndNullChunks($column, $sql);

		return \implode(' ', $sql);
	}

	/**
	 * Checks decimal column.
	 *
	 * @throws DBALException
	 */
	protected function checkDecimalColumn(Column $column): void
	{
		$base_type = $column->getType()
			->getBaseType();
		$precision = $base_type->getOption('precision');
		$scale     = $base_type->getOption('scale');

		if (null !== $precision) {
			if ($this->decimal_precision_min > $precision || $precision > $this->decimal_precision_max) {
				throw new DBALException(\sprintf(
					'[%s] Column %s with decimal type should have a "precision" between %s and %s not %s.',
					$this->db->getType(),
					$column->getFullName(),
					$this->decimal_precision_min,
					$this->decimal_precision_max,
					$precision
				));
			}

			if (null !== $scale) {
				$scale_max = \min($precision, $this->decimal_scale_max);

				if ($this->decimal_scale_min > $scale || $scale > $scale_max) {
					throw new DBALException(\sprintf(
						'[%s] Column %s with decimal type should have a "scale" between %s and %s not %s.',
						$this->db->getType(),
						$column->getFullName(),
						$this->decimal_scale_min,
						$scale_max,
						$scale
					));
				}
			}
		}
	}

	/**
	 * Gets table primary keys definition query string.
	 *
	 * @param Table $table
	 * @param bool  $alter
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
-- Primary key constraints definition for table {$this->quoteIdentifier($table_name)}
--
{$alter_table}";
	}

	/**
	 * Gets primary key constraint definition query string.
	 *
	 * @param PrimaryKey $pk    the primary key constraint
	 * @param bool       $alter use alter syntax
	 *
	 * @return string
	 */
	protected function getPrimaryKeySQL(PrimaryKey $pk, bool $alter): string
	{
		$columns_list    = $this->quoteCols($pk->getColumns());
		$host_table_name = $pk->getHostTable()
			->getFullName();
		$sql             = $alter ? 'ALTER TABLE ' . $this->quoteIdentifier($host_table_name) . ' ADD ' : '';
		$sql .= 'CONSTRAINT ' . $pk->getName() . ' PRIMARY KEY (' . $columns_list . ')' . ($alter ? ';' : '');

		return $sql;
	}

	/**
	 * Quote columns name in a given list.
	 *
	 * @param array $list
	 *
	 * @return string
	 */
	protected function quoteCols(array $list): string
	{
		return \implode(' , ', \array_map(fn (string $col) => $this->quoteIdentifier($col), $list));
	}

	/**
	 * Gets table unique key constraints definition query string.
	 *
	 * @param Table $table
	 * @param bool  $alter
	 *
	 * @return string
	 */
	protected function getTableUniqueKeyConstraintsDefinitionString(Table $table, bool $alter): string
	{
		$alter_table = [];
		$uc_list     = $table->getUniqueKeyConstraints();

		foreach ($uc_list as /* $uc_name => */ $uc) {
			$alter_table[] = $this->getUniqueKeySQL($uc, $alter);
		}

		$table_name  = $table->getFullName();
		$alter_table = \implode(\PHP_EOL, $alter_table);

		if (empty($alter_table)) {
			return '';
		}

		return "
--
-- Unique constraints definition for table {$this->quoteIdentifier($table_name)}
--
{$alter_table}";
	}

	/**
	 * Gets unique key constraint definition query string.
	 *
	 * @param UniqueKey $uc    the unique constraint
	 * @param bool      $alter use alter syntax
	 *
	 * @return string
	 */
	protected function getUniqueKeySQL(UniqueKey $uc, bool $alter): string
	{
		$host_table_name = $uc->getHostTable()
			->getFullName();
		$columns_list    = $this->quoteCols($uc->getColumns());
		$sql             = $alter ? 'ALTER TABLE ' . $this->quoteIdentifier($host_table_name) . ' ADD ' : '';
		$sql .= 'CONSTRAINT ' . $uc->getName() . ' UNIQUE (' . $columns_list . ')' . ($alter ? ';' : '');

		return $sql;
	}

	/**
	 * Gets all indexes definition query strings for a table (CREATE INDEX statements).
	 *
	 * @param Table $table
	 *
	 * @return string
	 */
	protected function getTableIndexesDefinitionString(Table $table): string
	{
		$index_list = $table->getIndexes();

		if (empty($index_list)) {
			return '';
		}

		$table_name  = $table->getFullName();
		$index_sql   = [];

		foreach ($index_list as $index) {
			$index_sql[] = $this->getIndexSQL($index);
		}

		$index_block = \implode(\PHP_EOL, $index_sql);

		return "
--
-- Indexes definition for table {$this->quoteIdentifier($table_name)}
--
{$index_block}";
	}

	/**
	 * Gets index CREATE statement.
	 *
	 * @param Index $index the index
	 *
	 * @return string
	 */
	abstract protected function getIndexSQL(Index $index): string;

	/**
	 * Gets the SQL for adding an index (CREATE INDEX).
	 *
	 * @param IndexAdded $action
	 *
	 * @return string
	 */
	protected function getIndexAddedString(IndexAdded $action): string
	{
		return $this->getIndexSQL($action->getIndex());
	}

	/**
	 * Gets the SQL for dropping an index (DROP INDEX).
	 *
	 * @param IndexDeleted $action
	 *
	 * @return string
	 */
	protected function getIndexDeletedString(IndexDeleted $action): string
	{
		return 'DROP INDEX ' . $this->quoteIdentifier($action->getIndex()->getName()) . ';';
	}

	/**
	 * Gets table foreign keys definition query string.
	 *
	 * @param Table $table
	 * @param bool  $alter
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
-- Foreign keys constraints definition for table {$this->quoteIdentifier($table_name)}
--
{$alter_table}";
	}

	/**
	 * Gets foreign key constraint definition query string.
	 *
	 * @param ForeignKey $fk    the foreign key constraint
	 * @param bool       $alter use alter syntax
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
		$host_columns      = $this->quoteCols($fk->getHostColumns());
		$reference_columns = $this->quoteCols($fk->getReferenceColumns());
		$sql               = $alter ? 'ALTER TABLE ' . $this->quoteIdentifier($host_table_name) . ' ADD ' : '';
		$sql .= 'CONSTRAINT ' . $fk->getName() . ' FOREIGN KEY (' . $host_columns
			. ') REFERENCES ' . $this->quoteIdentifier($ref_table->getFullName()) . ' (' . $reference_columns . ')';

		$sql .= ' ON UPDATE ';
		$sql .= $this->getForeignKeyActionSQL($update_action);

		$sql .= ' ON DELETE ';
		$sql .= $this->getForeignKeyActionSQL($delete_action);

		$sql .= ($alter ? ';' : '');

		return $sql;
	}

	/**
	 * Gets foreign key action sql.
	 *
	 * @param ForeignKeyAction $action
	 *
	 * @return string
	 */
	protected function getForeignKeyActionSQL(ForeignKeyAction $action): string
	{
		return match ($action) {
			ForeignKeyAction::SET_NULL    => 'SET NULL',
			ForeignKeyAction::SET_DEFAULT => 'SET DEFAULT',
			ForeignKeyAction::CASCADE     => 'CASCADE',
			ForeignKeyAction::RESTRICT    => 'RESTRICT',
			default                       => 'NO ACTION',
		};
	}

	/**
	 * Should return the create table query template.
	 *
	 * @return string
	 */
	abstract protected function createTableQueryTemplate(): string;

	/**
	 * @param TableDeleted $action
	 *
	 * @return string
	 */
	protected function getTableDeletedString(TableDeleted $action): string
	{
		$table_name = $action->getTable()
			->getFullName();

		return 'DROP TABLE ' . $this->quoteIdentifier($table_name) . ';';
	}

	/**
	 * Returns the SQL string to drop a column from a table.
	 *
	 * @param ColumnDeleted $action the column-deleted diff action
	 *
	 * @return string the ALTER TABLE ... DROP ... statement
	 */
	protected function getColumnDeletedString(ColumnDeleted $action): string
	{
		$table_name  = $action->getTable()
			->getFullName();
		$column_name = $action->getColumn()
			->getFullName();

		return 'ALTER TABLE ' . $this->quoteIdentifier($table_name) . ' DROP ' . $this->quoteIdentifier($column_name) . ';';
	}

	/**
	 * @param ColumnAdded $action
	 *
	 * @return string
	 *
	 * @throws DBALException
	 */
	protected function getColumnAddedString(ColumnAdded $action): string
	{
		$table_name = $action->getTable()
			->getFullName();
		$sql        = $this->getColumnDefinitionString($action->getColumn());

		return 'ALTER TABLE ' . $this->quoteIdentifier($table_name) . ' ADD ' . $sql . ';';
	}

	/**
	 * @param ColumnRenamed $action
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

		return 'ALTER TABLE ' . $this->quoteIdentifier($table_name) . ' RENAME COLUMN ' . $this->quoteIdentifier($old_column_name) . ' TO ' . $this->quoteIdentifier($new_column_name) . ';';
	}

	/**
	 * @param ColumnTypeChanged $action
	 *
	 * @return string
	 *
	 * @throws DBALException
	 */
	protected function getColumnTypeChangedString(ColumnTypeChanged $action): string
	{
		$column_definition = $this->getColumnDefinitionString($action->getNewColumn());
		$table_name        = $action->getTable()
			->getFullName();

		return 'ALTER TABLE ' . $this->quoteIdentifier($table_name) . ' ALTER COLUMN ' . $column_definition . ';';
	}

	/**
	 * @param PrimaryKeyConstraintAdded $action
	 *
	 * @return string
	 */
	protected function getPrimaryKeyConstraintAddedString(PrimaryKeyConstraintAdded $action): string
	{
		return $this->getPrimaryKeySQL($action->getConstraint(), true);
	}

	/**
	 * @param PrimaryKeyConstraintDeleted $action
	 *
	 * @return string
	 */
	protected function getPrimaryKeyConstraintDeletedString(PrimaryKeyConstraintDeleted $action): string
	{
		$constraint      = $action->getConstraint();
		$table_name      = $constraint->getHostTable()
			->getFullName();
		$constraint_name = $constraint->getName();

		return 'ALTER TABLE ' . $this->quoteIdentifier($table_name) . ' DROP CONSTRAINT ' . $constraint_name . ';';
	}

	/**
	 * @param ForeignKeyConstraintAdded $action
	 *
	 * @return string
	 */
	protected function getForeignKeyConstraintAddedString(ForeignKeyConstraintAdded $action): string
	{
		return $this->getForeignKeySQL($action->getConstraint(), true);
	}

	/**
	 * @param ForeignKeyConstraintDeleted $action
	 *
	 * @return string
	 */
	protected function getForeignKeyConstraintDeletedString(ForeignKeyConstraintDeleted $action): string
	{
		$constraint      = $action->getConstraint();
		$table_name      = $constraint->getHostTable()
			->getFullName();
		$constraint_name = $constraint->getName();

		return 'ALTER TABLE ' . $this->quoteIdentifier($table_name) . ' DROP CONSTRAINT ' . $constraint_name . ';';
	}

	/**
	 * @param UniqueKeyConstraintAdded $action
	 *
	 * @return string
	 */
	protected function getUniqueKeyConstraintAddedString(UniqueKeyConstraintAdded $action): string
	{
		return $this->getUniqueKeySQL($action->getConstraint(), true);
	}

	/**
	 * @param UniqueKeyConstraintDeleted $action
	 *
	 * @return string
	 */
	protected function getUniqueKeyConstraintDeletedString(UniqueKeyConstraintDeleted $action): string
	{
		$constraint      = $action->getConstraint();
		$table_name      = $constraint->getHostTable()
			->getFullName();
		$constraint_name = $constraint->getName();

		return 'ALTER TABLE ' . $this->quoteIdentifier($table_name) . ' DROP CONSTRAINT ' . $constraint_name . ';';
	}

	/**
	 * Create a comment query.
	 *
	 * @param string $comment
	 *
	 * @return string
	 */
	protected function getCommentQuery(string $comment): string
	{
		return Str::indent($comment, '-- ');
	}

	/**
	 * Returns sql SELECT query.
	 *
	 * @param QBSelect $qb
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
	 * Returns sql selected columns.
	 *
	 * @param QBSelect $qb
	 *
	 * @return string
	 */
	protected function getSelectedColumnsQuery(QBSelect $qb): string
	{
		$columns = \array_unique($qb->getOptionsSelect());

		if (!empty($columns)) {
			return \implode(', ', $columns);
		}

		return '*';
	}

	/**
	 * Returns sql WHERE query part.
	 *
	 * @param QBDelete|QBSelect|QBUpdate $qb
	 *
	 * @return string
	 */
	protected function getWhereQuery(QBDelete|QBSelect|QBUpdate $qb): string
	{
		$rule = $qb->getOptionsWhere();

		if (null !== $rule) {
			$rule = $this->filterToExpression($rule);
		}

		return empty($rule) ? '1 = 1' : $rule;
	}

	/**
	 * Converts a filter using an operator to a sql expression.
	 *
	 * By overriding this method you can change the way this kind of
	 * filters are converted to sql expressions when using another database engine.
	 *
	 * @param Filter $filter
	 *
	 * @return string
	 */
	protected function operatorFilterToExpression(Filter $filter): string
	{
		$left  = $filter->getLeftOperand()->getValueForQuery();
		$right = $filter->getRightOperand()?->getValueForQuery();
		$op    = $filter->getOperator();

		// Function-call style: MySQL JSON_CONTAINS(col, value)
		if (Operator::CONTAINS === $op) {
			return $this->getJsonContainsExpression($left, $right ?? 'NULL');
		}

		// Function-call style: MySQL JSON_CONTAINS_PATH(col, 'one', CONCAT('$.', key))
		if (Operator::HAS_KEY === $op) {
			return $this->getJsonHasKeyExpression($left, $right ?? 'NULL');
		}

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
	 * Returns sql FROM query part.
	 *
	 * @param QBDelete|QBSelect $qb
	 *
	 * @return string
	 */
	protected function getFromQuery(QBDelete|QBSelect $qb): string
	{
		$from = $qb->getOptionsFrom();
		$x    = [];

		foreach ($from as $table => $aliases) {
			$quoted_table = $this->quoteIdentifier($table);

			foreach ($aliases as $alias) {
				$x[] = $quoted_table . ' AS ' . $alias . ' ' . $this->getJoinQueryFor($qb, $alias);
			}
		}

		return \trim(\implode(', ', $x));
	}

	/**
	 * Returns sql JOIN for a given table.
	 *
	 * @param QBDelete|QBSelect $qb          the query builder
	 * @param string            $table_alias the table alias
	 *
	 * @return string
	 */
	protected function getJoinQueryFor(QBDelete|QBSelect $qb, string $table_alias): string
	{
		return $this->buildJoinSql($qb, $table_alias, [$table_alias => true]);
	}

	/**
	 * Returns sql GROUP BY query part.
	 *
	 * @param QBSelect $qb
	 *
	 * @return string
	 */
	protected function getGroupByQuery(QBSelect $qb): string
	{
		$group_by = $qb->getOptionsGroupBy();

		if (!empty($group_by)) {
			return ' GROUP BY ' . \implode(', ', $group_by);
		}

		return '';
	}

	/**
	 * Returns sql HAVING query part.
	 *
	 * @param QBSelect $qb
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
	 * Returns sql ORDER BY query part.
	 *
	 * @param QBDelete|QBSelect|QBUpdate $qb
	 *
	 * @return string
	 */
	protected function getOrderByQuery(QBDelete|QBSelect|QBUpdate $qb): string
	{
		$order_by = $qb->getOptionsOrderBy();

		if (!empty($order_by)) {
			return ' ORDER BY ' . \implode(', ', $order_by);
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
	protected function getLimitQuery(QBDelete|QBSelect|QBUpdate $qb): string
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
	 * Returns the INSERT keyword (e.g. `INSERT`, `INSERT IGNORE`).
	 *
	 * Drivers may override this to emit dialect-specific insert modes.
	 *
	 * @param QBInsert $qb
	 *
	 * @return string
	 */
	protected function getInsertKeyword(QBInsert $qb): string
	{
		return 'INSERT';
	}

	/**
	 * Returns the ON CONFLICT clause appended after VALUES in INSERT queries.
	 *
	 * Base returns `''`. Drivers override for upsert / ignore-on-conflict support.
	 *
	 * @param QBInsert $qb
	 *
	 * @return string
	 */
	protected function getOnConflictClause(QBInsert $qb): string
	{
		return '';
	}

	/**
	 * Returns the RETURNING clause appended after INSERT / UPDATE / DELETE queries.
	 *
	 * The default implementation throws when RETURNING was requested, because MySQL
	 * does not support this clause. Drivers that do support RETURNING (PostgreSQL,
	 * SQLite >= 3.35.0) should override this method to emit the proper SQL fragment.
	 *
	 * @param QBDelete|QBInsert|QBUpdate $qb
	 *
	 * @return string
	 *
	 * @throws DBALRuntimeException when RETURNING is requested but not supported by the driver
	 */
	protected function getReturningClause(QBDelete|QBInsert|QBUpdate $qb): string
	{
		$opts = $qb->getOptionsReturning();

		if ($opts['enabled']) {
			throw new DBALRuntimeException('RETURNING clause is not supported by this database driver.');
		}

		return '';
	}

	/**
	 * Returns sql INSERT query.
	 *
	 * @param QBInsert $qb
	 *
	 * @return string
	 */
	protected function getInsertQuery(QBInsert $qb): string
	{
		$table         = $qb->getOptionsTable();
		$cols          = $qb->getOptionsColumnsNames();
		$values_params = $qb->getOptionsValuesParams();

		if (empty($table)) {
			throw new DBALRuntimeException('Table name required for insert query.');
		}

		if (empty($cols)) {
			throw new DBALRuntimeException('Columns names required for insert query.');
		}

		if (empty($values_params)) {
			throw new DBALRuntimeException('Values params required for insert query.');
		}

		$parts = [];
		foreach ($values_params as $entry) {
			$parts[] = '(' . \implode(', ', $entry) . ')';
		}

		$columns = \implode(', ', $cols);
		$values  = \implode(', ', $parts);

		$keyword  = $this->getInsertKeyword($qb);
		$conflict = $this->getOnConflictClause($qb);

		return $keyword . ' INTO ' . $this->quoteIdentifier($table) . ' (' . $columns . ') VALUES ' . $values . $conflict . $this->getReturningClause($qb);
	}

	/**
	 * Returns sql UPDATE query.
	 *
	 * @param QBUpdate $qb
	 *
	 * @return string
	 */
	protected function getUpdateQuery(QBUpdate $qb): string
	{
		$table = $qb->getOptionsTable();

		if (empty($table)) {
			throw new DBALRuntimeException('Table name required for update query.');
		}

		$x = [];

		foreach ($qb->getOptionsColumns() as $column => $key_bind_name) {
			$x[] = $column . ' = ' . $key_bind_name;
		}

		$set   = \implode(', ', $x);
		$where = $this->getWhereQuery($qb);
		$alias = $qb->getOptionsUpdateTableAlias() ?? '';

		$max    = $qb->getOptionsLimitMax();

		$query  = 'UPDATE ' . $this->quoteIdentifier($table) . (empty($alias) ? '' : ' AS ' . $alias) . ' SET ' . $set . ' WHERE ' . $where;
		$query .= $this->getOrderByQuery($qb);
		$query .= \is_int($max) ? ' LIMIT ' . $max : '';
		$query .= $this->getReturningClause($qb);

		return $query;
	}

	/**
	 * Returns sql DELETE query.
	 *
	 * @param QBDelete $qb
	 *
	 * @return string
	 */
	protected function getDeleteQuery(QBDelete $qb): string
	{
		$where = $this->getWhereQuery($qb);
		$from  = $this->getFromQuery($qb);

		$x = [];

		foreach ($qb->getOptionsFrom() as /* $table_name => */ $aliases) {
			$x = [...$x, ...$aliases];
		}

		$multi_table_delete = \count($x) > 1;
		$delete_alias       = \implode(', ', $x);

		$max   = $qb->getOptionsLimitMax();

		if (!$multi_table_delete) {
			// Single-table DELETE: MySQL multi-table syntax (DELETE alias FROM t)
			// does not support LIMIT; use the standard single-table form instead.
			$query  = 'DELETE FROM ' . $from . ' WHERE ' . $where;
		} else {
			$query  = 'DELETE ' . $delete_alias . ' FROM ' . $from . ' WHERE ' . $where;
		}

		$query .= $this->getOrderByQuery($qb);

		if (\is_int($max)) {
			if ($multi_table_delete) {
				throw new DBALRuntimeException('LIMIT is not supported for multi-table deletes.');
			}

			$query .= ' LIMIT ' . $max;
		}

		$query .= $this->getReturningClause($qb);

		return $query;
	}

	/**
	 * Should return the db query template.
	 *
	 * @return string
	 */
	abstract protected function dbQueryTemplate(): string;

	/**
	 * Recursively builds the JOIN SQL fragment for a given alias, tracking visited
	 * aliases to prevent infinite recursion on cyclic join graphs (DoS guard).
	 *
	 * @param QBDelete|QBSelect   $qb          the query builder
	 * @param string              $table_alias alias whose joins are being expanded
	 * @param array<string, bool> $visited     aliases already processed in this chain
	 *
	 * @return string
	 */
	private function buildJoinSql(QBDelete|QBSelect $qb, string $table_alias, array $visited): string
	{
		$sql   = '';
		$joins = $qb->getOptionsJoins();

		if (isset($joins[$table_alias])) {
			$table_joins       = $joins[$table_alias];
			$visited_with_self = $visited + [$table_alias => true];

			foreach ($table_joins as $join) {
				$type                = $join->getType();
				$options             = $join->getOptions();
				$condition           = (string) $options['condition'];
				$table_to_join       = $options['table_to_join'];
				$table_to_join_alias = $options['table_to_join_alias'];

				// Skip JOIN emission for aliases already in the chain (cycle guard).
				if (isset($visited_with_self[$table_to_join_alias])) {
					continue;
				}

				$sql .= ' ' . $type->value
					. ' JOIN ' . $this->quoteIdentifier($table_to_join) . ' AS ' . $table_to_join_alias
					. ' ON ' . (!empty($condition) ? $condition : '1 = 1');
			}

			foreach ($table_joins as $join) {
				$options             = $join->getOptions();
				$table_to_join_alias = $options['table_to_join_alias'];

				if (!isset($visited_with_self[$table_to_join_alias])) {
					$sql .= $this->buildJoinSql($qb, $table_to_join_alias, $visited_with_self);
				}
			}
		}

		return $sql;
	}
}
