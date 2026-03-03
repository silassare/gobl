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
use Gobl\DBAL\Filters\FiltersTableScope;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\DBAL\Relations\Interfaces\LinkInterface;
use Gobl\DBAL\Table;
use Gobl\ORM\ORMEntity;
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

	/**
	 * {@inheritDoc}
	 */
	public function getHostTable(): Table
	{
		return $this->host_table;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getTargetTable(): Table
	{
		return $this->target_table;
	}

	/**
	 * {@inheritDoc}
	 */
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
	 *    filters to the target QB using a `FiltersTableScope` for the target table.
	 *
	 * Returns `false` when the link cannot be applied (e.g., a required entity column is null).
	 */
	final public function apply(QBSelect $target_qb, ?ORMEntity $host_entity = null): bool
	{
		if ($this->runLinkTypeApplyLogic($target_qb, $host_entity)) {
			$link_filters = $this->options['filters'] ?? [];

			if (!empty($link_filters)) {
				$alias = $target_qb->getMainAlias($this->target_table);
				$scope = new FiltersTableScope($this->target_table, $alias);

				$target_qb->andWhere(Filters::fromArray($link_filters, $target_qb, $scope));
			}

			return true;
		}

		return false;
	}

	/**
	 * Creates a sub-link between two tables via `Relation::createLink()`.
	 *
	 * When `$allow_nesting` is `false` (the default), the created link must itself be a
	 * direct `Link` subclass — `LinkJoin` children are rejected to prevent unbounded recursive
	 * join chains that could generate malformed SQL.
	 *
	 * @param Table $host_table
	 * @param Table $target_table
	 * @param array $options
	 * @param bool  $allow_nesting when `false`, rejects `LinkJoin` sub-links (prevents recursion)
	 *
	 * @throws DBALException
	 */
	protected function subLink(Table $host_table, Table $target_table, array $options, bool $allow_nesting = false): LinkInterface
	{
		$link = Relation::createLink($this->rdbms, $host_table, $target_table, $options);

		if (!$allow_nesting && !$link instanceof self) {
			throw new DBALException(\sprintf('The link type "%s" cannot be nested. Keep it simple.', $this->type->value));
		}

		return $link;
	}

	/**
	 * Abstract method to run the link filters logic.
	 */
	abstract protected function runLinkTypeApplyLogic(QBSelect $target_qb, ?ORMEntity $host_entity = null): bool;
}
