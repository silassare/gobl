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

namespace Gobl\DBAL\Relations;

use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\DBAL\Relations\Interfaces\LinkInterface;
use Gobl\DBAL\Table;
use Gobl\ORM\ORMEntity;

/**
 * Class LinkThrough.
 */
final class LinkThrough extends Link
{
	private LinkInterface $host_to_pivot_link;
	private LinkInterface $pivot_to_target_link;

	/**
	 * LinkThrough constructor.
	 *
	 * @param RDBMSInterface $rdbms
	 * @param Table          $host_table
	 * @param Table          $target_table
	 * @param Table          $pivot_table
	 * @param array{
	 *          filters?: array,
	 *          host_to_pivot?: array,
	 *          pivot_to_target?: array
	 *       } $options
	 *
	 * @throws DBALException
	 */
	public function __construct(
		private readonly RDBMSInterface $rdbms,
		Table $host_table,
		Table $target_table,
		private readonly Table $pivot_table,
		array $options = [],
	) {
		parent::__construct(LinkType::THROUGH, $host_table, $target_table, $options);

		$htp_options = $this->options['host_to_pivot'] ?? null;
		$ptt_options = $this->options['pivot_to_target'] ?? null;

		if (empty($htp_options)) {
			if ($this->pivot_table->hasDefaultForeignKeyConstraint($this->host_table)) {
				$columns_map = $this->pivot_table->getDefaultForeignKeyConstraintFrom($this->host_table)
					->getColumnsMapping();
				$htp_options = [
					'type'    => LinkType::COLUMNS->value,
					'columns' => \array_flip($columns_map),
				];
			} elseif ($this->host_table->hasDefaultForeignKeyConstraint($this->pivot_table)) {
				$columns_map = $this->host_table->getDefaultForeignKeyConstraintFrom($this->pivot_table)
					->getColumnsMapping();
				$htp_options = [
					'type'    => LinkType::COLUMNS->value,
					'columns' => $columns_map,
				];
			} else {
				throw new DBALException(\sprintf(
					'Auto linking through table "%s" from table "%s" to table "%s" failed, the pivot table "%s" has no foreign columns from or to "%s".',
					$this->pivot_table->getName(),
					$this->host_table->getName(),
					$this->target_table->getName(),
					$this->pivot_table->getName(),
					$this->host_table->getName()
				));
			}
		}

		if (empty($ptt_options)) {
			if ($this->pivot_table->hasDefaultForeignKeyConstraint($this->target_table)) {
				$columns_map = $this->pivot_table->getDefaultForeignKeyConstraintFrom($this->target_table)
					->getColumnsMapping();
				$ptt_options = [
					'type'    => LinkType::COLUMNS->value,
					'columns' => $columns_map,
				];
			} elseif ($this->target_table->hasDefaultForeignKeyConstraint($this->pivot_table)) {
				$columns_map = $this->target_table->getDefaultForeignKeyConstraintFrom($this->pivot_table)
					->getColumnsMapping();
				$ptt_options = [
					'type'    => LinkType::COLUMNS->value,
					'columns' => \array_flip($columns_map),
				];
			} else {
				throw new DBALException(\sprintf(
					'Auto linking through table "%s" from table "%s" to table "%s" failed, the pivot table "%s" has no foreign columns from or to "%s".',
					$this->pivot_table->getName(),
					$this->host_table->getName(),
					$this->target_table->getName(),
					$this->pivot_table->getName(),
					$this->target_table->getName()
				));
			}
		}

		$this->host_to_pivot_link   = $this->link($this->host_table, $this->pivot_table, $htp_options);
		$this->pivot_to_target_link = $this->link($this->pivot_table, $this->target_table, $ptt_options);
	}

	/**
	 * Gets the pivot table.
	 *
	 * @return Table
	 */
	public function getPivotTable(): Table
	{
		return $this->pivot_table;
	}

	/**
	 * Gets the link between the host table and the pivot table.
	 *
	 * @return LinkInterface
	 */
	public function getHostToThroughLink(): LinkInterface
	{
		return $this->host_to_pivot_link;
	}

	/**
	 * Gets the link between the pivot table and the target table.
	 *
	 * @return LinkInterface
	 */
	public function getThroughToTargetLink(): LinkInterface
	{
		return $this->pivot_to_target_link;
	}

	/**
	 * {@inheritDoc}
	 */
	public function fillRelation(ORMEntity $host_entity, array &$target_data = []): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function runLinkTypeApplyLogic(QBSelect $target_qb, ?ORMEntity $host_entity = null): bool
	{
		if ($this->pivot_to_target_link->apply($target_qb)) {
			return $this->host_to_pivot_link->apply($target_qb, $host_entity);
		}

		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'type'        => $this->type->value,
			'pivot_table' => $this->pivot_table->getName(),
		] + $this->options;
	}

	/**
	 * Creates a link between two tables.
	 *
	 * @throws DBALException
	 */
	private function link(Table $host_table, Table $target_table, array $options): LinkInterface
	{
		$link = Relation::createLink($this->rdbms, $host_table, $target_table, $options);

		if ($link instanceof self) {
			throw new DBALException('The link type "through" cannot be nested. Keep it simple.');
		}

		return $link;
	}
}
