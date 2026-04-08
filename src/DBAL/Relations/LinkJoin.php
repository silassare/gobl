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

use Gobl\DBAL\Builders\LinkBuilder;
use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Filters\Filters;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Operator;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\DBAL\Relations\Interfaces\LinkInterface;
use Gobl\DBAL\Table;
use Gobl\ORM\ORMEntity;
use Override;
use Throwable;

/**
 * Class LinkJoin.
 */
final class LinkJoin extends Link
{
	/**
	 * @var LinkInterface[]
	 */
	private array $links = [];

	/**
	 * LinkJoin constructor.
	 *
	 * @param RDBMSInterface $rdbms
	 * @param Table          $host_table
	 * @param Table          $target_table
	 * @param array{
	 * 			steps: array{join: string, link: array|\Gobl\DBAL\Builders\LinkBuilder} }[],
	 *          filters?: null|array,
	 *       } $options
	 *
	 * @throws DBALException
	 */
	public function __construct(
		RDBMSInterface $rdbms,
		Table $host_table,
		Table $target_table,
		array $options,
	) {
		parent::__construct(LinkType::JOIN, $rdbms, $host_table, $target_table, $options);

		$steps  = $this->options['steps'] ?? [];

		if (empty($steps) || !\is_array($steps)) {
			throw new DBALException('The "steps" option must be a non-empty array of steps.');
		}

		$from_table = $this->host_table;

		foreach ($steps as $key => $opt) {
			$to_table_name = $opt['join'] ?? null;
			$link_option   = $opt['link'] ?? [];

			if (empty($to_table_name)) {
				throw new DBALException(\sprintf(
					'The "join" option is missing for the step %d.',
					$key
				));
			}

			if ($link_option instanceof LinkBuilder) {
				$link_option = $link_option->toArray();
			} elseif (!\is_array($link_option)) {
				throw new DBALException(\sprintf(
					'The "link" option must be an array|%s for the step %d not "%s".',
					LinkBuilder::class,
					$key,
					\gettype($link_option)
				));
			}

			$to_table = $this->rdbms->getTable($to_table_name);

			if (null === $to_table) {
				throw new DBALException(\sprintf(
					'The table "%s" used in the step %d of the link does not exist.',
					$to_table_name,
					$key
				));
			}

			try {
				$this->links[] = $this->subLink($from_table, $to_table, $link_option, 1);
			} catch (Throwable $t) {
				throw new DBALException(\sprintf(
					'Failed to create the link for the step %d going from "%s" to table "%s".',
					$key,
					$from_table->getName(),
					$to_table->getName()
				), null, $t);
			}

			$from_table = $to_table;
		}

		// final check to ensure we end at the target table
		// if not, we try to auto create the last link
		if ($from_table->getName() !== $this->target_table->getName()) {
			try {
				$this->links[] = $this->subLink($from_table, $this->target_table, [
					'type' => LinkType::COLUMNS->value,
				], 1);
			} catch (Throwable $t) {
				throw new DBALException(\sprintf(
					'You did not specify the last link to the target table "%s" from table "%s". Automatic link creation failed.',
					$from_table->getName(),
					$this->target_table->getName()
				), null, $t);
			}
		}
	}

	/**
	 * {@inheritDoc}
	 *
	 * Always returns `false` - multi-step join links span several intermediate tables,
	 * so a single host entity cannot deterministically populate target data.
	 */
	#[Override]
	public function fillRelation(ORMEntity $host_entity, array &$target_data = []): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Applies each step's sub-link in order. The host entity is forwarded **only to the first
	 * step** (host -> first pivot), so that concrete entity values filter the entry point of
	 * the chain. Subsequent step links receive `null` and operate in join mode.
	 *
	 * Returns `false` as soon as any step's `apply()` returns `false`.
	 */
	#[Override]
	public function runLinkTypeApplyLogic(QBSelect $target_qb, ?ORMEntity $host_entity = null): bool
	{
		$host_to_first_pivot = true;

		foreach ($this->links as $link) {
			if (!$link->apply($target_qb, $host_to_first_pivot ? $host_entity : null)) {
				return false;
			}

			$host_to_first_pivot = false;
		}

		return true;
	}

	#[Override]
	public function toArray(): array
	{
		return [
			'type'        => $this->type->value,
		] + $this->options;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Batch strategy for `LinkJoin`:
	 * 1. Apply `links[N..1]` in reverse join mode. The target_qb starts with the target
	 *    table as the FROM anchor; working backwards ensures each step's anchor
	 *    (links[i].target_table) is already registered before links[i] is applied.
	 *    This builds the chain: final_target <- intermediate_N <- ... <- intermediate_1 (= first pivot A).
	 * 2. Apply `links[0]` (host -> A) in batch mode: collects host-col values, adds
	 *    `A.target_col IN (...)` WHERE conditions, and injects `SELECT A.target_col AS
	 *    _gobl_batch_key[_N]` computed slots that `groupBatchResults()` will read.
	 *
	 * Supports `LinkColumns` and `LinkMorph` as `links[0]`.
	 * Falls back to `false` for any other first-link type (e.g. nested `LinkThrough`).
	 */
	#[Override]
	public function applyBatch(QBSelect $target_qb, array $host_entities): bool
	{
		// Step 1: apply links[N..1] in reverse join mode to register all intermediate aliases.
		// Reverse order is required: target_qb starts with the target table as the FROM anchor;
		// each inner join must anchor on a table that is already registered. Working backwards
		// from the deepest intermediate toward the first pivot ensures each step's anchor
		// (links[i].target_table) was registered by the previous iteration.
		for ($i = \count($this->links) - 1; $i >= 1; --$i) {
			if (!$this->links[$i]->apply($target_qb)) {
				return false;
			}
		}

		// Step 2: apply links[0] batch filter and inject computed routing slots.
		$first = $this->links[0];

		if ($first instanceof LinkColumns) {
			return $this->applyBatchFirstLinkColumns($first, $target_qb, $host_entities);
		}

		if ($first instanceof LinkMorph) {
			return $this->applyBatchFirstLinkMorph($first, $target_qb, $host_entities);
		}

		// Fallback: batch mode only supports LinkColumns or LinkMorph as links[0].
		// Keep your relation link simple if you want to benefit from batch loading.
		// A nested LinkThrough/LinkJoin as the first hop is not handled here.
		return false;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Uses the `_gobl_batch_key[_N]` computed values that `applyBatch()` injected in the
	 * SELECT to route each target entity back to its host(s).
	 *
	 * For `LinkColumns` as first link: pivot FK = host col value; build reverse index on
	 * host col, look up by computed slot value.
	 * For `LinkMorph` as first link: same pattern using the morph routing column.
	 */
	#[Override]
	public function groupBatchResults(array $host_entities, array $result_entities): array
	{
		$first = $this->links[0];

		if ($first instanceof LinkColumns) {
			return $this->groupBatchResultsLinkColumns($first, $host_entities, $result_entities);
		}

		if ($first instanceof LinkMorph) {
			return $this->groupBatchResultsLinkMorph($first, $host_entities, $result_entities);
		}

		return [];
	}

	/**
	 * Applies the batch WHERE and computed slots for a `LinkColumns` first link.
	 *
	 * After `links[1..N]` registered the first intermediate table A, this adds:
	 * - `A.target_col_0 IN (host_col_0 values)` [AND `A.target_col_1 IN (...)` for composite]
	 * - `SELECT A.target_col AS _gobl_batch_key[_N]`
	 */
	private function applyBatchFirstLinkColumns(
		LinkColumns $first,
		QBSelect $target_qb,
		array $host_entities
	): bool {
		$mapping = $first->getColumnsMapping(); // {host_col -> A_target_col}

		/** @var array<string, list<mixed>> $values_per_col */
		$values_per_col = [];

		foreach ($mapping as $host_col => $a_col) {
			$values_per_col[$host_col] = [];
		}

		foreach ($host_entities as $entity) {
			$row  = [];
			$skip = false;

			foreach ($mapping as $host_col => $a_col) {
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

		$is_composite = \count($mapping) > 1;
		$slot         = 0;

		foreach ($mapping as $host_col => $a_col) {
			$values = $values_per_col[$host_col];

			if (empty($values)) {
				continue;
			}

			// After links[1..N] applied, first->target_table (= A) is registered.
			$a_fqn = $target_qb->fullyQualifiedName($first->getTargetTable(), $a_col);

			$target_qb->andWhere(
				Filters::fromArray([[$a_fqn, Operator::IN->value, $values]], $target_qb)
			);

			$key = $is_composite ? 'batch_key_' . $slot : 'batch_key';

			$target_qb->selectComputed($a_fqn, $key);
			++$slot;
		}

		return true;
	}

	/**
	 * Applies the batch WHERE and a single computed slot for a `LinkMorph` first link.
	 */
	private function applyBatchFirstLinkMorph(
		LinkMorph $first,
		QBSelect $target_qb,
		array $host_entities
	): bool {
		// Delegate WHERE conditions to LinkMorph::applyBatch() -- it references
		// first->target_table (= intermediate A), which is now registered.
		if (!$first->applyBatch($target_qb, $host_entities)) {
			return false;
		}

		// Inject computed routing slot from A.
		$a_routing_col = $first->isHostParent()
			? $first->getMorphChildKeyColumn()
			: $first->getMorphParentKeyColumn();

		$a_fqn = $target_qb->fullyQualifiedName($first->getTargetTable(), $a_routing_col);

		$target_qb->selectComputed($a_fqn, 'batch_key');

		return true;
	}

	/**
	 * Grouping helper for `LinkColumns` first links.
	 *
	 * @param LinkColumns $first
	 * @param ORMEntity[] $host_entities
	 * @param ORMEntity[] $result_entities
	 *
	 * @return array<string, ORMEntity[]>
	 */
	private function groupBatchResultsLinkColumns(
		LinkColumns $first,
		array $host_entities,
		array $result_entities
	): array {
		$mapping      = $first->getColumnsMapping();
		$is_composite = \count($mapping) > 1;
		$host_cols    = \array_keys($mapping);

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
	 * Grouping helper for `LinkMorph` first links.
	 *
	 * @param LinkMorph   $first
	 * @param ORMEntity[] $host_entities
	 * @param ORMEntity[] $result_entities
	 *
	 * @return array<string, ORMEntity[]>
	 */
	private function groupBatchResultsLinkMorph(
		LinkMorph $first,
		array $host_entities,
		array $result_entities
	): array {
		$host_routing_col = $first->isHostParent()
			? $first->getMorphParentKeyColumn()
			: $first->getMorphChildKeyColumn();

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
