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

namespace Gobl\DBAL\Drivers\SQLLite;

use Gobl\DBAL\Column;
use Gobl\DBAL\DbConfig;
use Gobl\DBAL\Drivers\SQLQueryGeneratorBase;
use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Filters\Filter;
use Gobl\DBAL\Indexes\Index;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Operator;
use Gobl\DBAL\Queries\JoinType;
use Gobl\DBAL\Queries\QBDelete;
use Gobl\DBAL\Queries\QBInsert;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\DBAL\Queries\QBUpdate;
use Gobl\DBAL\Table;
use Gobl\Gobl;

use const GOBL_ASSETS_DIR;

/**
 * Class SQLLiteQueryGenerator.
 */
class SQLLiteQueryGenerator extends SQLQueryGeneratorBase
{
	private static bool $templates_registered = false;

	/**
	 * SQLLiteQueryGenerator constructor.
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
				'sqllite_db'           => ['path' => GOBL_ASSETS_DIR . '/sqllite/db.sql'],
				'sqllite_create_table' => ['path' => GOBL_ASSETS_DIR . '/sqllite/create_table.sql'],
			]);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function wrapDatabaseDefinitionQuery(string $query): string
	{
		return $query;
	}

	/**
	 * {@inheritDoc}
	 *
	 * SQLite does not support ALTER TABLE ADD CONSTRAINT (only ADD COLUMN and RENAME TO).
	 * This override builds all constraints (FOREIGN KEY, UNIQUE) inline inside each
	 * CREATE TABLE statement rather than as separate ALTER TABLE… ADD statements.
	 */
	public function buildDatabase(?string $namespace = null): string
	{
		$tables             = $this->db->getTables($namespace);
		$create_table_parts = [];
		$create_index_parts = [];

		foreach ($tables as $table) {
			$columns = $table->getColumns();
			$sql     = [];

			foreach ($columns as $column) {
				$sql[] = $this->getColumnDefinitionString($column);
			}

			// Inline PRIMARY KEY (returns '' for single-column auto-increment PKs)
			if ($table->hasPrimaryKeyConstraint()) {
				$pk_def = $this->getTablePrimaryKeysDefinitionString($table, false);

				if (!empty($pk_def)) {
					$sql[] = $pk_def;
				}
			}

			// Inline FOREIGN KEY constraints — SQLite supports table-level FOREIGN KEY declarations
			foreach ($table->getForeignKeyConstraints() as $fk) {
				$sql[] = $this->getForeignKeySQL($fk, false);
			}

			// Inline UNIQUE KEY constraints
			foreach ($table->getUniqueKeyConstraints() as $uc) {
				$sql[] = $this->getUniqueKeySQL($uc, false);
			}

			$table_name = $table->getFullName();
			$table_body = \implode(',' . \PHP_EOL, $sql);
			$charset    = $table->getCharset() ?? $this->config->getDbCharset();
			$collate    = $table->getCollate() ?? $this->config->getDbCollate();

			$create_table_parts[] = Gobl::runTemplate($this->createTableQueryTemplate(), [
				'table_name'  => $table_name,
				'charset'     => $charset,
				'collate'     => $collate,
				'table_body'  => $table_body,
				'alter_table' => '',
			]);

			// CREATE INDEX statements are supported by SQLite
			$indexes = $this->getTableIndexesDefinitionString($table);

			if (!empty($indexes)) {
				$create_index_parts[] = $indexes;
			}
		}

		$sql_query = \implode(\PHP_EOL . \PHP_EOL, $create_table_parts);
		$index_sql = \implode(\PHP_EOL . \PHP_EOL, $create_index_parts);

		if (!empty($index_sql)) {
			$sql_query .= \PHP_EOL . $index_sql;
		}

		return Gobl::runTemplate($this->dbQueryTemplate(), [
			'gobl_time'    => Gobl::getGeneratedAtDate(),
			'gobl_version' => GOBL_VERSION,
			'db_sql_query' => $this->wrapDatabaseDefinitionQuery($sql_query),
		]);
	}

	/**
	 * {@inheritDoc}
	 */
	public function quoteIdentifier(string $name): string
	{
		return '"' . \str_replace('"', '""', $name) . '"';
	}

	/**
	 * {@inheritDoc}
	 *
	 * SQLite JSON path extraction: json_extract returns the value without surrounding quotes,
	 * so no UNQUOTE wrapper is needed.
	 */
	public function getJsonPathExpression(string $col_sql_expression, array $json_path): string
	{
		$dot_path = '$.' . \implode('.', \array_map('strval', $json_path));

		return 'JSON_EXTRACT(' . $col_sql_expression . ', ' . $this->quoteLiteral($dot_path) . ')';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function dbQueryTemplate(): string
	{
		return 'sqllite_db';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function createTableQueryTemplate(): string
	{
		return 'sqllite_create_table';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function getStringColumnDefinition(Column $column): string
	{
		$column_name = $column->getFullName();
		$type        = $column->getType()
			->getBaseType();
		$max         = $type->getOption('max', \INF);
		$col         = $this->quoteIdentifier($column_name);
		$sql         = [$col];

		if (\is_finite($max) && $max <= 65535) {
			$sql[] = "varchar({$max})";
		} else {
			// SQLite TEXT handles any length
			$sql[] = 'text';

			$this->defaultAndNullChunks($column, $sql, true);

			return \implode(' ', $sql);
		}

		$this->defaultAndNullChunks($column, $sql);

		return \implode(' ', $sql);
	}

	/**
	 * {@inheritDoc}
	 *
	 * SQLite has no JSON containment function equivalent to MySQL's JSON_CONTAINS or PostgreSQL's @>.
	 * Use JSON path equality filters (table.column.path notation) as a workaround.
	 */
	protected function operatorFilterToExpression(Filter $filter): string
	{
		if (Operator::JSON_CONTAINS === $filter->getOperator()) {
			throw new DBALRuntimeException(
				'SQLite does not support JSON containment checks (JSON_CONTAINS). '
					. 'Use JSON path equality filters (table.column.path notation) instead.'
			);
		}

		return parent::operatorFilterToExpression($filter);
	}

	/**
	 * {@inheritDoc}
	 * SQLite uses INTEGER (0/1) for boolean.
	 */
	protected function getBoolColumnDefinition(Column $column): string
	{
		$column_name = $column->getFullName();
		$sql         = [$this->quoteIdentifier($column_name) . ' integer'];

		$this->defaultAndNullChunks($column, $sql);

		return \implode(' ', $sql);
	}

	/**
	 * {@inheritDoc}
	 * SQLite uses INTEGER for all integer types.
	 */
	protected function getIntColumnDefinition(Column $column): string
	{
		$column_name = $column->getFullName();
		$type        = $column->getType()->getBaseType();
		$col         = $this->quoteIdentifier($column_name);

		if ($type->isAutoIncremented()) {
			// SQLite AUTOINCREMENT must be inline with PRIMARY KEY.
			// getTablePrimaryKeysDefinitionString() skips the separate CONSTRAINT for this column.
			return $col . ' integer PRIMARY KEY AUTOINCREMENT';
		}

		$sql = [$col . ' integer'];

		$this->defaultAndNullChunks($column, $sql);

		return \implode(' ', $sql);
	}

	/**
	 * {@inheritDoc}
	 * SQLite uses INTEGER for bigint as well.
	 */
	protected function getBigintColumnDefinition(Column $column): string
	{
		$column_name = $column->getFullName();
		$type        = $column->getType()->getBaseType();
		$col         = $this->quoteIdentifier($column_name);

		if ($type->isAutoIncremented()) {
			// SQLite AUTOINCREMENT must be inline with PRIMARY KEY.
			// getTablePrimaryKeysDefinitionString() skips the separate CONSTRAINT for this column.
			return $col . ' integer PRIMARY KEY AUTOINCREMENT';
		}

		$sql = [$col . ' integer'];

		$this->defaultAndNullChunks($column, $sql);

		return \implode(' ', $sql);
	}

	/**
	 * {@inheritDoc}
	 * SQLite requires AUTOINCREMENT to be declared inline with PRIMARY KEY.
	 * When a single-column PK uses auto-increment, skip the separate CONSTRAINT clause.
	 */
	protected function getTablePrimaryKeysDefinitionString(Table $table, bool $alter): string
	{
		$pk = $table->getPrimaryKeyConstraint();

		if (!$pk) {
			return '';
		}

		$pk_columns = $pk->getColumns();

		// Single-column PK: skip the CONSTRAINT if the column is inline PRIMARY KEY AUTOINCREMENT.
		if (1 === \count($pk_columns)) {
			$col = $table->getColumn($pk_columns[0]);

			if ($col && $col->getType()->getBaseType()->isAutoIncremented()) {
				return '';
			}
		}

		return parent::getTablePrimaryKeysDefinitionString($table, $alter);
	}

	/**
	 * {@inheritDoc}
	 * SQLite uses REAL for floating-point.
	 *
	 * @throws DBALException
	 */
	protected function getFloatColumnDefinition(Column $column): string
	{
		$this->checkFloatColumn($column);

		$column_name = $column->getFullName();
		$col         = $this->quoteIdentifier($column_name);
		$sql         = [$col . ' real'];

		$this->defaultAndNullChunks($column, $sql);

		return \implode(' ', $sql);
	}

	/**
	 * {@inheritDoc}
	 * SQLite uses NUMERIC for decimal.
	 *
	 * @throws DBALException
	 */
	protected function getDecimalColumnDefinition(Column $column): string
	{
		$this->checkDecimalColumn($column);

		$type = $column->getType()
			->getBaseType();

		$column_name = $column->getFullName();
		$precision   = $type->getOption('precision');
		$scale       = $type->getOption('scale');
		$col         = $this->quoteIdentifier($column_name);
		$sql         = [];

		if (null !== $precision) {
			if (null !== $scale) {
				$sql[] = $col . " numeric({$precision}, {$scale})";
			} else {
				$sql[] = $col . " numeric({$precision})";
			}
		} else {
			$sql[] = $col . ' numeric';
		}

		$this->defaultAndNullChunks($column, $sql);

		return \implode(' ', $sql);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function getIndexSQL(Index $index): string
	{
		$table_name   = $index->getHostTable()->getFullName();
		$columns_list = $this->quoteCols($index->getColumns());
		$index_name   = $this->quoteIdentifier($index->getName());

		return 'CREATE INDEX ' . $index_name . ' ON ' . $this->quoteIdentifier($table_name) . ' (' . $columns_list . ');';
	}

	/**
	 * {@inheritDoc}
	 *
	 * SQLite does not natively support LIMIT in UPDATE (without the
	 * SQLITE_ENABLE_UPDATE_DELETE_LIMIT compile-time flag). When a LIMIT
	 * is requested we rewrite the statement as a rowid sub-query:
	 *
	 *   UPDATE t SET … WHERE rowid IN (
	 *     SELECT rowid FROM t WHERE … [ORDER BY …] LIMIT n
	 *   )
	 *
	 * When LIMIT is absent the standard base-class implementation is used.
	 * ORDER BY without LIMIT still throws via the (inherited) guard.
	 */
	protected function getUpdateQuery(QBUpdate $qb): string
	{
		$max = $qb->getOptionsLimitMax();

		if (null === $max) {
			// No LIMIT – fall through to the standard implementation.
			if (!empty($qb->getOptionsOrderBy())) {
				throw new DBALRuntimeException('SQLite does not support ORDER BY in UPDATE statements.');
			}

			return parent::getUpdateQuery($qb);
		}

		$table = $qb->getOptionsTable();

		if (empty($table)) {
			throw new DBALRuntimeException('Table name required for update query.');
		}

		$set_parts = [];

		foreach ($qb->getOptionsColumns() as $column => $key_bind_name) {
			$set_parts[] = $column . ' = ' . $key_bind_name;
		}

		$set   = \implode(', ', $set_parts);
		$where = $this->getWhereQuery($qb);
		$ob    = $this->getOrderByQuery($qb); // '' or ' ORDER BY …'

		$alias = $qb->getOptionsUpdateTableAlias() ?? '';
		$qt    = $this->quoteIdentifier($table);
		$qta   = empty($alias) ? $qt : $qt . ' AS ' . $alias;
		$sub   = 'SELECT rowid FROM ' . $qta . ' WHERE ' . $where . $ob . ' LIMIT ' . $max;
		$query = 'UPDATE ' . $qt . (empty($alias) ? '' : ' AS ' . $alias) . ' SET ' . $set . ' WHERE rowid IN (' . $sub . ')';
		$query .= $this->getReturningClause($qb);

		return $query;
	}

	/**
	 * {@inheritDoc}
	 *
	 * SQLite does not support multi-table DELETE or JOINs in DELETE.
	 * Single-table syntax: `DELETE FROM table [AS alias] WHERE condition`
	 * (MySQL-style `DELETE alias FROM table ...` is not valid in SQLite.)
	 *
	 * When a LIMIT is requested a rowid sub-query is used:
	 *
	 *   DELETE FROM t WHERE rowid IN (
	 *     SELECT rowid FROM t WHERE … [ORDER BY …] LIMIT n
	 *   )
	 *
	 * ORDER BY without LIMIT still throws via the guard.
	 */
	protected function getDeleteQuery(QBDelete $qb): string
	{
		$from_map = $qb->getOptionsFrom();

		$x = [];

		foreach ($from_map as $aliases) {
			$x = [...$x, ...$aliases];
		}

		if (\count($x) > 1 || !empty($qb->getOptionsJoins())) {
			throw new DBALRuntimeException('SQLite does not support multi-table DELETE statements.');
		}

		$where = $this->getWhereQuery($qb);
		$max   = $qb->getOptionsLimitMax();

		if (null !== $max) {
			// LIMIT: rewrite as rowid subquery so ORDER BY + LIMIT work correctly.
			$table  = \array_key_first($from_map);
			$alias  = $from_map[$table][0] ?? '';
			$qt     = $this->quoteIdentifier($table);
			$qta    = empty($alias) ? $qt : $qt . ' AS ' . $alias;
			$ob     = $this->getOrderByQuery($qb);
			$sub    = 'SELECT rowid FROM ' . $qta . ' WHERE ' . $where . $ob . ' LIMIT ' . $max;
			$query  = 'DELETE FROM ' . $qt . ' WHERE rowid IN (' . $sub . ')';
			$query .= $this->getReturningClause($qb);

			return $query;
		}

		$from   = $this->getFromQuery($qb);
		$query  = 'DELETE FROM ' . $from . ' WHERE ' . $where;

		if (!empty($qb->getOptionsOrderBy())) {
			throw new DBALRuntimeException('SQLite does not support ORDER BY in DELETE statements.');
		}

		$query .= $this->getReturningClause($qb);

		return $query;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Overrides the base implementation to emulate RIGHT JOIN as a LEFT JOIN with
	 * swapped tables, since older SQLite builds (< 3.39.0) do not support RIGHT JOIN.
	 *
	 * When the running SQLite version is >= 3.39.0 the override is bypassed entirely
	 * and the base class generates native RIGHT JOIN SQL.
	 *
	 * For older builds, when a RIGHT JOIN is detected on a FROM-level alias:
	 *
	 *   FROM host AS h RIGHT JOIN target AS t ON cond
	 *
	 * is transparently rewritten as:
	 *
	 *   FROM target AS t LEFT JOIN host AS h ON cond
	 *
	 * This is semantically equivalent: both return all rows from `target` with
	 * matching rows from `host` (NULL for `host` columns when no match exists).
	 *
	 * Note: RIGHT JOINs nested inside sub-join chains (i.e. not directly off a FROM
	 * table) are not emulated on older SQLite and will throw via {@see getJoinQueryFor}.
	 */
	protected function getFromQuery(QBDelete|QBSelect $qb): string
	{
		if ($this->supportsNativeRightJoin()) {
			// SQLite >= 3.39.0: native RIGHT JOIN support — use the standard path.
			return parent::getFromQuery($qb);
		}

		$all_joins = $qb->getOptionsJoins();
		$from      = $qb->getOptionsFrom();
		$x         = [];

		foreach ($from as $table => $aliases) {
			$qt = $this->quoteIdentifier($table);

			foreach ($aliases as $alias) {
				$alias_joins = $all_joins[$alias] ?? [];

				// Partition this alias's joins into RIGHT vs. everything else.
				$right_joins  = [];
				$normal_joins = [];

				foreach ($alias_joins as $join) {
					if (JoinType::RIGHT === $join->getType()) {
						$right_joins[] = $join;
					} else {
						$normal_joins[] = $join;
					}
				}

				if (empty($right_joins)) {
					// Standard path – no RIGHT JOINs on this alias.
					$x[] = \trim($qt . ' AS ' . $alias . ' ' . $this->getJoinQueryFor($qb, $alias));
				} else {
					// Emulation: each RIGHT JOIN becomes a separate FROM entry where
					// the original host is rewritten as a LEFT JOIN child of the target.
					foreach ($right_joins as $rj) {
						$opts  = $rj->getOptions();
						$rt    = $this->quoteIdentifier($opts['table_to_join']);
						$ra    = $opts['table_to_join_alias'];
						$cond  = (string) $opts['condition'];

						// Target table leads; collect its own sub-joins first.
						$entry  = $rt . ' AS ' . $ra;
						$entry .= $this->getJoinQueryFor($qb, $ra);

						// Original host table becomes a LEFT JOIN child.
						$entry .= ' LEFT JOIN ' . $qt . ' AS ' . $alias;
						$entry .= ' ON ' . (!empty($cond) ? $cond : '1 = 1');

						// Non-right joins that were anchored on the original host
						// are appended after the swapped LEFT JOIN.
						foreach ($normal_joins as $nj) {
							$nopts  = $nj->getOptions();
							$njt    = $this->quoteIdentifier($nopts['table_to_join']);
							$nja    = $nopts['table_to_join_alias'];
							$ncond  = (string) $nopts['condition'];

							$entry .= ' ' . $nj->getType()->value . ' JOIN ' . $njt . ' AS ' . $nja;
							$entry .= ' ON ' . (!empty($ncond) ? $ncond : '1 = 1');
							$entry .= $this->getJoinQueryFor($qb, $nja);
						}

						$x[] = \trim($entry);
					}
				}
			}
		}

		return \trim(\implode(', ', $x));
	}

	/**
	 * Guards against RIGHT JOIN at sub-join levels on SQLite < 3.39.0.
	 * On SQLite >= 3.39.0 native RIGHT JOIN is supported so the guard is skipped.
	 *
	 * Top-level RIGHT JOINs on older SQLite are handled by
	 * {@see getFromQuery} via the LEFT JOIN swap emulation.
	 *
	 * Before delegating to the base class builder, the entire join sub-tree rooted
	 * at `$table_alias` is walked for any RIGHT JOIN, since the base class's
	 * `buildJoinSql` is private and cannot be overridden to intercept sub-level joins.
	 */
	protected function getJoinQueryFor(QBDelete|QBSelect $qb, string $table_alias): string
	{
		if (!$this->supportsNativeRightJoin()) {
			$this->assertNoSubLevelRightJoin($qb, $table_alias, [$table_alias => true]);
		}

		return parent::getJoinQueryFor($qb, $table_alias);
	}

	/**
	 * {@inheritDoc}
	 *
	 * SQLite uses `INSERT OR IGNORE INTO` for conflict-ignore mode.
	 */
	protected function getInsertKeyword(QBInsert $qb): string
	{
		if ('ignore' === ($qb->getOptionsOnConflict()['action'] ?? null)) {
			return 'INSERT OR IGNORE';
		}

		return 'INSERT';
	}

	/**
	 * {@inheritDoc}
	 *
	 * SQLite ON CONFLICT (requires SQLite >= 3.24.0):
	 *   - ignore: handled via `INSERT OR IGNORE INTO` keyword (see getInsertKeyword)
	 *   - update: `ON CONFLICT (cols) DO UPDATE SET col = EXCLUDED.col [, ...]`
	 *             Conflict columns are required; throws if omitted.
	 */
	protected function getOnConflictClause(QBInsert $qb): string
	{
		$conflict = $qb->getOptionsOnConflict();
		$action   = $conflict['action'] ?? null;

		if (null === $action || 'ignore' === $action) {
			return '';
		}

		// action = 'update' requires SQLite >= 3.24.0
		$conflict_columns = $conflict['conflict_columns'] ?? [];
		$update_columns   = $conflict['update_columns'] ?? [];
		$inserted_cols    = $qb->getOptionsColumnsNames();
		$cols_to_update   = empty($update_columns) ? $inserted_cols : $update_columns;

		if (empty($conflict_columns)) {
			throw new DBALRuntimeException(
				'SQLite ON CONFLICT DO UPDATE requires conflict_columns to be specified via doUpdateOnConflict().'
			);
		}

		$target = ' (' . \implode(', ', $conflict_columns) . ')';

		$parts = [];

		foreach ($cols_to_update as $col) {
			$parts[] = $col . ' = EXCLUDED.' . $col;
		}

		return ' ON CONFLICT' . $target . ' DO UPDATE SET ' . \implode(', ', $parts);
	}

	/**
	 * {@inheritDoc}
	 *
	 * SQLite supports RETURNING since version 3.35.0 (March 2021) on INSERT, UPDATE, and DELETE.
	 */
	protected function getReturningClause(QBDelete|QBInsert|QBUpdate $qb): string
	{
		$opts = $qb->getOptionsReturning();

		if (!$opts['enabled']) {
			return '';
		}

		$columns = empty($opts['columns']) ? ['*'] : $opts['columns'];

		return ' RETURNING ' . \implode(', ', $columns);
	}

	/**
	 * Recursively walks the join sub-tree of `$alias` and throws if any RIGHT JOIN is found.
	 *
	 * @param QBDelete|QBSelect   $qb
	 * @param string              $alias
	 * @param array<string, bool> $visited cycle-guard
	 *
	 * @throws DBALRuntimeException when a RIGHT JOIN is found at sub-join level
	 */
	private function assertNoSubLevelRightJoin(QBDelete|QBSelect $qb, string $alias, array $visited): void
	{
		$joins = $qb->getOptionsJoins()[$alias] ?? [];

		foreach ($joins as $join) {
			if (JoinType::RIGHT === $join->getType()) {
				throw new DBALRuntimeException(
					'SQLite does not support RIGHT JOIN at sub-join level. '
						. 'Top-level RIGHT JOINs are automatically emulated using LEFT JOIN with swapped tables.'
				);
			}

			$target = $join->getOptions()['table_to_join_alias'];

			if (!isset($visited[$target])) {
				$this->assertNoSubLevelRightJoin($qb, $target, $visited + [$target => true]);
			}
		}
	}

	/**
	 * Returns true when the connected (or configured) SQLite version natively supports
	 * RIGHT JOIN (>= 3.39.0, released July 2022).
	 *
	 * The version is obtained from `db_server_version` in {@see DbConfig} (useful
	 * for offline / test usage without a live connection) or from the live PDO
	 * connection attribute as a fallback. When the version cannot be determined
	 * the method returns false (safe fallback: use LEFT JOIN emulation).
	 */
	private function supportsNativeRightJoin(): bool
	{
		$version = $this->config->getDbServerVersion($this->db);

		if (null === $version) {
			// Cannot determine version — safe default: emulate.
			return false;
		}

		return \version_compare($version, '3.39.0', '>=');
	}
}
