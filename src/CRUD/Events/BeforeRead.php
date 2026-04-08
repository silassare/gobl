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

namespace Gobl\CRUD\Events;

use Gobl\CRUD\CRUDAction;
use Gobl\CRUD\Enums\ActionType;
use Gobl\CRUD\Traits\HasFilters;
use Gobl\CRUD\Traits\HasRelationContext;
use Gobl\DBAL\Relations\Relation;
use Gobl\DBAL\Table;
use Gobl\ORM\ORMTableQuery;

/**
 * Class BeforeRead.
 */
final class BeforeRead extends CRUDAction
{
	use HasFilters;
	use HasRelationContext;

	/**
	 * BeforeRead constructor.
	 *
	 * @param Table         $table
	 * @param ORMTableQuery $filters
	 * @param null|Relation $relation
	 */
	public function __construct(Table $table, ORMTableQuery $filters, ?Relation $relation = null)
	{
		parent::__construct(ActionType::READ, $table);

		$this->filters  = $filters;
		$this->relation = $relation;
	}
}
