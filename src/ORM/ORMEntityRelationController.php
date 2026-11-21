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

namespace Gobl\ORM;

use Gobl\CRUD\Exceptions\CRUDException;
use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Operator;
use Gobl\DBAL\Relations\Interfaces\LinkInterface;
use Gobl\DBAL\Relations\Interfaces\RelationControllerInterface;
use Gobl\DBAL\Relations\LinkColumns;
use Gobl\DBAL\Relations\LinkFilters;
use Gobl\DBAL\Relations\LinkJoin;
use Gobl\DBAL\Relations\LinkMorph;
use Gobl\DBAL\Relations\LinkThrough;
use Gobl\DBAL\Relations\Relation;
use Gobl\DBAL\Relations\RelationJoinWrites;
use Gobl\DBAL\Relations\RelationOnAttach;
use Gobl\DBAL\Relations\RelationOnRemove;
use Gobl\DBAL\Table;
use Gobl\Exceptions\GoblException;
use Gobl\ORM\Exceptions\ORMException;
use Gobl\ORM\Exceptions\ORMQueryException;
use Gobl\ORM\Interfaces\ORMOptionsInterface;
use Gobl\ORM\Utils\Helpers;
use Override;
use Throwable;

/**
 * Class ORMEntityRelationController.
 *
 * Reads a row's relatives, and writes them the way the relation's link says: what a write means
 * depends on which row holds the link.
 *
 * - **The relative holds it** (a child's key, a morph child's key and type): the relative is
 *   written with the link and the equalities the relation's filters ask of it, and a payload
 *   naming them with other values is refused.
 * - **The host holds it** (an account's key to its client, a morph child pointing at its parent):
 *   the relative is written as itself, and the host's key is pointed at it.
 * - **A link table holds it** (a relation through one): the relative is written as itself, and
 *   the link row between the two is written or removed.
 * - **A join** writes nothing unless the relation declares it ({@see RelationJoinWrites}).
 *
 * Every write is one transaction, checked at its end: a row the relation does not count as its
 * own -- a filter that is not an equality, a value the write changed -- is refused and rolled back.
 * What removing does is the relation's declaration ({@see RelationOnRemove}), and so is attaching a
 * record another row holds ({@see RelationOnAttach}).
 *
 * @implements RelationControllerInterface<ORMEntity,array,array>
 */
class ORMEntityRelationController implements RelationControllerInterface
{
	/** The relative holds the link. */
	private const string ON_RELATIVE = 'relative';

	/** The host holds the link. */
	private const string ON_HOST = 'host';

	/** A link table holds the link. */
	private const string ON_LINK_TABLE = 'link_table';

	protected Table $table;
	protected ORMController $controller;

	/**
	 * ORMRelatives constructor.
	 */
	public function __construct(protected Relation $relation)
	{
		$table            = $relation->getTargetTable();
		$this->controller = ORM::ctrl($table);
		$this->table      = $table;
	}

	#[Override]
	public function getRelativesStoreTable(): Table
	{
		return $this->table;
	}

	/**
	 * @throws GoblException
	 */
	#[Override]
	public function get(ORMEntity $host_entity, ORMOptionsInterface $options): ?ORMEntity
	{
		return $this->controller->getRelative($host_entity, $this->relation, $options);
	}

	/**
	 * @throws GoblException
	 */
	#[Override]
	public function list(ORMEntity $host_entity, ORMOptionsInterface $options): ?ORMResults
	{
		return $this->controller->getAllRelatives($host_entity, $this->relation, $options);
	}

	/**
	 * @throws DBALException
	 * @throws GoblException
	 */
	#[Override]
	public function create(ORMEntity $host_entity, array $payload): mixed
	{
		Helpers::assertCanManageRelatives($this->table, $this->relation, [$host_entity]);

		$holder = $this->holder();
		$form   = ORM::formByFullNames($this->table, $payload);

		return $this->db()->runInTransaction(function () use ($host_entity, $holder, $form): ORMEntity {
			if (self::ON_RELATIVE === $holder) {
				// Created from what the link writes and what the payload names, and nothing else:
				// handed an entity, a create checks every column it holds, so a private column the
				// request never touched -- still its table's default -- would be refused.
				$created = $this->controller->addItem($this->withFixed($form, $this->fixedFor($host_entity)));
			} else {
				$created = $this->controller->addItem($this->withFixed($form, $this->equalities()));

				$this->writeLink($host_entity, $created, $holder);
			}

			$this->assertRelative($host_entity, $created);

			return $created;
		});
	}

	/**
	 * Links an existing record to the host: the relative's key pointed at the host, the host's key
	 * pointed at the record, or a link row written between them.
	 *
	 * The payload names the record by its identity, as an update's does. A record already a
	 * relative is answered as it is. One whose own key names another row is refused unless the
	 * relation declares it is moved ({@see RelationOnAttach::MOVE}). Nothing else of the record is
	 * written: what it must be to belong -- a filter's equality -- is checked, not set.
	 *
	 * @throws GoblException
	 */
	#[Override]
	public function attach(ORMEntity $host_entity, array $payload): ORMEntity
	{
		Helpers::assertCanManageRelatives($this->table, $this->relation, [$host_entity]);

		$holder   = $this->holder();
		$identity = $this->extractFilters($payload);

		return $this->db()->runInTransaction(function () use ($host_entity, $holder, $identity): ORMEntity {
			$options = new ORMOptions();
			$options->setFilters($identity);

			$record = $this->controller->getItem($options);

			if (null === $record) {
				throw new ORMException('Unable to identify the record to attach.');
			}

			$already = new ORMOptions();
			$already->setFilters($identity);

			if (null !== $this->controller->getRelative($host_entity, $this->relation, $already)) {
				return $record;
			}

			if (self::ON_RELATIVE === $holder && $this->isHeldElsewhere($record)
				&& RelationOnAttach::MOVE !== $this->relation->getOnAttach()
			) {
				throw new ORMQueryException('GOBL_ORM_RELATION_ATTACH_HELD_ELSEWHERE', [
					'relation' => $this->relation->getName(),
				]);
			}

			$this->writeLink($host_entity, $record, $holder);

			$this->assertRelative($host_entity, $record);

			return $record;
		});
	}

	/**
	 * @throws GoblException
	 * @throws ORMException
	 * @throws CRUDException
	 */
	#[Override]
	public function link(ORMEntity $parent_entity, ORMEntity $child_entity, bool $auto_save = true): static
	{
		$link = $this->relation->getLink();

		if (!$link->fillRelation($parent_entity, $child_entity)) {
			throw new ORMException('Unable to link entities.');
		}

		if ($auto_save) {
			$child_entity->save();
		}

		return $this;
	}

	/**
	 * @throws ORMException
	 * @throws GoblException
	 */
	#[Override]
	public function update(ORMEntity $host_entity, array $payload): ORMEntity
	{
		$holder = $this->holder();
		$target = $this->identify($host_entity, $payload);

		// Through the controller, as a request's own update is, and not through the entity's save:
		// that one is server code, trusted to write private and sensitive columns, and the payload
		// here is a request's.
		$identity = $target->toIdentityFilters();
		$form     = ORM::formByFullNames($this->table, $payload);

		// The identity is how the payload names its relative, not a write of it: it found that very
		// row, and a key is never written by an update.
		foreach ($identity as $filter) {
			if (\is_array($filter)) {
				unset($form[$filter[0]]);
			}
		}

		// What the relation fixes is not the payload's to change: the same value is accepted and
		// left out, another one refused.
		$fixed = self::ON_RELATIVE === $holder ? $this->fixedFor($host_entity) : $this->equalities();

		foreach ($fixed as $column => $value) {
			if (\array_key_exists($column, $form)) {
				$this->assertSameValue($column, $form[$column], $value);

				unset($form[$column]);
			}
		}

		$options = new ORMOptions();
		$options->setFilters($identity);
		$options->setFormData($form);

		return $this->db()->runInTransaction(function () use ($host_entity, $options): ORMEntity {
			$updated = $this->controller->updateOneItem($options);

			if (null === $updated) {
				throw new ORMException('Unable to identify relative.');
			}

			$this->assertRelative($host_entity, $updated);

			return $updated;
		});
	}

	/**
	 * Removes a relative, as the relation declares ({@see RelationOnRemove}): the link only by
	 * default -- the link row, or the key set to null, refused where it cannot be -- or the link and
	 * the record.
	 *
	 * @throws ORMException
	 * @throws GoblException
	 */
	#[Override]
	public function delete(ORMEntity $host_entity, array $payload): ORMEntity
	{
		$holder = $this->holder();
		$target = $this->identify($host_entity, $payload);

		return $this->db()->runInTransaction(function () use ($host_entity, $holder, $target): ORMEntity {
			$delete = RelationOnRemove::DELETE === $this->relation->getOnRemove();

			// The record holding the link goes with it: nothing is left to unlink.
			if ($delete && self::ON_RELATIVE === $holder) {
				return $target->selfDelete();
			}

			$this->unlink($host_entity, $target, $holder);

			return $delete ? $target->selfDelete() : $target;
		});
	}

	/**
	 * @throws GoblException
	 */
	#[Override]
	public function getBatch(array $host_entities, ORMOptionsInterface $options): array
	{
		return $this->controller->getAllRelativesBatch(
			$host_entities,
			$this->relation,
			$options
		);
	}

	/**
	 * Which row holds the link of this relation, or a refusal where it is a join that declares no
	 * way to write one.
	 *
	 * @return self::ON_*
	 *
	 * @throws ORMQueryException
	 */
	private function holder(): string
	{
		$link = $this->relation->getLink();

		if ($link instanceof LinkColumns) {
			return $link->isHostHoldingKey() ? self::ON_HOST : self::ON_RELATIVE;
		}

		if ($link instanceof LinkMorph) {
			return $link->isHostParent() ? self::ON_RELATIVE : self::ON_HOST;
		}

		if ($link instanceof LinkThrough || null !== $this->joinAsThrough()) {
			return self::ON_LINK_TABLE;
		}

		throw new ORMQueryException('GOBL_ORM_RELATION_REFUSES_WRITES', [
			'relation' => $this->relation->getName(),
		]);
	}

	/**
	 * A join written as a relation through a link table, where it declares so and has the two steps
	 * that make one: `[link table, host to link table, link table to target]`, else `null`.
	 *
	 * @return null|array{0: Table, 1: LinkInterface, 2: LinkInterface}
	 */
	private function joinAsThrough(): ?array
	{
		$link = $this->relation->getLink();

		if (!$link instanceof LinkJoin || RelationJoinWrites::LAST_STEP !== $this->relation->getJoinWrites()) {
			return null;
		}

		$steps = $link->getSteps();

		if (2 !== \count($steps)) {
			return null;
		}

		return [$steps[0]->getTargetTable(), $steps[0], $steps[1]];
	}

	/**
	 * The link table of a relation through one, and its two links.
	 *
	 * @return array{0: Table, 1: LinkInterface, 2: LinkInterface}
	 *
	 * @throws ORMException
	 */
	private function through(): array
	{
		$link = $this->relation->getLink();

		if ($link instanceof LinkThrough) {
			return [$link->getPivotTable(), $link->getHostToThroughLink(), $link->getThroughToTargetLink()];
		}

		return $this->joinAsThrough() ?? throw new ORMException('The relation has no link table.');
	}

	/**
	 * Points the host at the relative, or writes the link row between them.
	 *
	 * @param self::ON_HOST|self::ON_LINK_TABLE|self::ON_RELATIVE $holder
	 *
	 * @throws GoblException
	 */
	private function writeLink(ORMEntity $host_entity, ORMEntity $relative, string $holder): void
	{
		$link = $this->relation->getLink();

		if (self::ON_RELATIVE === $holder) {
			$this->writeColumns($this->table, $relative, self::pointing($link, $host_entity, $relative)['values']);

			return;
		}

		if (self::ON_HOST === $holder) {
			$this->writeColumns(
				$this->relation->getHostTable(),
				$host_entity,
				self::pointing($link, $host_entity, $relative)['values']
			);

			return;
		}

		[$pivot, $to_pivot, $to_target] = $this->through();

		ORM::ctrl($pivot)->addItem($this->linkRow($pivot, $to_pivot, $to_target, $host_entity, $relative));
	}

	/**
	 * Removes the link between the host and the relative: the link row, or the key that points
	 * from one to the other set to null -- refused where that key cannot be null.
	 *
	 * @param self::ON_HOST|self::ON_LINK_TABLE|self::ON_RELATIVE $holder
	 *
	 * @throws GoblException
	 */
	private function unlink(ORMEntity $host_entity, ORMEntity $relative, string $holder): void
	{
		$link = $this->relation->getLink();

		if (self::ON_LINK_TABLE === $holder) {
			[$pivot, $to_pivot, $to_target] = $this->through();

			$filters = [];

			foreach ($this->linkRow($pivot, $to_pivot, $to_target, $host_entity, $relative) as $column => $value) {
				if (!empty($filters)) {
					$filters[] = 'and';
				}

				$filters[] = [$column, Operator::EQ, $value];
			}

			$options = new ORMOptions();
			$options->setFilters($filters);

			$links = ORM::ctrl($pivot);

			// One row at a time, as a request deletes one: deleting every row a filter matches is
			// an authorization of its own, which removing one link is not asking for.
			foreach (\iterator_to_array($links->getAllItems($options)->getItems(), false) as $row) {
				$row->selfDelete();
			}

			return;
		}

		$table  = self::ON_HOST === $holder ? $this->relation->getHostTable() : $this->table;
		$row    = self::ON_HOST === $holder ? $host_entity : $relative;
		$values = [];

		foreach (self::pointing($link, $host_entity, $relative)['values'] as $column => $_) {
			if (!$table->getColumnOrFail($column)->getType()->isNullable()) {
				throw new ORMQueryException('GOBL_ORM_RELATION_CANNOT_UNLINK', [
					'relation' => $this->relation->getName(),
					'field'    => $column,
				]);
			}

			$values[$column] = null;
		}

		$this->writeColumns($table, $row, $values);
	}

	/**
	 * The link row between the host and the relative: what each of its two links writes on it,
	 * and the equalities their filters ask of it.
	 *
	 * @return array<string, mixed>
	 *
	 * @throws ORMException
	 */
	private function linkRow(
		Table $pivot,
		LinkInterface $to_pivot,
		LinkInterface $to_target,
		ORMEntity $host_entity,
		ORMEntity $relative
	): array {
		$row = ORM::entity($pivot);

		$from_host   = self::pointing($to_pivot, $host_entity, $row);
		$from_target = self::pointing($to_target, $row, $relative);

		// Both links are the link table's to hold, or it is not one this can write.
		if ('target' !== $from_host['holder'] || 'host' !== $from_target['holder']) {
			throw new ORMException('The link table does not hold both links of the relation.');
		}

		return $from_host['values'] + $from_target['values']
			+ LinkFilters::requiredEqualities($to_pivot, $pivot)
			+ LinkFilters::requiredEqualities($to_target, $pivot);
	}

	/**
	 * What makes one row point at the other through a link of columns or a morph one: the side
	 * whose row holds it, and the values to write there, by full names.
	 *
	 * @return array{holder: 'host'|'target', values: array<string, mixed>}
	 *
	 * @throws ORMException
	 */
	private static function pointing(LinkInterface $link, ORMEntity $host, ORMEntity $target): array
	{
		if ($link instanceof LinkColumns) {
			$values = [];

			foreach ($link->getColumnsMapping() as $host_column => $target_column) {
				if ($link->isHostHoldingKey()) {
					$values[$host_column] = $target->{$target_column};
				} else {
					$values[$target_column] = $host->{$host_column};
				}
			}

			return ['holder' => $link->isHostHoldingKey() ? 'host' : 'target', 'values' => $values];
		}

		if ($link instanceof LinkMorph) {
			$host_is_parent = $link->isHostParent();
			$parent         = $host_is_parent ? $host : $target;
			$parent_table   = $host_is_parent ? $link->getHostTable() : $link->getTargetTable();
			$child_table    = $host_is_parent ? $link->getTargetTable() : $link->getHostTable();
			$key            = $child_table->getColumnOrFail($link->getMorphChildKeyColumn())->getFullName();
			$type           = $child_table->getColumnOrFail($link->getMorphChildTypeColumn())->getFullName();
			$parent_key     = $parent_table->getColumnOrFail($link->getMorphParentKeyColumn())->getFullName();

			return [
				'holder' => $host_is_parent ? 'target' : 'host',
				'values' => [$key => $parent->{$parent_key}, $type => $link->getMorphParentType()],
			];
		}

		throw new ORMException('Only a link of columns or a morph one can be written.');
	}

	/**
	 * Writes columns of a stored row, through its table's controller as a request's write is.
	 *
	 * @param array<string, mixed> $values by full names
	 *
	 * @throws GoblException
	 */
	private function writeColumns(Table $table, ORMEntity $row, array $values): void
	{
		$options = new ORMOptions();
		$options->setFilters($row->toIdentityFilters());
		$options->setFormData($values);

		$written = ORM::ctrl($table)->updateOneItem($options);

		if (null === $written) {
			throw new ORMException('Unable to write the link.');
		}

		// The caller's row now says what was stored, and is a stored row still: these are the
		// values the database just took, not changes waiting to be saved.
		foreach ($values as $column => $_) {
			$row->{$column} = $written->{$column};
		}

		$row->isSaved(true);
	}

	/**
	 * Whether a record that holds its own link names another row than none.
	 */
	private function isHeldElsewhere(ORMEntity $record): bool
	{
		$link = $this->relation->getLink();
		$host = ORM::entity($this->relation->getHostTable());

		foreach (self::pointing($link, $host, $record)['values'] as $column => $_) {
			if (null !== $record->{$column}) {
				return true;
			}
		}

		return false;
	}

	/**
	 * What the relation fixes on a relative of this host that holds the link, by full name: the key
	 * it fills, and the equalities its filters ask of the relative's own columns.
	 *
	 * @return array<string, mixed>
	 *
	 * @throws ORMException
	 */
	private function fixedFor(ORMEntity $host_entity): array
	{
		$link   = $this->relation->getLink();
		$filled = ORM::entity($this->table);

		if (!$link->fillRelation($host_entity, $filled)) {
			throw new ORMException('Unable to fill relation.');
		}

		// The key the link fills wins over an equality naming the same column: it is the link.
		return $filled->toRowChanges() + $this->equalities();
	}

	/**
	 * The equalities the relation's own filters ask of the relative's columns.
	 *
	 * @return array<string, mixed>
	 */
	private function equalities(): array
	{
		return LinkFilters::requiredEqualities($this->relation->getLink(), $this->table);
	}

	/**
	 * The form with what the relation fixes written in, refusing a payload that names one of those
	 * columns with another value.
	 *
	 * @param array<string, mixed> $form  by full names
	 * @param array<string, mixed> $fixed by full names
	 *
	 * @return array<string, mixed>
	 *
	 * @throws ORMQueryException
	 */
	private function withFixed(array $form, array $fixed): array
	{
		foreach ($fixed as $column => $value) {
			if (\array_key_exists($column, $form)) {
				$this->assertSameValue($column, $form[$column], $value);
			}

			$form[$column] = $value;
		}

		return $form;
	}

	/**
	 * Refuses a payload's value for a column the relation fixes, when it is another value.
	 *
	 * Compared as the column stores them, so `'7'` and `7` for an integer key are the same value.
	 *
	 * @throws ORMQueryException
	 */
	private function assertSameValue(string $column, mixed $given, mixed $fixed): void
	{
		$a = ORM::entity($this->table, true, false);
		$b = ORM::entity($this->table, true, false);

		$a->{$column} = $given;
		$b->{$column} = $fixed;

		if ($a->toRow()[$column] !== $b->toRow()[$column]) {
			throw new ORMQueryException('GOBL_ORM_RELATION_FIXES_THIS_FIELD', [
				'field'    => $column,
				'relation' => $this->relation->getName(),
			]);
		}
	}

	/**
	 * Refuses a write that left a row the relation does not count as its own: a filter the write
	 * could not satisfy by itself, or a value it changed.
	 *
	 * @throws GoblException
	 */
	private function assertRelative(ORMEntity $host_entity, ORMEntity $written): void
	{
		$options = new ORMOptions();
		$options->setFilters($written->toIdentityFilters());

		if (null === $this->controller->getRelative($host_entity, $this->relation, $options)) {
			throw new ORMQueryException('GOBL_ORM_RELATION_WRITE_NOT_A_RELATIVE', [
				'relation' => $this->relation->getName(),
			]);
		}
	}

	/**
	 * The database of the relative's table, for a write and its check to be one transaction.
	 */
	private function db(): RDBMSInterface
	{
		return ORM::getDatabase($this->table->getNamespace());
	}

	/**
	 * Identify the relative.
	 *
	 * @throws GoblException
	 * @throws ORMException
	 */
	private function identify(ORMEntity $host_entity, array $relative): ORMEntity
	{
		$filters = $this->extractFilters($relative);
		$options = new ORMOptions();
		$options->setFilters($filters);

		$target = $this->controller->getRelative(
			$host_entity,
			$this->relation,
			$options
		);

		if (!$target) {
			throw new ORMException('Unable to identify relative.');
		}

		return $target;
	}

	/**
	 * Try to extract the relative identity filters from the payload.
	 *
	 * @param mixed $payload
	 *
	 * @throws ORMException
	 */
	private function extractFilters(array $payload): array
	{
		try {
			if (empty($payload)) {
				throw new ORMException('Empty relative payload.');
			}

			$instance = ORM::entity($this->table, false, false);

			return $instance->hydrate($payload)->toIdentityFilters();
		} catch (Throwable $t) {
			throw new ORMException('Invalid relative data.', null, $t);
		}
	}
}
