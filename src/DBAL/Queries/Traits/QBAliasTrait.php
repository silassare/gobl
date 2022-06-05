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

namespace Gobl\DBAL\Queries\Traits;

use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Table;

/**
 * Trait QBAliasTrait.
 */
trait QBAliasTrait
{
	/**
	 * @psalm-var array<string, string>
	 *
	 * @var string[]
	 */
	private array $aliases_to_tables_map = [];

	public function resolveTableFullName(string $table_name_or_alias): ?string
	{
		$table_name = $this->getAliasTable($table_name_or_alias) ?? $table_name_or_alias;

		if ($table = $this->db->getTable($table_name)) {
			return $table->getFullName();
		}

		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function isDeclaredAlias(string $str): bool
	{
		return isset($this->aliases_to_tables_map[$str]);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getAliasTable(string $alias): ?string
	{
		return $this->aliases_to_tables_map[$alias] ?? null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function prefixColumnsString(string $table, ...$columns): string
	{
		return \implode(' , ', $this->prefixColumnsArray($table, $columns, true));
	}

	/**
	 * {@inheritDoc}
	 */
	public function prefixColumnsArray(string $table, array $columns, bool $absolute = false): array
	{
		$alias = null;
		$list  = [];

		if ($tmp = $this->getAliasTable($table)) {
			$alias = $table;
			$table = $tmp;
		}

		$t    = $this->db->getTableOrFail($table);
		$head = $alias ?? $t->getFullName();

		foreach ($columns as $col_name) {
			$col           = $t->getColumnOrFail($col_name);
			$col_full_name = $col->getFullName();
			$list[]        = ($absolute ? $head . '.' . $col_full_name : $col_full_name);
		}

		return $list;
	}

	/**
	 * {@inheritDoc}
	 */
	public function alias(array $aliases_to_tables_map): static
	{
		foreach ($aliases_to_tables_map as $alias => $table_name) {
			$table_name = $this->resolveTableFullName($table_name) ?? $table_name;
			$this->useAlias($table_name, $alias);
		}

		return $this;
	}

	/**
	 * Asserts that a given string is an alias.
	 *
	 * @param string $str
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	protected function assertAliasExists(string $str): void
	{
		if (!isset($this->aliases_to_tables_map[$str])) {
			throw new DBALException(\sprintf('alias "%s" is not defined.', $str));
		}
	}

	/**
	 * {@inheritDoc}
	 */

	/**
	 * @param string $table_name
	 * @param string $alias
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	protected function useAlias(string $table_name, string $alias): void
	{
		if (empty($alias) || !\preg_match(Table::ALIAS_REG, $alias)) {
			throw new DBALException(\sprintf('alias "%s" should match: %s', $alias, Table::ALIAS_PATTERN));
		}

		if ($table = $this->db->getTable($table_name)) {
			$table_name = $table->getFullName();
		}

		if (isset($this->aliases_to_tables_map[$alias])) {
			if ($this->aliases_to_tables_map[$alias] !== $table_name) {
				throw new DBALException(\sprintf(
					'alias "%s" is already in use by "%s".',
					$alias,
					$this->aliases_to_tables_map[$alias]
				));
			}
		} else {
			$this->aliases_to_tables_map[$alias] = $table_name;
		}
	}
}
