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

namespace Gobl\DBAL\Queries;

use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Queries\Interfaces\QBInterface;
use Gobl\DBAL\Queries\Traits\QBCommonTrait;
use Gobl\DBAL\Queries\Traits\QBFromTrait;
use Gobl\DBAL\Queries\Traits\QBGroupByTrait;
use Gobl\DBAL\Queries\Traits\QBHavingTrait;
use Gobl\DBAL\Queries\Traits\QBJoinsTrait;
use Gobl\DBAL\Queries\Traits\QBLimitTrait;
use Gobl\DBAL\Queries\Traits\QBOrderByTrait;
use Gobl\DBAL\Queries\Traits\QBWhereTrait;
use Gobl\DBAL\Table;
use PDOStatement;

/**
 * Class QBSelect.
 */
class QBSelect implements QBInterface
{
	use QBCommonTrait;
	use QBFromTrait;
	use QBGroupByTrait;
	use QBHavingTrait;
	use QBJoinsTrait;
	use QBLimitTrait;
	use QBOrderByTrait;
	use QBWhereTrait;

	protected array $options_select = [];

	/**
	 * QBSelect constructor.
	 *
	 * @param RDBMSInterface $db
	 */
	public function __construct(protected RDBMSInterface $db) {}

	public function getType(): QBType
	{
		return QBType::SELECT;
	}

	/**
	 * Returns the raw SELECT expression list accumulated by {@see select()} calls.
	 *
	 * Each entry is a pre-qualified SQL column expression such as `u.user_id`,
	 * `u.user_id as uid`, or `u.*`.
	 *
	 * @return array
	 */
	public function getOptionsSelect(): array
	{
		return $this->options_select;
	}

	/**
	 * Executes a `COUNT(*)` query derived from this SELECT and returns the total row count.
	 *
	 * When `$preserve_limit` is `false`, both `max` (LIMIT) and `offset` (OFFSET) are
	 * temporarily removed before building the count query, then restored afterwards.
	 * This gives the true total number of matching rows regardless of pagination state.
	 *
	 * When `$preserve_limit` is `true`, the current LIMIT/OFFSET remain in effect,
	 * so the count reflects only the rows in the current page window.
	 *
	 * @param bool $preserve_limit `false` strips pagination (default use-case for total count);
	 *                             `true` keeps the current LIMIT/OFFSET in the count query
	 *
	 * @return int
	 */
	public function runTotalRowsCount(bool $preserve_limit): int
	{
		$offset = $this->options_limit_offset;
		$max    = $this->options_limit_max;

		if (!$preserve_limit) {
			$this->options_limit_offset = null;
			$this->options_limit_max    = null;
		}

		$sql = $this->db->getGenerator()
			->buildTotalRowCountQuery($this);

		$req = $this->db->execute($sql, $this->getBoundValues(), $this->getBoundValuesTypes());

		// restore limit
		$this->options_limit_offset = $offset;
		$this->options_limit_max    = $max;

		return $req->fetchColumn();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return PDOStatement
	 */
	public function execute(): PDOStatement
	{
		$sql    = $this->getSqlQuery();
		$values = $this->getBoundValues();
		$types  = $this->getBoundValuesTypes();

		return $this->db->select($sql, $values, $types);
	}

	/**
	 * Adds columns to the SELECT clause.
	 *
	 * @param null|string|Table $table_name_or_alias when provided, column references are qualified
	 *                                               with the resolved alias/table name
	 * @param array             $columns             column list; empty means `table.*` (select all).
	 *                                               Integer-keyed: `['col1', 'col2']` -> `alias.col1, alias.col2`.
	 *                                               String-keyed: `['alias' => 'col']` -> `alias.col AS alias`.
	 * @param bool              $auto_prefix         when `true` (default), bare column names are
	 *                                               prefixed with the resolved table alias;
	 *                                               when `false`, the raw table name is used as prefix
	 *
	 * @return $this
	 */
	public function select(string|Table|null $table_name_or_alias = null, array $columns = [], bool $auto_prefix = true): static
	{
		if (!empty($table_name_or_alias)) {
			$table_name = $this->resolveTable($table_name_or_alias)
				?->getFullName() ?? $table_name_or_alias;
			// when empty, we select all columns from the table
			if (empty($columns)) {
				$this->options_select[] = $this->fullyQualifiedNameArray($table_name_or_alias)[0];
			} elseif ($auto_prefix) {
				if (\is_int(\array_key_first($columns))) {
					$columns = $this->fullyQualifiedNameArray($table_name_or_alias, $columns);
				} else {
					$keys    = $this->fullyQualifiedNameArray($table_name_or_alias, \array_keys($columns));
					$values  = \array_values($columns);
					$columns = \array_combine($keys, $values);
				}

				foreach ($columns as $key => $value) {
					$this->options_select[] = \is_int($key) ? $value : $key . ' as ' . $value;
				}
			} else {
				foreach ($columns as $key => $value) {
					$this->options_select[] = \is_int($key)
						? $table_name . '.' . $value : $table_name . '.' . $key . ' as ' . $value;
				}
			}
		} else {
			foreach ($columns as $key => $value) {
				$this->options_select[] = \is_int($key) ? $value : $key . ' as ' . $value;
			}
		}

		return $this;
	}
}
