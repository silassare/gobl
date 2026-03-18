<?php

/**
 * Copyright (c) Emile Silas Sare.
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
use Gobl\DBAL\Diff\Actions\ColumnTypeChanged;
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
use Gobl\DBAL\Types\TypeBigint;
use Gobl\DBAL\Types\TypeJson;
use Gobl\DBAL\Types\TypeString;
use Gobl\DBAL\Types\Utils\JsonPath;
use Gobl\Gobl;
use Override;
use RuntimeException;

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
				'postgresql_db'           => 'postgresql/db.sql.blate',
				'postgresql_create_table' => 'postgresql/create_table.sql.blate',
			]);
		}
	}

	#[Override]
	public function wrapDatabaseDefinitionQuery(string $query): string
	{
		return $query;
	}

	#[Override]
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
	#[Override]
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
	#[Override]
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
	#[Override]
	public function getJsonHasKeyExpression(string $col_sql_expression, string $key_expression): string
	{
		return 'jsonb_path_exists(' . $col_sql_expression . ", ('$.' || " . $key_expression . ')::jsonpath)';
	}

	#[Override]
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

	#[Override]
	protected function dbQueryTemplate(): string
	{
		return 'postgresql_db';
	}

	#[Override]
	protected function createTableQueryTemplate(): string
	{
		return 'postgresql_create_table';
	}

	#[Override]
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

	#[Override]
	protected function getBoolColumnDefinition(Column $column): string
	{
		$column_name = $column->getFullName();
		$sql         = [$this->quoteIdentifier($column_name) . ' boolean'];

		$this->defaultAndNullChunks($column, $sql);

		return \implode(' ', $sql);
	}

	#[Override]
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

	#[Override]
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
	#[Override]
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
	#[Override]
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
	#[Override]
	protected function getJSONColumnDefinition(Column $column): string
	{
		/** @var TypeJson $base */
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
	#[Override]
	protected function operatorFilterToExpression(Filter $filter): string
	{
		if (Operator::CONTAINS === $filter->getOperator()) {
			$left  = $filter->getLeftOperand()->getValueForQuery();
			$right = $filter->getRightOperand()?->getValueForQuery();

			return $this->getJsonContainsExpression($left, $right ?? 'NULL');
		}

		return parent::operatorFilterToExpression($filter);
	}

	/**
	 * {@inheritDoc}
	 *
	 * PostgreSQL requires separate sub-commands for each aspect of a column change:
	 *   ALTER TABLE t ALTER COLUMN col TYPE new_type [USING expr];
	 *   ALTER TABLE t ALTER COLUMN col SET NOT NULL | DROP NOT NULL;
	 *   ALTER TABLE t ALTER COLUMN col SET DEFAULT val | DROP DEFAULT;
	 *
	 * Each sub-command is emitted only when the relevant aspect actually changed
	 * (type, nullability, or default).  The sub-commands are joined with newlines.
	 *
	 * When converting TEXT/VARCHAR or TEXT-stored JSON to JSONB, a
	 * USING to_jsonb(col::text) clause is appended to the TYPE sub-command.
	 */
	#[Override]
	protected function getColumnTypeChangedString(ColumnTypeChanged $action): string
	{
		$new_column = $action->getNewColumn();
		$old_column = $action->getOldColumn();
		$table_name = $action->getTable()->getFullName();
		$new_base   = $new_column->getType()->getBaseType();
		$old_base   = $old_column->getType()->getBaseType();
		$t_quoted   = $this->quoteIdentifier($table_name);
		$col_quoted = $this->quoteIdentifier($new_column->getFullName());
		$statements = [];

		// 1. TYPE sub-command
		$type_expr = $this->extractPostgresTypeExpression($new_column);
		$type_sql  = 'ALTER TABLE ' . $t_quoted . ' ALTER COLUMN ' . $col_quoted . ' TYPE ' . $type_expr;

		// PostgreSQL cannot implicitly cast TEXT/VARCHAR or TEXT-stored JSON to JSONB;
		// use a direct ::jsonb cast to parse the stored text as JSON.
		// This preserves the JSON structure (object stays object, array stays array)
		// and validates the content (throws if the stored text is not valid JSON).
		if (
			$new_base instanceof TypeJson
			&& $new_base->isNativeJson()
			&& (
				$old_base instanceof TypeString
				|| ($old_base instanceof TypeJson && !$old_base->isNativeJson())
			)
		) {
			$type_sql .= ' USING ' . $col_quoted . '::jsonb';
		}

		$statements[] = $type_sql . ';';

		// 2. Nullability sub-command (only when it changed)
		$old_nullable = $old_base->isNullable();
		$new_nullable = $new_base->isNullable();

		if ($old_nullable !== $new_nullable) {
			$statements[] = $new_nullable
				? 'ALTER TABLE ' . $t_quoted . ' ALTER COLUMN ' . $col_quoted . ' DROP NOT NULL;'
				: 'ALTER TABLE ' . $t_quoted . ' ALTER COLUMN ' . $col_quoted . ' SET NOT NULL;';
		}

		// 3. DEFAULT sub-command (only when it changed)
		$old_default = $this->getColumnDefaultChunk($old_column);
		$new_default = $this->getColumnDefaultChunk($new_column);

		if ($new_default !== $old_default) {
			if (null !== $new_default) {
				$statements[] = 'ALTER TABLE ' . $t_quoted . ' ALTER COLUMN ' . $col_quoted . ' SET ' . $new_default . ';';
			} else {
				$statements[] = 'ALTER TABLE ' . $t_quoted . ' ALTER COLUMN ' . $col_quoted . ' DROP DEFAULT;';
			}
		}

		return \implode("\n", $statements);
	}

	#[Override]
	protected function getDBCharsetChangeString(DBCharsetChanged $action): string
	{
		throw new RuntimeException('PostgreSQL does not support changing database charset via query.');
	}

	#[Override]
	protected function getDBCollateChangeString(DBCollateChanged $action): string
	{
		throw new RuntimeException('PostgreSQL does not support changing database collate via query.');
	}

	#[Override]
	protected function getTableCharsetChangeString(TableCharsetChanged $action): string
	{
		throw new RuntimeException('PostgreSQL does not support changing table charset via query.');
	}

	#[Override]
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
	#[Override]
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
	#[Override]
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
	#[Override]
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
	#[Override]
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
	#[Override]
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
	 * Extracts the SQL type expression for use in a PostgreSQL ALTER COLUMN TYPE clause.
	 *
	 * Strips the quoted column name and all constraints (NOT NULL, NULL, DEFAULT) from
	 * the full column definition string.  For auto-incremented columns (serial / bigserial),
	 * the underlying integer type (integer / bigint) is returned because serial and bigserial
	 * are pseudo-types not accepted by the TYPE clause in ALTER COLUMN.
	 *
	 * @param Column $column
	 *
	 * @return string e.g. 'bigint', 'varchar(20)', 'jsonb', 'boolean', 'integer'
	 */
	private function extractPostgresTypeExpression(Column $column): string
	{
		$base = $column->getType()->getBaseType();

		// serial/bigserial are pseudo-types; ALTER COLUMN TYPE requires real type names.
		if ($base->isAutoIncremented()) {
			return TypeBigint::NAME === $base->getName() ? 'bigint' : 'integer';
		}

		$full_def   = $this->getColumnDefinitionString($column);
		$col_quoted = $this->quoteIdentifier($column->getFullName());
		$prefix     = $col_quoted . ' ';

		$rest = \str_starts_with($full_def, $prefix)
			? \substr($full_def, \strlen($prefix))
			: $full_def;

		// Type expression ends just before the first constraint keyword.
		// PostgreSQL type names never contain these patterns as substrings.
		foreach ([' NOT NULL', ' NULL', ' DEFAULT'] as $marker) {
			$pos = \strpos($rest, $marker);

			if (false !== $pos) {
				return \substr($rest, 0, $pos);
			}
		}

		return $rest;
	}

	/**
	 * Returns the DEFAULT clause fragment for a column, or null if there is none.
	 *
	 * Delegates to defaultAndNullChunks() and picks the first part starting with
	 * "DEFAULT ".  Returns null when there is no default for the column.
	 *
	 * @param Column $column
	 *
	 * @return null|string e.g. "DEFAULT '{}'" or "DEFAULT NULL" or null
	 */
	private function getColumnDefaultChunk(Column $column): ?string
	{
		$parts = [];
		$this->defaultAndNullChunks($column, $parts);

		foreach ($parts as $part) {
			if (\str_starts_with((string) $part, 'DEFAULT ')) {
				return (string) $part;
			}
		}

		return null;
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
