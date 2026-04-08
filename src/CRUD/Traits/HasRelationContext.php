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

namespace Gobl\CRUD\Traits;

use Gobl\DBAL\Relations\Relation;

/**
 * Trait HasRelationContext.
 */
trait HasRelationContext
{
	protected ?Relation $relation = null;

	/**
	 * Returns the relation.
	 *
	 * @return null|Relation
	 */
	public function getRelation(): ?Relation
	{
		return $this->relation;
	}
}
