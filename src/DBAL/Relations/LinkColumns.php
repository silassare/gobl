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

namespace Gobl\DBAL\Relations;

use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Filters\Filters;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Operator;
use Gobl\DBAL\Queries\QBExpression;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\DBAL\Table;
use Gobl\ORM\ORMEntity;
use Override;

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
	 * @param RDBMSInterface $rdbms
	 * @param Table          $host_table
	 * @param Table          $target_table
	 * @param array{
	 *         columns?: null|array<string,string>,
	 *         filters?: null|array
	 *      } $options
	 *
	 * @throws DBALException
	 */
	public function __construct(
		RDBMSInterface $rdbms,
		Table $host_table,
		Table $target_table,
		array $options = []
	) {
		parent::__construct(LinkType::COLUMNS, $rdbms, $host_table, $target_table, $options);

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
	 *
	 * Populates `$target_data` with all mapped host->target column values from `$host_entity`.
	 * Returns `false` (leaving `$target_data` unchanged) as soon as any mapped host column
	 * value is `null`, because a null FK value makes the relation unsatisfiable.
	 */
	#[Override]
	public function fillRelation(ORMEntity $host_entity, array &$target_data = []): bool
	{
		foreach ($this->columns_mapping as $host_column => $target_column) {
			$value = $host_entity->{$host_column};

			// a null value makes the relation invalid
			if (null === $value) {
				return false;
			}

			$target_data[$target_column] = $value;
		}

		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Two operating modes depending on whether a host entity is provided:
	 *
	 * - **Entity mode** (`$host_entity !== null`): reads concrete column values from the entity
	 *   and adds them as equality filters on the target QB. Returns `false` if any mapped column
	 *   value is `null` (relation unsatisfiable).
	 * - **Join mode** (`$host_entity === null`): adds an `INNER JOIN` to the target QB joining
	 *   the target table to the host table with the mapped columns as the ON condition.
	 */
	#[Override]
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
				// Wrap in QBExpression so the host-column FQN is used as a raw SQL expression
				// rather than a bound parameter (FilterRightOperand does not auto-resolve strings).
				new QBExpression($target_qb->fullyQualifiedName($this->host_table, $host_column)),
			);
		}

		return true;
	}

	#[Override]
	public function toArray(): array
	{
		return [
			'type' => $this->type->value,
		] + $this->options;
	}

	/**
	 * {@inheritDoc}
	 *
	 * For a single-column mapping `host_col -> target_col`, adds:
	 *   `target_col IN (host_col values from host_entities)`
	 *
	 * For composite mappings `(host_0 -> target_0, host_1 -> target_1, ...)`, adds one
	 * `IN` condition per column (AND-joined). `groupBatchResults()` performs exact
	 * composite-key matching to eliminate the cross-product over-selection.
	 */
	#[Override]
	public function applyBatch(QBSelect $target_qb, array $host_entities): bool
	{
		/** @var array<string, list<mixed>> $values_per_col keyed by host_col full name */
		$values_per_col = [];

		foreach ($this->columns_mapping as $host_col => $target_col) {
			$values_per_col[$host_col] = [];
		}

		foreach ($host_entities as $entity) {
			$row  = [];
			$skip = false;

			foreach ($this->columns_mapping as $host_col => $target_col) {
				$val = $entity->{$host_col};

				if (null === $val) {
					$skip = true;

					break;
				}

				$row[$host_col] = $val;
			}

			if ($skip) {
				continue;
			}

			foreach ($row as $host_col => $val) {
				$values_per_col[$host_col][] = $val;
			}
		}

		// Nothing to filter on.
		$has_values = false;

		foreach ($values_per_col as $values) {
			if (!empty($values)) {
				$has_values = true;

				break;
			}
		}

		if (!$has_values) {
			return false;
		}

		foreach ($this->columns_mapping as $host_col => $target_col) {
			$values = $values_per_col[$host_col];

			if (empty($values)) {
				continue;
			}

			$c_fqn = $target_qb->fullyQualifiedName($this->target_table, $target_col);

			$target_qb->andWhere(
				Filters::fromArray([[$c_fqn, Operator::IN->value, $values]], $target_qb)
			);
		}

		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * For single-column mapping `host_col -> target_col`:
	 * - Build a reverse index: `target_col_value -> [host_pk_string, ...]`
	 *   (multiple hosts may share the same FK value in many-to-one scenarios).
	 * - For each result entity, look up the host PKs by `result.target_col`.
	 * - The returned map is keyed by host entity PK string.
	 *
	 * For composite mappings, the same pattern applies using a null-byte-delimited
	 * composite key `"val_0\0val_1\0..."` for both the index build and the lookup so
	 * that cross-product results from `applyBatch()` are filtered out accurately.
	 */
	#[Override]
	public function groupBatchResults(array $host_entities, array $result_entities): array
	{
		$is_composite = \count($this->columns_mapping) > 1;

		// host_cols = host column names; target_cols = corresponding target column names
		$host_cols   = \array_keys($this->columns_mapping);
		$target_cols = \array_values($this->columns_mapping);

		// Build reverse index: composite_key -> [host_identity_key, ...]
		$host_pks_by_key = [];

		foreach ($host_entities as $entity) {
			$key_parts = [];
			$skip      = false;

			foreach ($host_cols as $host_col) {
				$val = $entity->{$host_col};

				if (null === $val) {
					$skip = true;

					break;
				}

				$key_parts[] = (string) $val;
			}

			if ($skip) {
				continue;
			}

			$lookup_key = $is_composite ? \implode("\0", $key_parts) : $key_parts[0];

			$host_pks_by_key[$lookup_key][] = $entity->toIdentityKey();
		}

		$grouped = [];

		foreach ($result_entities as $result) {
			$key_parts = [];

			foreach ($target_cols as $target_col) {
				$key_parts[] = (string) $result->{$target_col};
			}

			$lookup_key   = $is_composite ? \implode("\0", $key_parts) : $key_parts[0];
			$host_pk_keys = $host_pks_by_key[$lookup_key] ?? [];

			foreach ($host_pk_keys as $host_pk_key) {
				$grouped[$host_pk_key][] = $result;
			}
		}

		return $grouped;
	}
}
