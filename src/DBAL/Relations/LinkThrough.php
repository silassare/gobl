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
use Gobl\DBAL\Queries\QBSelect;
use Gobl\DBAL\Relations\Interfaces\LinkInterface;
use Gobl\DBAL\Table;
use Gobl\ORM\ORMEntity;
use Override;

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
	 *          filters?: null|array,
	 * 			pivot_table?: null|string,
	 *          host_to_pivot?: null|array,
	 *          pivot_to_target?: null|array
	 *       } $options
	 *
	 * @throws DBALException
	 */
	public function __construct(
		RDBMSInterface $rdbms,
		Table $host_table,
		Table $target_table,
		private readonly Table $pivot_table,
		array $options = [],
	) {
		parent::__construct(LinkType::THROUGH, $rdbms, $host_table, $target_table, $options);

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

		$this->host_to_pivot_link   = $this->subLink($this->host_table, $this->pivot_table, $htp_options);
		$this->pivot_to_target_link = $this->subLink($this->pivot_table, $this->target_table, $ptt_options);
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
	 *
	 * Always returns `false` - pivot-table links span two hops, so a single
	 * host entity cannot populate the target data without a database round-trip.
	 */
	#[Override]
	public function fillRelation(ORMEntity $host_entity, array &$target_data = []): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 *
	 * The pivot join is applied in **pivot-first** order:
	 * 1. `pivot_to_target_link->apply()` (join mode, no entity) - joins the pivot to the target.
	 * 2. `host_to_pivot_link->apply($host_entity)` - either filters by entity values (entity mode)
	 *    or adds the host->pivot join (join mode).
	 *
	 * This ordering ensures the JOIN clauses are emitted in the correct inside-out sequence
	 * required by the SQL generator.
	 */
	#[Override]
	public function runLinkTypeApplyLogic(QBSelect $target_qb, ?ORMEntity $host_entity = null): bool
	{
		if ($this->pivot_to_target_link->apply($target_qb)) {
			return $this->host_to_pivot_link->apply($target_qb, $host_entity);
		}

		return false;
	}

	#[Override]
	public function toArray(): array
	{
		return [
			'type'        => $this->type->value,
			'pivot_table' => $this->pivot_table->getName(),
		] + $this->options;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Batch strategy for `LinkThrough`:
	 * 1. Apply `pivot_to_target_link` in join mode - registers the pivot alias on `$target_qb`.
	 * 2. Delegate the host -> pivot batch filter to `host_to_pivot_link->applyBatch()`.
	 *    Because the pivot alias is now registered, `fullyQualifiedName(pivot, col)` resolves
	 *    correctly inside `applyBatch()`.
	 * 3. Inject `SELECT pivot_col AS _gobl_batch_key[_N]` so that each target entity carries
	 *    the pivot FK value used to route it back to its host in `groupBatchResults()`.
	 *
	 * Supports both `LinkColumns` and `LinkMorph` as `host_to_pivot_link`.
	 * Returns `false` for any other sub-link type.
	 */
	#[Override]
	public function applyBatch(QBSelect $target_qb, array $host_entities): bool
	{
		// Step 1: register the pivot via the pivot -> target join (same as entity path).
		if (!$this->pivot_to_target_link->apply($target_qb)) {
			return false;
		}

		// Step 2: batch-filter the pivot using the host -> pivot link.
		if (!$this->host_to_pivot_link->applyBatch($target_qb, $host_entities)) {
			return false;
		}

		// Step 3: inject computed slots so groupBatchResults() can route results.
		$this->selectPivotBatchKeys($target_qb);

		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Uses the `_gobl_batch_key[_N]` computed values that `applyBatch()` injected into the
	 * SELECT to route each target entity back to its host(s).
	 *
	 * For `LinkColumns` host-to-pivot: the pivot FK value equals the host column value, so we
	 * build a reverse index `str(host.host_col) -> [host_identity_key]` and look up
	 * `str(entity.getComputedValue('batch_key[_N]'))`.
	 *
	 * For `LinkMorph` host-to-pivot: the pivot morph-key equals the host routing-column value,
	 * same reverse-lookup pattern with a single `batch_key` slot.
	 */
	#[Override]
	public function groupBatchResults(array $host_entities, array $result_entities): array
	{
		$htp = $this->host_to_pivot_link;

		if ($htp instanceof LinkColumns) {
			return $this->groupBatchResultsLinkColumns($htp, $host_entities, $result_entities);
		}

		if ($htp instanceof LinkMorph) {
			return $this->groupBatchResultsLinkMorph($htp, $host_entities, $result_entities);
		}

		return [];
	}

	/**
	 * Injects `SELECT pivot_alias.pivot_col AS _gobl_batch_key[_N]` for every FK column
	 * that routes results back to a host entity.
	 *
	 * For `LinkColumns`: one slot per mapping entry (`batch_key` for single, `batch_key_0`,
	 * `batch_key_1`, ... for composite).
	 * For `LinkMorph`: always a single `batch_key` slot using the pivot morph-key column.
	 */
	private function selectPivotBatchKeys(QBSelect $target_qb): void
	{
		$htp = $this->host_to_pivot_link;

		if ($htp instanceof LinkColumns) {
			$mapping      = $htp->getColumnsMapping();
			$is_composite = \count($mapping) > 1;
			$slot         = 0;

			foreach ($mapping as $host_col => $pivot_col) {
				$pivot_fqn = $target_qb->fullyQualifiedName($this->pivot_table, $pivot_col);
				$key       = $is_composite ? 'batch_key_' . $slot : 'batch_key';

				$target_qb->selectComputed($pivot_fqn, $key);
				++$slot;
			}

			return;
		}

		if ($htp instanceof LinkMorph) {
			// host_is_parent=true  -> pivot carries child columns; routing col = child_key
			// host_is_parent=false -> pivot carries parent_key; routing col = parent_key
			$pivot_routing_col = $htp->isHostParent()
				? $htp->getMorphChildKeyColumn()
				: $htp->getMorphParentKeyColumn();

			$pivot_fqn = $target_qb->fullyQualifiedName($this->pivot_table, $pivot_routing_col);

			$target_qb->selectComputed($pivot_fqn, 'batch_key');
		}
	}

	/**
	 * Grouping helper for `LinkColumns` host-to-pivot links.
	 *
	 * @param LinkColumns $htp
	 * @param ORMEntity[] $host_entities
	 * @param ORMEntity[] $result_entities
	 *
	 * @return array<string, ORMEntity[]>
	 */
	private function groupBatchResultsLinkColumns(
		LinkColumns $htp,
		array $host_entities,
		array $result_entities
	): array {
		$mapping      = $htp->getColumnsMapping();
		$is_composite = \count($mapping) > 1;
		$host_cols    = \array_keys($mapping);

		// Build reverse index: composite_key -> [host_identity_key, ...]
		$by_key = [];

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

			$by_key[$lookup_key][] = $entity->toIdentityKey();
		}

		$grouped = [];

		foreach ($result_entities as $result) {
			if ($is_composite) {
				$key_parts = [];

				for ($i = 0; $i < \count($mapping); ++$i) {
					$key_parts[] = (string) $result->getComputedValue('batch_key_' . $i);
				}

				$lookup_key = \implode("\0", $key_parts);
			} else {
				$lookup_key = (string) $result->getComputedValue('batch_key');
			}

			foreach ($by_key[$lookup_key] ?? [] as $host_pk) {
				$grouped[$host_pk][] = $result;
			}
		}

		return $grouped;
	}

	/**
	 * Grouping helper for `LinkMorph` host-to-pivot links.
	 *
	 * @param LinkMorph   $htp
	 * @param ORMEntity[] $host_entities
	 * @param ORMEntity[] $result_entities
	 *
	 * @return array<string, ORMEntity[]>
	 */
	private function groupBatchResultsLinkMorph(
		LinkMorph $htp,
		array $host_entities,
		array $result_entities
	): array {
		// host_is_parent=true  -> host has parent_key, pivot has child_key (= host FK)
		// host_is_parent=false -> host has child_key, pivot has parent_key (= host FK)
		$host_routing_col = $htp->isHostParent()
			? $htp->getMorphParentKeyColumn()
			: $htp->getMorphChildKeyColumn();

		$by_key = [];

		foreach ($host_entities as $entity) {
			$val = $entity->{$host_routing_col};

			if (null !== $val) {
				$by_key[(string) $val][] = $entity->toIdentityKey();
			}
		}

		$grouped = [];

		foreach ($result_entities as $result) {
			$lookup_key = (string) $result->getComputedValue('batch_key');

			foreach ($by_key[$lookup_key] ?? [] as $host_pk) {
				$grouped[$host_pk][] = $result;
			}
		}

		return $grouped;
	}
}
