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
use Gobl\DBAL\Filters\Filters;

/**
 * Trait QBJoinsTrait.
 */
trait QBJoinsTrait
{
	/**
	 * @psalm-var array<string, array<array{'type': string, 'secondTable': string, 'secondTableAlias': string,
	 *            'condition':null|Filters|string}>>
	 */
	protected array $options_joins = [];

	/**
	 * @return array
	 */
	public function getOptionsJoins(): array
	{
		return $this->options_joins;
	}

	/**
	 * Inner join.
	 *
	 * @param string              $firstTableAlias
	 * @param string              $secondTable
	 * @param string              $secondTableAlias
	 * @param null|Filters|string $condition
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 *
	 * @return $this
	 */
	public function innerJoin(
		string $firstTableAlias,
		string $secondTable,
		string $secondTableAlias,
		null|Filters|string $condition = null
	): static {
		return $this->join('INNER', $firstTableAlias, $secondTable, $secondTableAlias, $condition);
	}

	/**
	 * Left join.
	 *
	 * @param string              $firstTableAlias
	 * @param string              $secondTable
	 * @param string              $secondTableAlias
	 * @param null|Filters|string $condition
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 *
	 * @return $this
	 */
	public function leftJoin(
		string $firstTableAlias,
		string $secondTable,
		string $secondTableAlias,
		null|Filters|string $condition = null
	): static {
		return $this->join('LEFT', $firstTableAlias, $secondTable, $secondTableAlias, $condition);
	}

	/**
	 * Right join.
	 *
	 * @param string              $firstTableAlias
	 * @param string              $secondTable
	 * @param string              $secondTableAlias
	 * @param null|Filters|string $condition
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 *
	 * @return $this
	 */
	public function rightJoin(
		string $firstTableAlias,
		string $secondTable,
		string $secondTableAlias,
		null|Filters|string $condition = null
	): static {
		return $this->join('RIGHT', $firstTableAlias, $secondTable, $secondTableAlias, $condition);
	}

	/**
	 * @param string              $type
	 * @param string              $firstTableAlias
	 * @param string              $secondTable
	 * @param string              $secondTableAlias
	 * @param null|Filters|string $condition
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 *
	 * @return $this
	 */
	private function join(
		string $type,
		string $firstTableAlias,
		string $secondTable,
		string $secondTableAlias,
		null|Filters|string $condition = null
	): static {
		$this->assertAliasExists($firstTableAlias);

		/** @var string $from_table */
		$from_table = $this->getAliasTable($firstTableAlias);

		if (!isset($this->options_from[$from_table])) {
			throw new DBALException(\sprintf(
				'The table "%s" alias "%s" is not in the "from" part of the query.',
				$from_table,
				$firstTableAlias
			));
		}

		$secondTable = $this->resolveTableFullName($secondTable) ?? $secondTable;

		$this->useAlias($secondTable, $secondTableAlias);

		$this->options_joins[$firstTableAlias][] = [
			'type'             => $type,
			'secondTable'      => $secondTable,
			'secondTableAlias' => $secondTableAlias,
			'condition'        => $condition,
		];

		return $this;
	}
}
