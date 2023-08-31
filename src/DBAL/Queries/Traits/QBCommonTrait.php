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

use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Table;
use PDO;
use Throwable;

/**
 * Trait QBCommonTrait.
 */
trait QBCommonTrait
{
	use QBAliasTrait;
	use QBBindTrait;

	/**
	 * Help var_dump().
	 *
	 * @return array
	 */
	public function __debugInfo()
	{
		return ['instance_of' => static::class];
	}

	/**
	 * Magic method __toString.
	 *
	 * @return string
	 */
	public function __toString(): string
	{
		return $this->getSqlQuery();
	}

	/**
	 * Disable clone.
	 */
	private function __clone()
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function fullyQualifiedName(string|Table $table_name_or_alias, string $column): string
	{
		return $this->fullyQualifiedNameArray($table_name_or_alias, [$column])[0];
	}

	/**
	 * {@inheritDoc}
	 */
	public function fullyQualifiedNameArray(string|Table $table_name_or_alias, array $columns = []): array
	{
		$table = $this->resolveTable($table_name_or_alias);

		/** @var string $table_name */
		$table_name = $table?->getFullName() ?? $table_name_or_alias;

		// if the provided argument is an alias, use it as the head
		if (\is_string($table_name_or_alias) && $this->isDeclaredAlias($table_name_or_alias)) {
			$head = $table_name_or_alias;
		} else {
			// try get the main alias or fallback to the table name / provided argument
			try {
				$head = $this->getMainAlias($table_name);
			} catch (Throwable) {
				$head = $table_name;
			}
		}

		$out = [];

		if (empty($columns)) {
			return [$head . '.*'];
		}

		foreach ($columns as $column) {
			if ($table) {
				$column = $table->getColumnOrFail($column)
					->getFullName();
			}

			$out[] = $head . '.' . $column;
		}

		return $out;
	}

	/**
	 * Returns the RDBMS.
	 *
	 * @return \Gobl\DBAL\Interfaces\RDBMSInterface
	 */
	public function getRDBMS(): RDBMSInterface
	{
		return $this->db;
	}

	/**
	 * Returns query string to be executed by the rdbms.
	 *
	 * @return string
	 */
	public function getSqlQuery(): string
	{
		return $this->db->getGenerator()
			->buildQuery($this);
	}

	/**
	 * Alias for {@see \PDO::quote()}.
	 *
	 * @param int   $type
	 * @param mixed $value
	 *
	 * @return string
	 */
	public function quote(mixed $value, int $type = PDO::PARAM_STR): string
	{
		return $this->db->getConnection()
			->quote($value, $type);
	}

	/**
	 * {@inheritDoc}
	 */
	public function resolveTable(string|Table $table_name_or_alias): ?Table
	{
		/** @var null|Table $resolved_table */
		$resolved_table = null;

		if ($table_name_or_alias instanceof Table) {
			$resolved_table = $table_name_or_alias;
		}

		if (!$resolved_table) {
			// maybe a table name or full name
			$resolved_table = $this->db->getTable($table_name_or_alias);

			// check if it is an alias
			if ($aliased_table_name = $this->getAliasTable($table_name_or_alias)) {
				$resolved_table = $this->db->getTable($aliased_table_name);
			}
		}

		return $resolved_table;
	}
}
