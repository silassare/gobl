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

namespace Gobl\DBAL\Drivers\PostgreSQL;

use Gobl\DBAL\Column;
use Gobl\DBAL\DbConfig;
use Gobl\DBAL\Diff\Actions\DBCharsetChanged;
use Gobl\DBAL\Diff\Actions\DBCollateChanged;
use Gobl\DBAL\Diff\Actions\TableCharsetChanged;
use Gobl\DBAL\Diff\Actions\TableCollateChanged;
use Gobl\DBAL\Drivers\SQLQueryGeneratorBase;
use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Filters\Filter;
use Gobl\DBAL\Indexes\Index;
use Gobl\DBAL\Indexes\IndexType;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Operator;
use Gobl\DBAL\Queries\QBDelete;
use Gobl\DBAL\Queries\QBInsert;
use Gobl\DBAL\Queries\QBUpdate;
use Gobl\DBAL\Types\TypeJSON;
use Gobl\DBAL\Types\Utils\JsonPath;
use Gobl\Gobl;
use RuntimeException;

use const GOBL_ASSETS_DIR;

/**
 * Class PostgreSQLQueryGenerator.
 */
class PostgreSQLQueryGenerator extends SQLQueryGeneratorBase
{
	private static bool $templates_registered = false;

	/**
	 * PostgreSQLQueryGenerator constructor.
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
				'postgresql_db'           => ['path' => GOBL_ASSETS_DIR . '/postgresql/db.sql'],
				'postgresql_create_table' => ['path' => GOBL_ASSETS_DIR . '/postgresql/create_table.sql'],
			]);
		}
	}

	public function wrapDatabaseDefinitionQuery(string $query): string
	{
		return $query;
	}

	public function quoteIdentifier(string $name): string
	{
		return '"' . \str_replace('"', '""', $name) . '"';
	}

	/**
	 * {@inheritDoc}
	 *
	 * PostgreSQL JSON path extraction:
	 *   - single-level: col->>'key'
	 *   - multi-level:  col#>>'{key1,key2}'
	 */
	public function getJsonPathExtractionExpression(JsonPath $json_path): string
	{
		$col_expr      =  $this->getJsonPathColumnFQN($json_path);
		$path_segments = $json_path->getPathSegments();

		if (1 === \count($path_segments)) {
			return $col_expr . '->>' . $this->quoteLiteral((string) $path_segments[0]);
		}

		$path_content = $this->getJsonPathSegmentsAsString($json_path);

		return $col_expr . '#>>' . $this->quoteLiteral('{' . $path_content . '}');
	}

	/**
	 * {@inheritDoc}
	 *
	 * PostgreSQL JSON containment: `left @> right::jsonb`
	 */
	public function getJsonContainsExpression(string $left, string $right): string
	{
		return $left . ' @> ' . $right . '::jsonb';
	}

	/**
	 * {@inheritDoc}
	 *
	 * PostgreSQL key existence: `jsonb_path_exists(col, ('$.' || key)::jsonpath)`
	 *
	 * Uses `jsonb_path_exists()` (equivalent to the `@?` jsonpath operator but
	 * avoids the bare `?` character which PDO interprets as a positional-parameter
	 * placeholder when mixed with named parameters).
	 *
	 * - Single-segment: `'tag'`  -> `'$.tag'::jsonpath`   -> checks top-level key `tag`
	 * - Multi-segment:  `'user.role'` -> `'$.user.role'::jsonpath` -> checks nested key
	 */
	public function getJsonHasKeyExpression(string $col_sql_expression, string $key_expression): string
	{
		return 'jsonb_path_exists(' . $col_sql_expression . ", ('$.' || " . $key_expression . ')::jsonpath)';
	}

	protected function getJsonPathSegmentsAsString(JsonPath $json_path): string
	{
		$path_segments = $json_path->getPathSegments();

		// For multi-level paths each segment is embedded inside a PostgreSQL array literal
		// '{seg1,seg2}' which is itself a SQL string literal.
		// Two layers of escaping are needed:
		//   1. Array-literal layer: segments containing commas, braces, double-quotes or
		//      spaces must be wrapped in " with inner " escaped as "".
		//   2. SQL string-literal layer: single-quotes are doubled via singleQuote().
		// quotes around the whole '{...}' composite literal, not just each segment.
		return \implode(',', \array_map(static function (mixed $s): string {
			$s = (string) $s;

			if (\preg_match('/[,{}" ]/', $s)) {
				// Wrap in PostgreSQL array-element double-quotes; escape inner " as "".
				$s = '"' . \str_replace('"', '""', $s) . '"';
			}

			// Escape single-quotes for the outer SQL string literal (layer 2).
			return \str_replace("'", "''", $s);
		}, $path_segments));
	}

	protected function dbQueryTemplate(): string
	{
		return 'postgresql_db';
	}

	protected function createTableQueryTemplate(): string
	{
		return 'postgresql_create_table';
	}

	protected function getStringColumnDefinition(Column $column): string
	{
		$column_name = $column->getFullName();
		$type        = $column->getType()
			->getBaseType();
		$max         = $type->getOption('max', \INF);

		$col = $this->quoteIdentifier($column_name);

		$sql = [$col];

		if (\is_finite($max) && $max <= 65535) {
			$sql[] = "varchar({$max})";

			$this->defaultAndNullChunks($column, $sql);
		} else {
			// PostgreSQL TEXT handles any length
			$sql[] = 'text';

			$this->defaultAndNullChunks($column, $sql, true);
		}

		return \implode(' ', $sql);
	}

	protected function getBoolColumnDefinition(Column $column): string
	{
		$column_name = $column->getFullName();
		$sql         = [$this->quoteIdentifier($column_name) . ' boolean'];

		$this->defaultAndNullChunks($column, $sql);

		return \implode(' ', $sql);
	}

	protected function getIntColumnDefinition(Column $column): string
	{
		$column_name = $column->getFullName();
		$type        = $column->getType()
			->getBaseType();
		$min         = $type->getOption('min', -\INF);
		$max         = $type->getOption('max', \INF);
		$col         = $this->quoteIdentifier($column_name);

		if ($type->isAutoIncremented()) {
			// PostgreSQL SERIAL = INTEGER + auto-increment sequence
			return $col . ' serial';
		}

		$sql = [$col];

		if ($min >= -32768 && $max <= 32767) {
			$sql[] = 'smallint';
		} else {
			$sql[] = 'integer';
		}

		$this->defaultAndNullChunks($column, $sql);

		return \implode(' ', $sql);
	}

	protected function getBigintColumnDefinition(Column $column): string
	{
		$column_name = $column->getFullName();
		$type        = $column->getType()
			->getBaseType();
		$col         = $this->quoteIdentifier($column_name);

		if ($type->isAutoIncremented()) {
			// PostgreSQL BIGSERIAL = BIGINT + auto-increment sequence
			return $col . ' bigserial';
		}

		$sql = [$col . ' bigint'];

		$this->defaultAndNullChunks($column, $sql);

		return \implode(' ', $sql);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws DBALException
	 */
	protected function getFloatColumnDefinition(Column $column): string
	{
		$this->checkFloatColumn($column);

		$column_name = $column->getFullName();
		$mantissa    = $column->getType()
			->getOption('mantissa');
		$col         = $this->quoteIdentifier($column_name);
		$sql         = [];

		if (null !== $mantissa) {
			$sql[] = $col . " float({$mantissa})";
		} else {
			$sql[] = $col . ' double precision';
		}

		$this->defaultAndNullChunks($column, $sql);

		return \implode(' ', $sql);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws DBALException
	 */
	protected function getDecimalColumnDefinition(Column $column): string
	{
		$type = $column->getType()
			->getBaseType();
		$this->checkDecimalColumn($column);

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
	 *
	 * PostgreSQL supports native JSONB (binary JSON, preferred) and JSON column types.
	 * When native_json is enabled, JSONB is used for efficient storage and indexing.
	 */
	protected function getJSONColumnDefinition(Column $column): string
	{
		/** @var TypeJSON $base */
		$base = $column->getType()->getBaseType();

		if (!$base->isNativeJson()) {
			return parent::getJSONColumnDefinition($column);
		}

		$col = $this->quoteIdentifier($column->getFullName());
		$sql = [$col . ' jsonb'];

		$this->defaultAndNullChunks($column, $sql);

		return \implode(' ', $sql);
	}

	/**
	 * {@inheritDoc}
	 *
	 * Delegates CONTAINS to `getJsonContainsExpression` for the `@>` syntax.
	 */
	protected function operatorFilterToExpression(Filter $filter): string
	{
		if (Operator::CONTAINS === $filter->getOperator()) {
			$left  = $filter->getLeftOperand()->getValueForQuery();
			$right = $filter->getRightOperand()?->getValueForQuery();

			return $this->getJsonContainsExpression($left, $right ?? 'NULL');
		}

		return parent::operatorFilterToExpression($filter);
	}

	protected function getDBCharsetChangeString(DBCharsetChanged $action): string
	{
		throw new RuntimeException('PostgreSQL does not support changing database charset via query.');
	}

	protected function getDBCollateChangeString(DBCollateChanged $action): string
	{
		throw new RuntimeException('PostgreSQL does not support changing database collate via query.');
	}

	protected function getTableCharsetChangeString(TableCharsetChanged $action): string
	{
		throw new RuntimeException('PostgreSQL does not support changing table charset via query.');
	}

	protected function getTableCollateChangeString(TableCollateChanged $action): string
	{
		throw new RuntimeException('PostgreSQL does not support changing table collate via query.');
	}

	/**
	 * {@inheritDoc}
	 *
	 * PostgreSQL uses: CREATE INDEX name ON table [USING method] (cols);
	 * MYSQL_* index types are silently ignored.
	 */
	protected function getIndexSQL(Index $index): string
	{
		$table_name   = $index->getHostTable()->getFullName();
		$columns_list = $this->quoteCols($index->getColumns());
		$index_type   = $index->getType();
		$index_name   = $this->quoteIdentifier($index->getName());

		$access_method = match ($index_type) {
			IndexType::BTREE        => 'btree',
			IndexType::HASH         => 'hash',
			IndexType::PGSQL_GIN    => 'gin',
			IndexType::PGSQL_GIST   => 'gist',
			IndexType::PGSQL_BRIN   => 'brin',
			IndexType::PGSQL_SPGIST => 'spgist',
			default                 => null,
		};

		$sql = 'CREATE INDEX ' . $index_name . ' ON ' . $this->quoteIdentifier($table_name);

		if ($access_method) {
			$sql .= ' USING ' . $access_method;
		}

		$sql .= ' (' . $columns_list . ');';

		return $sql;
	}

	/**
	 * {@inheritDoc}
	 *
	 * PostgreSQL does not natively support LIMIT in UPDATE. When a LIMIT is
	 * requested we rewrite the statement as a ctid sub-query:
	 *
	 *   UPDATE t SET ... WHERE ctid IN (
	 *     SELECT ctid FROM t WHERE ... [ORDER BY ...] LIMIT n
	 *   )
	 *
	 * When LIMIT is absent the standard base-class implementation is used.
	 * ORDER BY without LIMIT still throws via the (inherited) guard.
	 */
	protected function getUpdateQuery(QBUpdate $qb): string
	{
		$max = $qb->getOptionsLimitMax();

		if (null === $max) {
			// No LIMIT - fall through to the standard implementation.
			if (!empty($qb->getOptionsOrderBy())) {
				throw new DBALRuntimeException('PostgreSQL does not support ORDER BY in UPDATE statements.');
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
		$ob    = $this->getOrderByQuery($qb); // '' or ' ORDER BY ...'

		$alias = $qb->getOptionsUpdateTableAlias() ?? '';
		$qt    = $this->quoteIdentifier($table);
		$from  = empty($alias) ? $qt : $qt . ' AS ' . $alias;
		$sub   = 'SELECT ctid FROM ' . $from . ' WHERE ' . $where . $ob . ' LIMIT ' . $max;
		$query = 'UPDATE ' . $qt . (empty($alias) ? '' : ' AS ' . $alias) . ' SET ' . $set . ' WHERE ctid IN (' . $sub . ')';
		$query .= $this->getReturningClause($qb);

		return $query;
	}

	/**
	 * {@inheritDoc}
	 *
	 * PostgreSQL multi-table DELETE syntax:
	 *   DELETE FROM primary_table [AS alias] USING other_table [AS alias] [, ...]
	 *   WHERE join_conditions AND filter_conditions
	 *
	 * JOIN ON conditions are extracted from the join chain and moved into WHERE.
	 *
	 * For single-table DELETE with LIMIT a ctid sub-query is used:
	 *
	 *   DELETE FROM t WHERE ctid IN (
	 *     SELECT ctid FROM t WHERE ... [ORDER BY ...] LIMIT n
	 *   )
	 */
	protected function getDeleteQuery(QBDelete $qb): string
	{
		$where    = $this->getWhereQuery($qb);
		$from_map = $qb->getOptionsFrom();

		// Flatten from-map to an ordered list of [table, alias] pairs.
		$from_list = [];

		foreach ($from_map as $table => $aliases) {
			foreach ($aliases as $alias) {
				$from_list[] = [$table, $alias];
			}
		}

		if (empty($from_list)) {
			throw new DBALRuntimeException('Table name required for delete query.');
		}

		[$primary_table, $primary_alias] = $from_list[0];

		$max = $qb->getOptionsLimitMax();

		// Single-table DELETE with LIMIT: rewrite as ctid subquery.
		if (null !== $max && 1 === \count($from_list) && empty($qb->getOptionsJoins())) {
			$qpt   = $this->quoteIdentifier($primary_table);
			$ob    = $this->getOrderByQuery($qb); // '' or ' ORDER BY ...'
			$sub   = 'SELECT ctid FROM ' . $qpt . ' AS ' . $primary_alias . ' WHERE ' . $where . $ob . ' LIMIT ' . $max;
			$query = 'DELETE FROM ' . $qpt . ' WHERE ctid IN (' . $sub . ')';
			$query .= $this->getReturningClause($qb);

			return $query;
		}

		$using_parts     = [];
		$join_conditions = [];

		// Non-primary explicit FROM tables go into USING.
		foreach (\array_slice($from_list, 1) as [$table, $alias]) {
			$using_parts[] = $this->quoteIdentifier($table) . ' AS ' . $alias;
			$this->flattenJoinsForUsing($qb, $alias, $using_parts, $join_conditions);
		}

		// JOINs rooted at the primary alias also go into USING.
		$this->flattenJoinsForUsing($qb, $primary_alias, $using_parts, $join_conditions);

		// Move JOIN ON conditions in front of the existing WHERE predicate.
		if (!empty($join_conditions)) {
			$where = '(' . \implode(') AND (', $join_conditions) . ') AND (' . $where . ')';
		}

		$primary_from = $this->quoteIdentifier($primary_table) . ' AS ' . $primary_alias;

		if (empty($using_parts)) {
			$query = 'DELETE FROM ' . $primary_from . ' WHERE ' . $where;
		} else {
			$query = 'DELETE FROM ' . $primary_from . ' USING ' . \implode(', ', $using_parts) . ' WHERE ' . $where;
		}

		if (!empty($qb->getOptionsOrderBy())) {
			throw new DBALRuntimeException('PostgreSQL does not support ORDER BY in DELETE statements.');
		}

		if (null !== $qb->getOptionsLimitMax()) {
			throw new DBALRuntimeException('PostgreSQL does not support LIMIT in multi-table DELETE.');
		}

		$query .= $this->getReturningClause($qb);

		return $query;
	}

	/**
	 * {@inheritDoc}
	 *
	 * PostgreSQL ON CONFLICT:
	 *   - ignore: `ON CONFLICT DO NOTHING`
	 *   - update: `ON CONFLICT [(cols)] DO UPDATE SET col = EXCLUDED.col [, ...]`
	 */
	protected function getOnConflictClause(QBInsert $qb): string
	{
		$conflict = $qb->getOptionsOnConflict();
		$action   = $conflict['action'] ?? null;

		if (null === $action) {
			return '';
		}

		if ('ignore' === $action) {
			return ' ON CONFLICT DO NOTHING';
		}

		// action = 'update'
		$conflict_columns = $conflict['conflict_columns'] ?? [];
		$update_columns   = $conflict['update_columns'] ?? [];
		$inserted_cols    = $qb->getOptionsColumnsNames();
		$cols_to_update   = empty($update_columns) ? $inserted_cols : $update_columns;

		$target = empty($conflict_columns)
			? ''
			: ' (' . \implode(', ', $conflict_columns) . ')';

		$parts = [];

		foreach ($cols_to_update as $col) {
			$parts[] = $col . ' = EXCLUDED.' . $col;
		}

		return ' ON CONFLICT' . $target . ' DO UPDATE SET ' . \implode(', ', $parts);
	}

	/**
	 * {@inheritDoc}
	 *
	 * PostgreSQL fully supports the RETURNING clause on INSERT, UPDATE, and DELETE.
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
	 * Recursively collects JOIN tables and ON conditions from the given host alias
	 * for use in a PostgreSQL USING clause.
	 *
	 * @param QBDelete $qb
	 * @param string   $host_alias
	 * @param string[] $using_parts     accumulates `table AS alias` strings
	 * @param string[] $join_conditions accumulates ON condition strings
	 */
	private function flattenJoinsForUsing(
		QBDelete $qb,
		string $host_alias,
		array &$using_parts,
		array &$join_conditions,
		array $visited = [],
	): void {
		$joins = $qb->getOptionsJoins();

		if (!isset($joins[$host_alias])) {
			return;
		}

		$visited_with_self = $visited + [$host_alias => true];

		foreach ($joins[$host_alias] as $join) {
			$opts      = $join->getOptions();
			$target    = $opts['table_to_join'];
			$target_as = $opts['table_to_join_alias'];
			$cond      = (string) $opts['condition'];

			// Cycle guard: skip aliases already in the chain to prevent infinite recursion.
			if (isset($visited_with_self[$target_as])) {
				continue;
			}

			$using_parts[] = $this->quoteIdentifier($target) . ' AS ' . $target_as;

			if (!empty($cond) && '1 = 1' !== $cond) {
				$join_conditions[] = $cond;
			}

			$this->flattenJoinsForUsing($qb, $target_as, $using_parts, $join_conditions, $visited_with_self);
		}
	}
}
