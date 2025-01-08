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

use Gobl\DBAL\Filters\Filters;
use Gobl\DBAL\Filters\FiltersTableScope;
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
	 * @param LinkType $type
	 * @param Table    $host_table
	 * @param Table    $target_table
	 * @param array    $options
	 */
	public function __construct(
		protected readonly LinkType $type,
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
	 * Abstract method to run the link filters logic.
	 */
	abstract protected function runLinkTypeApplyLogic(QBSelect $target_qb, ?ORMEntity $host_entity = null): bool;
}
