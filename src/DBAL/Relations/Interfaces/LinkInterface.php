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

namespace Gobl\DBAL\Relations\Interfaces;

use Gobl\DBAL\Queries\QBSelect;
use Gobl\DBAL\Relations\LinkType;
use Gobl\DBAL\Table;
use Gobl\ORM\ORMEntity;
use PHPUtils\Interfaces\ArrayCapableInterface;

/**
 * Class LinkInterface.
 */
interface LinkInterface extends ArrayCapableInterface
{
	/**
	 * Get the host table.
	 *
	 * @return Table
	 */
	public function getHostTable(): Table;

	/**
	 * Get the target table.
	 *
	 * @return Table
	 */
	public function getTargetTable(): Table;

	/**
	 * Get the link type.
	 *
	 * @return LinkType
	 */
	public function getType(): LinkType;

	/**
	 * Applies the relation link filters to the target query builder.
	 *
	 * When an entity is provided the link is applied only if the host entity has all the required
	 * columns values.
	 *
	 * @param QBSelect                 $target_qb   a select query builder on the target table
	 * @param null|\Gobl\ORM\ORMEntity $host_entity the host entity if any
	 *
	 * @return bool returns true if the relation link was applied, false otherwise
	 */
	public function apply(QBSelect $target_qb, ?ORMEntity $host_entity = null): bool;
}
