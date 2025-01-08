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

use Gobl\CRUD\CRUDAction;
use Gobl\CRUD\Enums\ActionType;
use Gobl\CRUD\Traits\HasFilters;
use Gobl\DBAL\Table;
use Gobl\ORM\ORMTableQuery;

/**
 * Class BeforeDeleteAll.
 */
class BeforeDeleteAll extends CRUDAction
{
	use HasFilters;

	/**
	 * BeforeDeleteAll constructor.
	 *
	 * @param Table         $table
	 * @param ORMTableQuery $filters
	 */
	public function __construct(Table $table, ORMTableQuery $filters)
	{
		parent::__construct(ActionType::DELETE_ALL, $table);

		$this->filters = $filters;
	}
}
