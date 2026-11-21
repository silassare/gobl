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
use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Relations\Interfaces\LinkInterface;
use Gobl\DBAL\Relations\Interfaces\RelationControllerInterface;
use Gobl\DBAL\Relations\Interfaces\RelationInterface;
use Gobl\DBAL\Table;
use Gobl\Gobl;
use Gobl\ORM\ORMEntity;
use Gobl\ORM\ORMEntityRelationController;
use InvalidArgumentException;
use Override;
use PHPUtils\Interfaces\ArrayCapableInterface;
use Gobl\ORM\Candidates\CandidatesProviderInterface;
use PHPUtils\Lock\Interfaces\LockableInterface;
use ReflectionMethod;
use PHPUtils\Lock\Traits\PermanentlyLockableTrait;
use PHPUtils\Str;
use PHPUtils\Traits\ArrayCapableTrait;
use Throwable;

/**
 * Class Relation.
 *
 * @implements RelationInterface<ORMEntity,array,array>
 */
abstract class Relation implements RelationInterface, ArrayCapableInterface, LockableInterface
{
	use ArrayCapableTrait;
	use PermanentlyLockableTrait {
		PermanentlyLockableTrait::lock as private traitLock;
	}

	/**
	 * Optional column projection applied when loading relatives.
	 *
	 * null means "select all columns" (current default behaviour).
	 * A non-null array restricts the SELECT to those column names.
	 *
	 * @var null|string[]
	 */
	private ?array $_select = null;

	private RelationOnRemove $on_remove = RelationOnRemove::UNLINK;

	private RelationOnAttach $on_attach = RelationOnAttach::REFUSE;

	private RelationJoinWrites $join_writes = RelationJoinWrites::REFUSE;

	/** Whether the relation offers the records a row may be linked to through it. */
	private bool $candidates = false;

	/** @var null|class-string<CandidatesProviderInterface> */
	private ?string $candidates_provider = null;

	/**
	 * Relation constructor.
	 */
	public function __construct(
		protected RelationType $type,
		protected string $name,
		protected LinkInterface $link,
	) {
		if (!\preg_match(self::NAME_REG, $name)) {
			throw new InvalidArgumentException(
				\sprintf(
					'Relation name "%s" should match: %s',
					$name,
					self::NAME_PATTERN
				)
			);
		}
		if (!Gobl::isAllowedRelationName($name)) {
			throw new DBALRuntimeException(
				\sprintf(
					'Relation name "%s" is not allowed.',
					$this->name
				)
			);
		}
	}

	/**
	 * Creates the appropriate `LinkInterface` implementation based on the `type` key in `$options`.
	 *
	 * Dispatches to:
	 * - `LinkType::COLUMNS` -> `LinkColumns`
	 * - `LinkType::MORPH`   -> `LinkMorph`
	 * - `LinkType::THROUGH` -> `LinkThrough` (requires `pivot_table` key)
	 * - `LinkType::JOIN`    -> `LinkJoin`
	 *
	 * Note: `$options['filters']` is accepted here but is **not validated at creation time**
	 * because the target table's generated class files may not yet exist when the schema is
	 * being built. Validation happens lazily when the relation is actually used.
	 *
	 * @throws DBALException
	 */
	public static function createLink(
		RDBMSInterface $rdbms,
		Table $host_table,
		Table $target_table,
		array $options
	): LinkInterface {
		$type    = $options['type'] ?? null;
		$filters = $options['filters'] ?? null;

		if (\is_string($type)) {
			$type = LinkType::tryFrom($type);
		}

		// It will be good if we can validate the filters here
		// for now we can't do that because the target table classes files may not be yet generated
		if ($filters && !\is_array($filters)) {
			throw new DBALException(
				\sprintf(
					'Property "filters" defined in relation link should be of array type not "%s".',
					\get_debug_type($filters)
				)
			);
		}

		if (!$type instanceof LinkType) {
			throw new DBALException('Invalid "type" property for relation link.');
		}

		switch ($type) {
			case LinkType::COLUMNS:
				return new LinkColumns($rdbms, $host_table, $target_table, $options);

			case LinkType::MORPH:
				return new LinkMorph($rdbms, $host_table, $target_table, $options);

			case LinkType::THROUGH:
				$pivot_table = $options['pivot_table'] ?? null;

				if (!$pivot_table) {
					throw new DBALException(
						\sprintf('Property "pivot_table" is required for relation link type "%s".', $type->value)
					);
				}

				if (\is_string($pivot_table)) {
					$pivot_table = $rdbms->getTableOrFail($pivot_table);
				}

				if (!$pivot_table instanceof Table) {
					throw new DBALException(
						\sprintf(
							'property "pivot_table" defined for relation link type "%s" should be of string|%s type not "%s".',
							$type->value,
							Table::class,
							\get_debug_type($pivot_table)
						)
					);
				}

				return new LinkThrough($rdbms, $host_table, $target_table, $pivot_table, $options);

			case LinkType::JOIN:
				return new LinkJoin($rdbms, $host_table, $target_table, $options);
		}

		throw new DBALException(\sprintf('Unsupported link type "%s".', $type->value));
	}

	/**
	 * Gets the relation link.
	 */
	public function getLink(): LinkInterface
	{
		return $this->link;
	}

	#[Override]
	public function isPaginated(): bool
	{
		return $this->type->isMultiple();
	}

	#[Override]
	public function getHostTable(): Table
	{
		return $this->link->getHostTable();
	}

	/**
	 * Gets the relation type.
	 */
	public function getType(): RelationType
	{
		return $this->type;
	}

	/**
	 * Gets relation getter method name.
	 */
	public function getGetterName(): string
	{
		return Str::toGetterName($this->getName());
	}

	#[Override]
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Delegates entirely to the target table's `isPrivate()` state.
	 * A relation to a private table is itself considered private.
	 */
	#[Override]
	public function isPrivate(): bool
	{
		return $this->getTargetTable()->isPrivate();
	}

	/**
	 * Gets the relation target table.
	 */
	public function getTargetTable(): Table
	{
		return $this->link->getTargetTable();
	}

	#[Override]
	public function toArray(): array
	{
		$options           = [];
		$options['type']   = $this->type->value;
		$options['target'] = $this->getTargetTable()
			->getName();
		$options['link'] = $this->link->toArray();

		if (null !== $this->_select) {
			$options['select'] = $this->_select;
		}

		// Written only where they are not the default, so a schema that never declared one reads
		// back as it was written.
		if (RelationOnRemove::UNLINK !== $this->on_remove) {
			$options['on_remove'] = $this->on_remove->value;
		}

		if (RelationOnAttach::REFUSE !== $this->on_attach) {
			$options['on_attach'] = $this->on_attach->value;
		}

		if (RelationJoinWrites::REFUSE !== $this->join_writes) {
			$options['join_writes'] = $this->join_writes->value;
		}

		if ($this->candidates) {
			$options['candidates'] = $this->candidates_provider ?? true;
		}

		return $options;
	}

	/**
	 * Declares that the relation offers its candidates: the records a row may be linked to through
	 * it, found by the server.
	 *
	 * Without a provider they are found by the relation itself, which only the safe cases allow
	 * ({@see automaticCandidatesRefusal()}): another relation is refused when its table is locked. A
	 * provider is a class, never a closure, so a schema array and a migration snapshot carry it.
	 *
	 * @param null|class-string<CandidatesProviderInterface> $provider
	 *
	 * @return $this
	 */
	public function candidates(?string $provider = null): static
	{
		$this->assertNotLocked();

		$this->candidates          = true;
		$this->candidates_provider = $provider;

		return $this;
	}

	/**
	 * Whether the relation offers its candidates.
	 */
	public function hasCandidates(): bool
	{
		return $this->candidates;
	}

	/**
	 * The class narrowing the candidates, or `null` where the relation finds them itself.
	 *
	 * @return null|class-string<CandidatesProviderInterface>
	 */
	public function getCandidatesProvider(): ?string
	{
		return $this->candidates_provider;
	}

	/**
	 * Why the relation cannot find its candidates by itself, or `null` when it can.
	 *
	 * It can when nothing but the target decides them: the host holds the key, on one column, and
	 * the relation's filters name only the target's columns against values written in them; or a
	 * link table holds both links and its filters are equalities on it, which a new link row is
	 * written with. Anything else depends on the host -- a filter on its column, a second key column
	 * -- or has no single row to pick, and needs a provider.
	 */
	public function automaticCandidatesRefusal(): ?string
	{
		$link   = $this->link;
		$target = $this->getTargetTable();

		if ($link instanceof LinkColumns) {
			if (!$link->isHostHoldingKey()) {
				return 'the relative holds the key, so a candidate is a row another host may hold';
			}

			if (1 !== \count($link->getColumnsMapping())) {
				return 'it links on more than one column, and the others depend on the host';
			}

			return LinkFilters::onlyOn($link, $target)
				? null
				: 'its filters name a column that is not the target\'s, or compare with what is not a value';
		}

		if ($link instanceof LinkMorph) {
			if ($link->isHostParent()) {
				return 'the relative holds the link, so a candidate is a row another host may hold';
			}

			return LinkFilters::onlyOn($link, $target)
				? null
				: 'its filters name a column that is not the target\'s, or compare with what is not a value';
		}

		if ($link instanceof LinkThrough) {
			$pivot     = $link->getPivotTable();
			$to_pivot  = $link->getHostToThroughLink();
			$to_target = $link->getThroughToTargetLink();

			$holds_both = (($to_pivot instanceof LinkColumns && !$to_pivot->isHostHoldingKey())
					|| ($to_pivot instanceof LinkMorph && $to_pivot->isHostParent()))
				&& (($to_target instanceof LinkColumns && $to_target->isHostHoldingKey())
					|| ($to_target instanceof LinkMorph && !$to_target->isHostParent()));

			if (!$holds_both) {
				return 'the link table does not hold both links';
			}

			if (
				!LinkFilters::onlyEqualitiesOn($to_pivot, $pivot)
				|| !LinkFilters::onlyEqualitiesOn($to_target, $pivot)
			) {
				return 'a filter of the link table is not an equality on it, which a new link row could be written with';
			}

			return LinkFilters::onlyOn($link, $target)
				? null
				: 'its filters name a column that is not the target\'s, or compare with what is not a value';
		}

		return 'a join has no single row a candidate would be linked through';
	}

	/**
	 * What removing a relative through this relation does.
	 */
	public function getOnRemove(): RelationOnRemove
	{
		return $this->on_remove;
	}

	/**
	 * Declares what removing a relative through this relation does.
	 *
	 * @return $this
	 */
	public function onRemove(RelationOnRemove $strategy): static
	{
		$this->assertNotLocked();

		$this->on_remove = $strategy;

		return $this;
	}

	/**
	 * What attaching a record another row holds does.
	 */
	public function getOnAttach(): RelationOnAttach
	{
		return $this->on_attach;
	}

	/**
	 * Declares what attaching a record another row holds does.
	 *
	 * @return $this
	 */
	public function onAttach(RelationOnAttach $strategy): static
	{
		$this->assertNotLocked();

		$this->on_attach = $strategy;

		return $this;
	}

	/**
	 * What a write through this relation does when it is a join.
	 */
	public function getJoinWrites(): RelationJoinWrites
	{
		return $this->join_writes;
	}

	/**
	 * Declares what a write through this relation does when it is a join.
	 *
	 * @return $this
	 */
	public function joinWrites(RelationJoinWrites $strategy): static
	{
		$this->assertNotLocked();

		$this->join_writes = $strategy;

		return $this;
	}

	/**
	 * Gets the optional column projection for this relation.
	 *
	 * null means all columns are selected (default behaviour).
	 * A non-null array contains the column names to include in the SELECT.
	 *
	 * @return null|string[]
	 */
	public function getSelect(): ?array
	{
		return $this->_select;
	}

	/**
	 * Sets the column projection for this relation.
	 *
	 * @param null|string[] $columns column names to select, or null to select all
	 *
	 * @return $this
	 */
	public function setSelect(?array $columns): static
	{
		$this->_select = empty($columns) ? null : $columns;

		return $this;
	}

	/**
	 * Validates the select projection and resolves it to full column names.
	 *
	 * Returns null when no projection is configured (all columns will be selected).
	 * Throws {@see DBALRuntimeException} when an unknown column
	 * name is encountered.
	 *
	 * @return null|list<string> resolved full column names, or null when getSelect() is null
	 */
	public function resolveSelectColumns(): ?array
	{
		if (null === $this->_select) {
			return null;
		}

		$target  = $this->getTargetTable();
		$list    = [];

		foreach ($this->_select as $name) {
			$col    = $target->getColumnOrFail($name);
			$list[] = $col->getFullName();
		}

		return $list;
	}

	/**
	 * Locks this relation to prevent further changes.
	 */
	#[Override]
	public function lock(): static
	{
		if (!$this->isLocked()) {
			$this->assertIsValid();

			$this->traitLock();
		}

		return $this;
	}

	#[Override]
	public function getController(): RelationControllerInterface
	{
		return new ORMEntityRelationController($this);
	}

	/**
	 * Asserts that this relation is valid.
	 */
	protected function assertIsValid(): void
	{
		if ($this->candidates) {
			$this->assertCandidatesAreFound();
		}

		if (null !== $this->_select && !empty($this->_select)) {
			$target = $this->getTargetTable();

			foreach ($this->_select as $name) {
				try {
					$target->assertHasColumn($name);
				} catch (Throwable $t) {
					throw new DBALRuntimeException(
						\sprintf(
							'Unknown column name "%s" in select projection of relation "%s".',
							$name,
							$this->name
						),
						null,
						$t
					);
				}
			}
		}
	}

	/**
	 * Refuses candidates that cannot be found: automatic ones where the relation is not a safe case,
	 * a provider that is not a class implementing the interface with an `__invoke` of one argument.
	 */
	private function assertCandidatesAreFound(): void
	{
		$provider = $this->candidates_provider;

		if (null === $provider) {
			$refusal = $this->automaticCandidatesRefusal();

			if (null !== $refusal) {
				throw new DBALRuntimeException(\sprintf(
					'The relation "%s" cannot find its candidates by itself: %s. Name a provider class: ->candidates(Provider::class).',
					$this->name,
					$refusal
				));
			}

			return;
		}

		if (!\class_exists($provider) || !\is_subclass_of($provider, CandidatesProviderInterface::class)) {
			throw new DBALRuntimeException(\sprintf(
				'The candidates provider "%s" of the relation "%s" is not a class implementing "%s".',
				$provider,
				$this->name,
				CandidatesProviderInterface::class
			));
		}

		if (!\method_exists($provider, '__invoke') || 1 !== new ReflectionMethod($provider, '__invoke')->getNumberOfParameters()) {
			throw new DBALRuntimeException(\sprintf(
				'The candidates provider "%s" of the relation "%s" must define __invoke() taking the context the ORM generates for it.',
				$provider,
				$this->name
			));
		}
	}
}
