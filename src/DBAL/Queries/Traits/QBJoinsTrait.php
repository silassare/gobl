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

use Gobl\DBAL\Builders\JoinBuilder;
use Gobl\DBAL\Queries\JoinType;
use Gobl\DBAL\Table;

/**
 * Trait QBJoinsTrait.
 *
 * @see https://learnsql.com/blog/joins-vs-multiple-tables-in-from/
 */
trait QBJoinsTrait
{
	/**
	 * Map table alias to declared join builder instances.
	 *
	 * @var array<string, JoinBuilder[]>
	 */
	protected array $options_joins = [];

	/**
	 * @return array<string, JoinBuilder[]>
	 */
	public function getOptionsJoins(): array
	{
		return $this->options_joins;
	}

	/**
	 * Inner join.
	 *
	 * Selects records that have matching values in both tables.
	 *
	 * @param \Gobl\DBAL\Table|string $table
	 * @param null|string             $alias
	 *
	 * @return JoinBuilder
	 */
	public function innerJoin(
		string|Table $table,
		string $alias = null,
	): JoinBuilder {
		return $this->join(JoinType::INNER, $table, $alias);
	}

	/**
	 * Left join.
	 *
	 * Selects all records from the left table, and the matched records from the right table.
	 *
	 * @param \Gobl\DBAL\Table|string $table
	 * @param null|string             $alias
	 *
	 * @return JoinBuilder
	 */
	public function leftJoin(
		string|Table $table,
		string $alias = null,
	): JoinBuilder {
		return $this->join(JoinType::LEFT, $table, $alias);
	}

	/**
	 * Right join.
	 *
	 * Selects all records from the right table, and the matched records from the left table.
	 *
	 * @param \Gobl\DBAL\Table|string $table
	 * @param null|string             $alias
	 *
	 * @return JoinBuilder
	 */
	public function rightJoin(
		string|Table $table,
		string $alias = null,
	): JoinBuilder {
		return $this->join(JoinType::RIGHT, $table, $alias);
	}

	/**
	 * Creates a join query.
	 *
	 * @param JoinType                $type
	 * @param \Gobl\DBAL\Table|string $firstTable
	 * @param null|string             $alias
	 *
	 * @return JoinBuilder
	 */
	public function join(
		JoinType $type,
		string|Table $firstTable,
		?string $alias = null,
	): JoinBuilder {
		if ($firstTable instanceof Table) {
			$resolved_table_name = $firstTable->getFullName();
		} else {
			/** @var string $firstTable */
			$resolved_table_name = $this->getAliasTable($firstTable);

			// argument is an alias
			if ($resolved_table_name) {
				$alias = $alias ?? $firstTable;
			} else {
				$resolved_table_name = $this->resolveTable($firstTable)
					?->getFullName() ?? $firstTable;
			}
		}

		if ($alias) {
			// this will throw an exception if the alias already exists
			// and is not for the same table
			$this->alias($resolved_table_name, $alias);
		} else {
			$alias = $this->getMainAlias($resolved_table_name);
		}

		$jb                            = new JoinBuilder($type, $this, $resolved_table_name, $alias);
		$this->options_joins[$alias][] = $jb;

		return $jb;
	}
}
