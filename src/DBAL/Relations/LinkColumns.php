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
use Gobl\DBAL\Filters\Filters;
use Gobl\DBAL\Operator;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\DBAL\Table;
use Gobl\ORM\ORMEntity;

/**
 * Class LinkColumns.
 */
final class LinkColumns extends Link
{
	private array $columns_mapping;

	/**
	 * LinkColumns constructor.
	 *
	 * Use columns to link the tables.
	 * If no columns are given, it will try to auto detect using the foreign key.
	 * If no foreign key is found, an exception will be thrown.
	 *
	 * @param Table $host_table
	 * @param Table $target_table
	 * @param array{
	 *         columns?: array<string,string>,
	 *         filters?: array
	 *      } $options
	 *
	 * @throws DBALException
	 */
	public function __construct(
		Table $host_table,
		Table $target_table,
		array $options = []
	) {
		parent::__construct(LinkType::COLUMNS, $host_table, $target_table, $options);

		$this->columns_mapping = $this->options['columns'] ?? [];

		if (empty($this->columns_mapping)) {
			if ($this->target_table->hasDefaultForeignKeyConstraint($this->host_table)) {
				$this->columns_mapping = \array_flip($this->target_table->getDefaultForeignKeyConstraintFrom($this->host_table)
					->getColumnsMapping());
			} elseif ($this->host_table->hasDefaultForeignKeyConstraint($this->target_table)) {
				$this->columns_mapping = $this->host_table->getDefaultForeignKeyConstraintFrom($this->target_table)
					->getColumnsMapping();
			} else {
				throw new DBALException(\sprintf(
					'There is no columns to link the table "%s" to the table "%s".',
					$this->host_table->getName(),
					$this->target_table->getName()
				));
			}
		} else {
			$cols = [];

			foreach ($this->columns_mapping as $host_column => $target_column) {
				$f_col = $this->host_table->getColumnOrFail($host_column);
				$t_col = $this->target_table->getColumnOrFail($target_column);

				$cols[$f_col->getFullName()] = $t_col->getFullName();
			}

			$this->columns_mapping = $cols;
		}
	}

	/**
	 * Gets the columns mapping.
	 */
	public function getColumnsMapping(): array
	{
		return $this->columns_mapping;
	}

	/**
	 * {@inheritDoc}
	 */
	public function runLinkTypeApplyLogic(QBSelect $target_qb, ?ORMEntity $host_entity = null): bool
	{
		if ($host_entity) {
			// we use array to not pollute the query builder filters
			// until we are sure the relation is satisfied by the host entity

			$filters = [];

			foreach ($this->columns_mapping as $host_column => $target_column) {
				$value = $host_entity->{$host_column};

				// a null value makes the relation invalid
				if (null === $value) {
					return false;
				}

				if (isset($filters[0])) {
					$filters[] = 'and';
				}

				$c_fqn = $target_qb->fullyQualifiedName($this->target_table, $target_column);

				$filters[] = [$c_fqn, Operator::EQ->value, $value];
			}

			$target_qb->andWhere(Filters::fromArray($filters, $target_qb));

			return true;
		}

		$filters = $target_qb->filters();

		$target_qb->innerJoin($this->target_table)
			->to($this->host_table)
			->on($filters);

		foreach ($this->columns_mapping as $host_column => $target_column) {
			$filters->eq(
				$target_qb->fullyQualifiedName($this->target_table, $target_column),
				$target_qb->fullyQualifiedName($this->host_table, $host_column),
			);
		}

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'type' => $this->type->value,
		] + $this->options;
	}
}
