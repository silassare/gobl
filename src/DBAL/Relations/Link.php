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
use Gobl\DBAL\Filters\FiltersMultiScope;
use Gobl\DBAL\Filters\FiltersTableScope;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\DBAL\Relations\Interfaces\LinkInterface;
use Gobl\DBAL\Table;
use Gobl\ORM\ORMEntity;
use Override;
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class Link.
 */
abstract class Link implements LinkInterface
{
	use ArrayCapableTrait;

	/**
	 * Link constructor.
	 *
	 * @param LinkType       $type
	 * @param RDBMSInterface $rdbms
	 * @param Table          $host_table
	 * @param Table          $target_table
	 * @param array          $options
	 */
	public function __construct(
		protected readonly LinkType $type,
		protected readonly RDBMSInterface $rdbms,
		protected readonly Table $host_table,
		protected readonly Table $target_table,
		protected readonly array $options,
	) {}

	#[Override]
	public function getHostTable(): Table
	{
		return $this->host_table;
	}

	#[Override]
	public function getTargetTable(): Table
	{
		return $this->target_table;
	}

	#[Override]
	public function getType(): LinkType
	{
		return $this->type;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Two-phase execution:
	 * 1. Calls `runLinkTypeApplyLogic()` (implemented by each concrete subclass) which adds
	 *    the join / where conditions specific to the link type.
	 * 2. If that succeeds **and** `$options['filters']` is non-empty, appends those extra
	 *    filters to the target QB using a `FiltersMultiScope` that covers both the
	 *    target table (highest priority) and the host table. This lets link-level filters
	 *    safely reference columns on either table - e.g. a pivot column in a
	 *    `pivot_to_target` sub-link of a `through` relation.
	 *
	 * Returns `false` when the link cannot be applied (e.g., a required entity column is null).
	 */
	#[Override]
	final public function apply(QBSelect $target_qb, ?ORMEntity $host_entity = null): bool
	{
		if ($this->runLinkTypeApplyLogic($target_qb, $host_entity)) {
			$link_filters = $this->options['filters'] ?? [];

			if (!empty($link_filters)) {
				// Use declare=true so that getMainAlias never throws even if the host
				// table is not yet registered (the filter may only reference target columns).
				$target_alias = $target_qb->getMainAlias($this->target_table, true);
				$host_alias   = $target_qb->getMainAlias($this->host_table, true);

				$t_scope = new FiltersTableScope($this->target_table, $target_alias);
				$h_scope = new FiltersTableScope($this->host_table, $host_alias);

				$scope_bundle = new FiltersMultiScope();

				// target has highest priority
				$scope_bundle->push($t_scope, $this->target_table, $target_alias);
				// host has second priority (allows referencing host columns, e.g. pivot columns, in filters)
				$scope_bundle->push($h_scope, $this->host_table, $host_alias);

				$target_qb->andWhere(Filters::fromArray($link_filters, $target_qb, $scope_bundle));
			}

			return true;
		}

		return false;
	}

	/**
	 * Creates a sub-link between two tables via `Relation::createLink()`.
	 *
	 * When `$allow_nesting` is `false` (the default), composite link types
	 * (`LinkJoin` and `LinkThrough`) are rejected as sub-links because they
	 * require their own sub-links internally, which would produce unbounded
	 * recursive join chains and malformed SQL.
	 *
	 * Only "simple" link types - `LinkColumns` and `LinkMorph` - are nestable:
	 * - `LinkColumns` maps FK columns directly and generates no extra joins.
	 * - `LinkMorph` adds a single polymorphic constraint, also without extra joins.
	 *
	 * Why `LinkJoin` cannot be nested:
	 * A `LinkJoin` is itself composed of one or more sub-links. Embedding a `LinkJoin`
	 * as a sub-link would create a recursive structure where each level could
	 * add arbitrary JOINs and WHERE conditions - it becomes impossible to validate
	 * or generate deterministic SQL.
	 *
	 * @param Table $host_table
	 * @param Table $target_table
	 * @param array $options
	 * @param bool  $allow_nesting when `true`, all link types are permitted as sub-links
	 *
	 * @throws DBALException when a composite link type is used without `$allow_nesting = true`
	 */
	protected function subLink(Table $host_table, Table $target_table, array $options, bool $allow_nesting = false): LinkInterface
	{
		$link = Relation::createLink($this->rdbms, $host_table, $target_table, $options);

		if (!$allow_nesting && ($link instanceof LinkJoin || $link instanceof LinkThrough)) {
			throw new DBALException(\sprintf(
				'Link type "%s" cannot be used as a sub-link inside "%s". '
					. 'Only "%s" and "%s" are nestable as sub-links.',
				$link->getType()->value,
				$this->type->value,
				LinkType::COLUMNS->value,
				LinkType::MORPH->value,
			));
		}

		return $link;
	}

	/**
	 * Abstract method to run the link filters logic.
	 */
	abstract protected function runLinkTypeApplyLogic(QBSelect $target_qb, ?ORMEntity $host_entity = null): bool;
}
