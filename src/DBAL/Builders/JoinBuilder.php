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

namespace Gobl\DBAL\Builders;

use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Filters\Filters;
use Gobl\DBAL\Queries\Interfaces\QBInterface;
use Gobl\DBAL\Queries\JoinType;
use Gobl\DBAL\Table;

/**
 * Class JoinBuilder.
 */
final class JoinBuilder
{
	private null|string $table_to_join               = null;
	private null|string $table_to_join_alias         = null;
	private null|Filters|string $condition           = null;

	/**
	 * JoinBuilder constructor.
	 *
	 * @param JoinType    $type
	 * @param QBInterface $qb
	 * @param string      $table
	 * @param string      $table_alias
	 */
	public function __construct(
		private readonly JoinType $type,
		private readonly QBInterface $qb,
		private readonly string $table,
		private readonly string $table_alias,
	) {}

	/**
	 * Sets the table to join.
	 *
	 * @param \Gobl\DBAL\Table|string $table_to_join
	 * @param null|string             $alias
	 *
	 * @return $this
	 */
	public function to(string|Table $table_to_join, string $alias = null): self
	{
		$this->table_to_join = $this->qb->resolveTable($table_to_join)
			?->getFullName() ?? $table_to_join;

		if ($alias) {
			$this->qb->alias($this->table_to_join, $alias);
		}

		$this->table_to_join_alias = $alias ?? $this->qb->getMainAlias($this->table_to_join, true);

		return $this;
	}

	/**
	 * Sets the join condition.
	 *
	 * @param \Gobl\DBAL\Filters\Filters|string $condition
	 *
	 * @return $this
	 */
	public function on(Filters|string $condition): self
	{
		$this->condition = $condition;

		return $this;
	}

	/**
	 * Returns the join type.
	 *
	 * @return JoinType
	 */
	public function getType(): JoinType
	{
		return $this->type;
	}

	/**
	 * Returns the join options.
	 *
	 * If the join is incomplete, an exception is thrown.
	 *
	 * @return array{table_to_join: string, table_to_join_alias: string, condition: null|Filters|string}
	 */
	public function getOptions(): array
	{
		if (!$this->table_to_join || !$this->table_to_join_alias) {
			throw new DBALRuntimeException(\sprintf(
				'Incomplete join clause for table "%s" aliased by "%s".',
				$this->table,
				$this->table_alias
			));
		}

		return [
			'table_to_join'       => $this->table_to_join,
			'table_to_join_alias' => $this->table_to_join_alias,
			'condition'           => $this->condition,
		];
	}
}
