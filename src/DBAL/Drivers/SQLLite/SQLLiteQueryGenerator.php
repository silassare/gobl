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
use Gobl\DBAL\Interfaces\RDBMSInterface;
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
	 */
	protected function quoteIdentifier(string $name): string
	{
		return '"' . \str_replace('"', '""', $name) . '"';
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
}
