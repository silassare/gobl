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
use Gobl\DBAL\Indexes\Index;
use Gobl\DBAL\Indexes\IndexType;
use Gobl\DBAL\DbConfig;
use Gobl\DBAL\Diff\Actions\DBCharsetChanged;
use Gobl\DBAL\Diff\Actions\DBCollateChanged;
use Gobl\DBAL\Diff\Actions\TableCharsetChanged;
use Gobl\DBAL\Diff\Actions\TableCollateChanged;
use Gobl\DBAL\Drivers\SQLQueryGeneratorBase;
use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Types\TypeJSON;
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

	/**
	 * {@inheritDoc}
	 */
	public function wrapDatabaseDefinitionQuery(string $query): string
	{
		return $query;
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
	 */
	protected function dbQueryTemplate(): string
	{
		return 'postgresql_db';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function createTableQueryTemplate(): string
	{
		return 'postgresql_create_table';
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

	/**
	 * {@inheritDoc}
	 */
	protected function getBoolColumnDefinition(Column $column): string
	{
		$column_name = $column->getFullName();
		$sql         = [$this->quoteIdentifier($column_name) . ' boolean'];

		$this->defaultAndNullChunks($column, $sql);

		return \implode(' ', $sql);
	}

	/**
	 * {@inheritDoc}
	 */
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

	/**
	 * {@inheritDoc}
	 */
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

		if (!$base->getOption('native_json', false)) {
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
	 * PostgreSQL JSON path extraction:
	 *   - single-level: col->>'key'
	 *   - multi-level:  col#>>'{key1,key2}'
	 */
	public function getJsonPathExpression(string $col_sql_expression, array $json_path): string
	{
		if (1 === \count($json_path)) {
			return $col_sql_expression . "->>'" . \addslashes($json_path[0]) . "'";
		}

		$path_literal = \implode(',', \array_map('strval', $json_path));

		return $col_sql_expression . "#>>'" . '{' . $path_literal . '}' . "'";
	}

	/**
	 * {@inheritDoc}
	 */
	protected function getDBCharsetChangeString(DBCharsetChanged $action): string
	{
		throw new RuntimeException('PostgreSQL does not support changing database charset via query.');
	}

	/**
	 * {@inheritDoc}
	 */
	protected function getDBCollateChangeString(DBCollateChanged $action): string
	{
		throw new RuntimeException('PostgreSQL does not support changing database collate via query.');
	}

	/**
	 * {@inheritDoc}
	 */
	protected function getTableCharsetChangeString(TableCharsetChanged $action): string
	{
		throw new RuntimeException('PostgreSQL does not support changing table charset via query.');
	}

	/**
	 * {@inheritDoc}
	 */
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
}
