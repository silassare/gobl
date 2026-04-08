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
use PHPUtils\Lock\Interfaces\LockableInterface;
use PHPUtils\Lock\Traits\PermanentlyLockableTrait;
use PHPUtils\Str;
use PHPUtils\Traits\ArrayCapableTrait;
use Throwable;

/**
 * Class Relation.
 *
 * @implements RelationInterface<ORMEntity,ORMEntity,array,array>
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

	/**
	 * Relation constructor.
	 *
	 * @param RelationType  $type
	 * @param string        $name
	 * @param LinkInterface $link
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
	 * @param RDBMSInterface $rdbms
	 * @param Table          $host_table
	 * @param Table          $target_table
	 * @param array          $options
	 *
	 * @return LinkInterface
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
	 *
	 * @return LinkInterface
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
	 *
	 * @return RelationType
	 */
	public function getType(): RelationType
	{
		return $this->type;
	}

	/**
	 * Gets relation getter method name.
	 *
	 * @return string
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
	 *
	 * @return Table
	 */
	public function getTargetTable(): Table
	{
		return $this->link->getTargetTable();
	}

	#[Override]
	public function toArray(): array
	{
		$options['type']   = $this->type->value;
		$options['target'] = $this->getTargetTable()
			->getName();
		$options['link'] = $this->link->toArray();

		if (null !== $this->_select) {
			$options['select'] = $this->_select;
		}

		return $options;
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
		$this->_select = $columns;

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
	 *
	 * @return static
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
}
