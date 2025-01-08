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

namespace Gobl\CRUD\Events;

use Gobl\ORM\ORMEntity;
use PHPUtils\Events\Event;

/**
 * Class EntityEvent.
 *
 * @template TEntity of \Gobl\ORM\ORMEntity
 */
class EntityEvent extends Event
{
	/**
	 * EntityEvent constructor.
	 *
	 * @psalm-param TEntity $entity
	 */
	public function __construct(
		protected ORMEntity $entity,
	) {}

	/**
	 * Return the target entity.
	 *
	 * @return TEntity
	 */
	public function getEntity(): ORMEntity
	{
		return $this->entity;
	}
}
