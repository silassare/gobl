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
use Gobl\DBAL\Queries\QBSelect;
use Gobl\DBAL\Table;
use Gobl\ORM\ORMEntity;

/**
 * Class LinkMorph.
 */
final class LinkMorph extends Link
{
	private string $morph_parent_key_column;
	private string $morph_parent_type;
	private string $morph_child_key_column;
	private string $morph_child_type_column;

	/**
	 * If the host table is the parent.
	 *
	 * @var bool
	 */
	private bool $host_is_parent;

	/**
	 * LinkMorph constructor.
	 *
	 * @param Table $host_table
	 * @param Table $target_table
	 * @param array{
	 *        filters?: array,
	 *        prefix?: string,
	 *        parent_type?: string,
	 *        parent_key_column?: string,
	 *        child_key_column?: string,
	 *        child_type_column?: string,
	 *     } $options
	 *
	 * @throws DBALException
	 */
	public function __construct(
		Table $host_table,
		Table $target_table,
		array $options = []
	) {
		parent::__construct(LinkType::MORPH, $host_table, $target_table, $options);

		if (isset($this->options['prefix'])) {
			$this->morph_child_key_column  = $this->options['prefix'] . '_id';
			$this->morph_child_type_column = $this->options['prefix'] . '_type';
		} elseif (empty($this->options['child_key_column']) || empty($this->options['child_type_column'])) {
			throw new DBALException(\sprintf(
				'For a morph link between table "%s" and "%s", you must provide a "prefix" or both "child_key_column" and "child_type_column" options.',
				$this->host_table->getName(),
				$this->target_table->getName()
			));
		} else {
			$this->morph_child_key_column  = $this->options['child_key_column'];
			$this->morph_child_type_column = $this->options['child_type_column'];
		}

		if (
			$host_table->hasColumn($this->morph_child_key_column)
			&& $host_table->hasColumn($this->morph_child_type_column)
		) {
			$this->host_is_parent = false;
			$polymorphic_parent   = $target_table;
		} elseif (
			$target_table->hasColumn($this->morph_child_key_column)
			&& $target_table->hasColumn($this->morph_child_type_column)
		) {
			$this->host_is_parent = true;
			$polymorphic_parent   = $host_table;
		} else {
			throw new DBALException(\sprintf(
				'Neither table "%s" nor table "%s" has the columns "%s" and "%s" required for the morph link.',
				$this->host_table->getName(),
				$this->target_table->getName(),
				$this->morph_child_key_column,
				$this->morph_child_type_column
			));
		}

		$this->morph_parent_type = $this->options['parent_type'] ?? $polymorphic_parent->getName();

		if (empty($this->options['parent_key_column'])) {
			$pk = $polymorphic_parent->getPrimaryKeyConstraint();
			if (null === $pk) {
				throw new DBALException(\sprintf(
					'Unable to auto detect the "parent_key_column" from the table "%s" because it has no primary key.',
					$polymorphic_parent->getName()
				));
			}

			$columns = $pk->getColumns();
			if (\count($columns) > 1) {
				throw new DBALException(\sprintf(
					'Unable to auto detect the "parent_key_column" from the table "%s" because it has a composite primary key.',
					$polymorphic_parent->getName()
				));
			}
			$this->morph_parent_key_column = $polymorphic_parent->getColumnOrFail($columns[0])
				->getName();
		} else {
			$column = $polymorphic_parent->getColumnOrFail($this->options['parent_key_column']);

			if (!$polymorphic_parent->isPrimaryKey([$column->getFullName()])) {
				throw new DBALException(\sprintf(
					'The "parent_key_column" value "%s" must be the primary key column in the table "%s".',
					$column->getFullName(),
					$polymorphic_parent->getName()
				));
			}

			$this->morph_parent_key_column = $column->getName();
		}
	}

	/**
	 * Get the morph parent table key column.
	 *
	 * @return string
	 */
	public function getMorphParentKeyColumn(): string
	{
		return $this->morph_parent_key_column;
	}

	/**
	 * Get the morph parent table type.
	 *
	 * @return string
	 */
	public function getMorphParentType(): string
	{
		return $this->morph_parent_type;
	}

	/**
	 * Get the morph child table key column.
	 *
	 * @return string
	 */
	public function getMorphChildKeyColumn(): string
	{
		return $this->morph_child_key_column;
	}

	/**
	 * Get the morph child table type column.
	 *
	 * @return string
	 */
	public function getMorphChildTypeColumn(): string
	{
		return $this->morph_child_type_column;
	}

	/**
	 * {@inheritDoc}
	 */
	public function runLinkTypeApplyLogic(QBSelect $target_qb, ?ORMEntity $host_entity = null): bool
	{
		$filters = $target_qb->filters();

		if ($this->host_is_parent) {
			if ($host_entity) {
				$key = $host_entity->{$this->morph_parent_key_column};

				if (null === $key) {
					return false;
				}

				$filters->eq(
					$target_qb->fullyQualifiedName($this->target_table, $this->morph_child_type_column),
					$this->morph_parent_type
				);
				$filters->eq(
					$target_qb->fullyQualifiedName($this->target_table, $this->morph_child_key_column),
					$key
				);

				$target_qb->andWhere($filters);

				return true;
			}

			$target_qb->innerJoin($this->target_table)
				->to($this->host_table)
				->on($filters);

			$filters->eq(
				$target_qb->fullyQualifiedName($this->target_table, $this->morph_child_key_column),
				$target_qb->fullyQualifiedName($this->host_table, $this->morph_parent_key_column)
			);
			$filters->eq(
				$target_qb->fullyQualifiedName($this->target_table, $this->morph_child_type_column),
				$this->morph_parent_type
			);

			return true;
		}

		if ($host_entity) {
			$key = $host_entity->{$this->morph_child_key_column};

			if (null === $key) {
				return false;
			}

			$filters->eq(
				$target_qb->fullyQualifiedName($this->target_table, $this->morph_parent_key_column),
				$key
			);
			$filters->eq(
				$target_qb->fullyQualifiedName($this->target_table, $this->morph_parent_type),
				$this->morph_parent_type
			);

			$target_qb->andWhere($filters);

			return true;
		}

		$target_qb->innerJoin($this->target_table)
			->to($this->host_table)
			->on($filters);

		$filters->eq(
			$target_qb->fullyQualifiedName($this->target_table, $this->morph_parent_key_column),
			$target_qb->fullyQualifiedName($this->host_table, $this->morph_child_key_column)
		);
		$filters->eq(
			$target_qb->fullyQualifiedName($this->target_table, $this->morph_parent_type),
			$this->morph_parent_type
		);

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
