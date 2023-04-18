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
	private string $morph_host_key_column;
	private string $morph_host_type;
	private string $morph_target_key_column;
	private string $morph_target_type_column;

	/**
	 * LinkMorph constructor.
	 *
	 * @param \Gobl\DBAL\Table $host_table
	 * @param \Gobl\DBAL\Table $target_table
	 * @param array{
	 *        host_type?: string,
	 *        host_key_column?: string,
	 *        prefix?: string,
	 *        target_key_column?: string,
	 *        target_type_column?: string,
	 *     }                   $options
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function __construct(
		Table $host_table,
		Table $target_table,
		private readonly array $options = []
	) {
		parent::__construct(LinkType::MORPH, $host_table, $target_table);

		$this->morph_host_type = $this->options['host_type'] ?? $this->host_table->getName();

		if (isset($this->options['prefix'])) {
			$this->morph_target_key_column  = $this->options['prefix'] . '_id';
			$this->morph_target_type_column = $this->options['prefix'] . '_type';
		} elseif (empty($this->options['target_key_column']) || empty($this->options['target_type_column'])) {
			throw new DBALException(\sprintf(
				'For a morph link between table "%s" and "%s", you must provide a "prefix" or "target_key_column" and "target_type_column" options.',
				$this->host_table->getName(),
				$this->target_table->getName()
			));
		} else {
			$this->morph_target_key_column  = $this->options['target_key_column'];
			$this->morph_target_type_column = $this->options['target_type_column'];
		}

		$this->target_table->assertHasColumn($this->morph_target_key_column);
		$this->target_table->assertHasColumn($this->morph_target_type_column);

		if (empty($this->options['host_key_column'])) {
			$pk = $this->host_table->getPrimaryKeyConstraint();
			if (null === $pk) {
				throw new DBALException(\sprintf(
					'Unable to auto detect the host key column for the table "%s" because it has no primary key.',
					$this->host_table->getName()
				));
			}

			$columns = $pk->getColumns();
			if (\count($columns) > 1) {
				throw new DBALException(\sprintf(
					'Unable to auto detect the host key column for the table "%s" because it has a composite primary key.',
					$this->host_table->getName()
				));
			}
			$this->morph_host_key_column = $this->host_table->getColumnOrFail($columns[0])
				->getName();
		} else {
			$column = $this->host_table->getColumnOrFail($this->options['host_key_column']);

			if (!$this->host_table->isPrimaryKey([$column->getFullName()])) {
				throw new DBALException(\sprintf(
					'The "host_key_column" option "%s" for the table "%s" must be the primary key column.',
					$column->getFullName(),
					$this->host_table->getName()
				));
			}

			$this->morph_host_key_column = $column->getName();
		}
	}

	/**
	 * Get the morph host table key column.
	 *
	 * @return string
	 */
	public function getMorphHostKeyColumn(): string
	{
		return $this->morph_host_key_column;
	}

	/**
	 * Get the morph host table type.
	 *
	 * @return string
	 */
	public function getMorphHostType(): string
	{
		return $this->morph_host_type;
	}

	/**
	 * Get the morph target table key column.
	 *
	 * @return string
	 */
	public function getMorphTargetKeyColumn(): string
	{
		return $this->morph_target_key_column;
	}

	/**
	 * Get the morph target table type column.
	 *
	 * @return string
	 */
	public function getMorphTargetTypeColumn(): string
	{
		return $this->morph_target_type_column;
	}

	/**
	 * {@inheritDoc}
	 */
	public function apply(QBSelect $target_qb, ?ORMEntity $host_entity = null): bool
	{
		if ($host_entity) {
			$filters = $target_qb->filters();
			$key     = $host_entity->{$this->morph_host_key_column};

			if (null === $key) {
				return false;
			}

			$filters->eq($this->morph_target_key_column, $key);
			$filters->eq($this->morph_target_type_column, $this->morph_host_type);

			$target_qb->andWhere($filters);

			return true;
		}

		$filters = $target_qb->filters();

		$target_qb->innerJoin($this->target_table)
			->to($this->host_table)
			->on($filters);

		$filters->eq(
			$target_qb->fullyQualifiedName($this->target_table, $this->morph_target_key_column),
			$target_qb->fullyQualifiedName($this->host_table, $this->morph_host_key_column)
		);
		$filters->eq(
			$target_qb->fullyQualifiedName($this->target_table, $this->morph_target_type_column),
			$this->morph_host_type
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
