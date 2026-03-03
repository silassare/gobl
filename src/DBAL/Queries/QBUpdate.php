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
use Gobl\DBAL\Queries\Traits\QBLimitTrait;
use Gobl\DBAL\Queries\Traits\QBOrderByTrait;
use Gobl\DBAL\Queries\Traits\QBSetColumnsValuesTrait;
use Gobl\DBAL\Queries\Traits\QBWhereTrait;
use LogicException;
use PDOStatement;
use PHPUtils\Str;

/**
 * Class QBUpdate.
 */
final class QBUpdate implements QBInterface
{
	use QBCommonTrait;
	use QBLimitTrait;
	use QBOrderByTrait;
	use QBSetColumnsValuesTrait;
	use QBWhereTrait;

	protected ?string $options_table = null;

	/**
	 * Map of columns and bound parameters.
	 *
	 * @var array<string, string>
	 */
	protected array $options_columns              = [];
	protected ?string $options_update_table_alias = '';

	/**
	 * RETURNING options.
	 *
	 * @var array{enabled: bool, columns: string[]}
	 */
	protected array $options_returning = ['enabled' => false, 'columns' => ['*']];

	/**
	 * QBUpdate constructor.
	 *
	 * @param RDBMSInterface $db
	 */
	public function __construct(protected RDBMSInterface $db) {}

	/**
	 * {@inheritDoc}
	 *
	 * @return int
	 */
	public function execute(): int
	{
		$sql    = $this->getSqlQuery();
		$values = $this->getBoundValues();
		$types  = $this->getBoundValuesTypes();

		return $this->db->update($sql, $values, $types);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getType(): QBType
	{
		return QBType::UPDATE;
	}

	/**
	 * Sets the table to update.
	 *
	 * When `$alias` is given it is registered as both the alias and the main alias for the
	 * table, and stored in `$options_update_table_alias` for use by the SQL generator
	 * when building SET and WHERE expressions. Calling `update()` a second time replaces
	 * the previously set table.
	 *
	 * @param string      $table table name or full name
	 * @param null|string $alias optional alias for the table in the generated SQL
	 *
	 * @return $this
	 */
	public function update(string $table, ?string $alias = null): self
	{
		$this->options_table = $this->resolveTable($table)
			?->getFullName() ?? $table;

		if (!empty($alias)) {
			$this->alias($this->options_table, $alias);
			$this->options_update_table_alias = $alias;
		}

		return $this;
	}

	/**
	 * Gets the table to update alias.
	 *
	 * @return null|string
	 */
	public function getOptionsUpdateTableAlias(): ?string
	{
		return $this->options_update_table_alias;
	}

	/**
	 * Gets the table to update.
	 *
	 * @return null|string
	 */
	public function getOptionsTable(): ?string
	{
		return $this->options_table;
	}

	/**
	 * Gets the map of columns and bound parameters.
	 *
	 * @return array<string, string>
	 */
	public function getOptionsColumns(): array
	{
		return $this->options_columns;
	}

	/**
	 * Marks this UPDATE to return columns from the updated rows (requires PostgreSQL or SQLite >= 3.35.0).
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
	 * Executes the query and returns a {@see PDOStatement} for iterating the updated rows.
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
	 * Sets the columns and values for the SET clause.
	 *
	 * Each entry in `$values` is bound as a named parameter and the column→parameter
	 * map is stored for the SQL generator. `QBExpression` values are passed through
	 * as raw SQL fragments without binding.
	 *
	 * Throws `LogicException` if {@see update()} has not been called first.
	 *
	 * @param array $values      the column → value map (or column → `QBExpression` for raw SQL)
	 * @param bool  $auto_prefix when `true`, column names are automatically prefixed with
	 *                           the table's column prefix before binding
	 *
	 * @return $this
	 *
	 * @throws LogicException when `update()` has not been called first
	 */
	public function set(array $values, bool $auto_prefix = true): self
	{
		if (!isset($this->options_table)) {
			throw new LogicException(\sprintf('You must call "%s" method first', Str::callableName([$this, 'update'])));
		}

		$this->options_columns = $this->bindColumnsValuesForInsertOrUpdate($this->options_table, $values, $auto_prefix);

		return $this;
	}
}
