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

namespace Gobl\DBAL\Queries;

use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Queries\Interfaces\QBInterface;
use Gobl\DBAL\Queries\Traits\QBCommonTrait;
use Gobl\DBAL\Queries\Traits\QBSetColumnsValuesTrait;
use Gobl\DBAL\Table;
use InvalidArgumentException;
use LogicException;
use PDOStatement;
use PHPUtils\Str;

/**
 * Class QBInsert.
 */
final class QBInsert implements QBInterface
{
	use QBCommonTrait;
	use QBSetColumnsValuesTrait;

	protected ?string $options_table = null;

	/**
	 * @var string[]
	 */
	protected array $options_columns_names = [];

	/**
	 * @var array<int, string[]>
	 */
	protected array $options_values_params = [];

	protected bool $auto_prefix = true;

	/**
	 * ON CONFLICT / INSERT IGNORE options.
	 *
	 * @var array{action?: 'ignore'|'update', conflict_columns?: string[], update_columns?: string[]}
	 */
	protected array $options_on_conflict = [];

	/**
	 * RETURNING options.
	 *
	 * @var array{enabled: bool, columns: string[]}
	 */
	protected array $options_returning = ['enabled' => false, 'columns' => ['*']];

	/**
	 * QBInsert constructor.
	 *
	 * @param RDBMSInterface $db
	 */
	public function __construct(protected RDBMSInterface $db) {}

	/**
	 * {@inheritDoc}
	 *
	 * @return false|string
	 */
	public function execute(): false|string
	{
		$sql    = $this->getSqlQuery();
		$values = $this->getBoundValues();
		$types  = $this->getBoundValuesTypes();

		return $this->db->insert($sql, $values, $types);
	}

	public function getType(): QBType
	{
		return QBType::INSERT;
	}

	/**
	 * Specify the table to insert into.
	 *
	 * @param string|Table $table
	 * @param bool         $auto_prefix
	 *
	 * @return $this
	 */
	public function into(string|Table $table, bool $auto_prefix = true): self
	{
		$table_name = $this->resolveTable($table)
			?->getFullName() ?? $table;
		$this->options_table = $table_name;
		$this->auto_prefix   = $auto_prefix;

		return $this;
	}

	/**
	 * Adds one or more rows to insert.
	 *
	 * Auto-detects between single-row and multi-row input:
	 * - **String-keyed array** (e.g. `['col' => 'val', ...]`) -> treated as a single row
	 *   and forwarded to {@see singleValue()}.
	 * - **Integer-keyed array** (e.g. `[['col' => 'val'], ['col' => 'val2']]`) -> each element
	 *   is forwarded as a separate row to {@see singleValue()}.
	 *
	 * @param array<int, array<string, mixed>>|array<string, mixed> $values single row map or list of row maps for multi insert
	 *
	 * @return $this
	 */
	public function values(array $values): self
	{
		$key = \array_keys($values)[0];

		if (\is_string($key)) {
			/** @var array<string, mixed> $values */
			$this->singleValue($values);
		} else {
			foreach ($values as $value) {
				$this->singleValue($value);
			}
		}

		return $this;
	}

	/**
	 * Appends a single row to the INSERT statement.
	 *
	 * Column set consistency is enforced: if rows have already been added, the column set of
	 * `$value` must exactly match the column set of the first row; mismatches throw
	 * `InvalidArgumentException`. This prevents malformed multi-row inserts.
	 *
	 * Throws `LogicException` if {@see into()} has not been called yet.
	 *
	 * @param array<string, mixed> $value column -> value map for a single row
	 *
	 * @return $this
	 *
	 * @throws InvalidArgumentException when the column set differs from previous rows
	 * @throws LogicException           when `into()` has not been called first
	 */
	public function singleValue(array $value): self
	{
		if (!isset($this->options_table)) {
			throw new LogicException(\sprintf('You must call "%s" method first', Str::callableName([$this, 'into'])));
		}

		$map           = $this->bindColumnsValuesForInsertOrUpdate($this->options_table, $value, $this->auto_prefix);
		$columns       = \array_keys($map);
		$values_params = \array_values($map);

		if (empty($this->options_columns_names)) {
			$this->options_columns_names = $columns;
		} elseif ($this->options_columns_names !== $columns) {
			throw new InvalidArgumentException(
				\sprintf(
					'Invalid columns for multi insert, expected "(%s)" got "(%s)"',
					\implode(', ', $this->options_columns_names),
					\implode(', ', $columns)
				)
			);
		}

		$this->options_values_params[] = $values_params;

		return $this;
	}

	/**
	 * Gets the table to insert into.
	 *
	 * @return null|string
	 */
	public function getOptionsTable(): ?string
	{
		return $this->options_table;
	}

	/**
	 * Marks this INSERT to silently skip the row when a unique/primary-key conflict occurs.
	 *
	 * - MySQL:      emits `INSERT IGNORE INTO ...`
	 * - PostgreSQL: appends `ON CONFLICT DO NOTHING`
	 * - SQLite:     emits `INSERT OR IGNORE INTO ...`
	 *
	 * @return $this
	 */
	public function ignoreOnConflict(): static
	{
		$this->options_on_conflict = ['action' => 'ignore'];

		return $this;
	}

	/**
	 * Marks this INSERT to update columns when a unique/primary-key conflict occurs (upsert).
	 *
	 * @param string[] $conflict_columns Columns defining the conflict target.
	 *                                   Required for PostgreSQL/SQLite; ignored by MySQL (uses unique/PK indexes).
	 * @param string[] $update_columns   Columns to update on conflict. Empty = update all inserted columns.
	 *
	 * - MySQL:      appends `ON DUPLICATE KEY UPDATE col = VALUES(col) ...`
	 * - PostgreSQL: appends `ON CONFLICT (cols) DO UPDATE SET col = EXCLUDED.col ...`
	 * - SQLite:     appends `ON CONFLICT (cols) DO UPDATE SET col = EXCLUDED.col ...` (requires SQLite >= 3.24.0)
	 *
	 * @return $this
	 */
	public function doUpdateOnConflict(array $conflict_columns = [], array $update_columns = []): static
	{
		$this->options_on_conflict = [
			'action'           => 'update',
			'conflict_columns' => $conflict_columns,
			'update_columns'   => $update_columns,
		];

		return $this;
	}

	/**
	 * Gets the on-conflict options.
	 *
	 * @return array{action?: 'ignore'|'update', conflict_columns?: string[], update_columns?: string[]}
	 */
	public function getOptionsOnConflict(): array
	{
		return $this->options_on_conflict;
	}

	/**
	 * Marks this INSERT to return columns from the inserted rows (requires PostgreSQL or SQLite >= 3.35.0).
	 *
	 * MySQL does not support RETURNING; calling this method on a MySQL connection will throw at query-generation time.
	 *
	 * @param string|string[] $columns Column names, or `'*'` for all columns. Defaults to `['*']`.
	 *
	 * @return $this
	 */
	public function returning(array|string $columns = ['*']): static
	{
		$this->options_returning = [
			'enabled' => true,
			'columns' => (array) $columns,
		];

		return $this;
	}

	/**
	 * Gets the RETURNING options.
	 *
	 * @return array{enabled: bool, columns: string[]}
	 */
	public function getOptionsReturning(): array
	{
		return $this->options_returning;
	}

	/**
	 * Executes the query and returns a {@see PDOStatement} for iterating the inserted rows.
	 *
	 * Call this instead of {@see execute()} when you want to read back the affected rows via RETURNING.
	 *
	 * @return PDOStatement
	 */
	public function executeReturning(): PDOStatement
	{
		$sql    = $this->getSqlQuery();
		$values = $this->getBoundValues();
		$types  = $this->getBoundValuesTypes();

		return $this->db->execute($sql, $values, $types);
	}

	/**
	 * Gets the inserted columns names.
	 *
	 * @return string[]
	 */
	public function getOptionsColumnsNames(): array
	{
		return $this->options_columns_names;
	}

	/**
	 * Gets the inserted values params.
	 *
	 * @return array<int, string[]>
	 */
	public function getOptionsValuesParams(): array
	{
		return $this->options_values_params;
	}
}
