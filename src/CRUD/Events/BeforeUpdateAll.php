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
 * Class BeforeUpdateAll.
 */
class BeforeUpdateAll extends CRUDAction
{
	use HasFilters;

	/**
	 * BeforeUpdateAll constructor.
	 *
	 * @param \Gobl\DBAL\Table        $table
	 * @param \Gobl\ORM\ORMTableQuery $filters
	 * @param array                   $form
	 */
	public function __construct(Table $table, ORMTableQuery $filters, array $form)
	{
		parent::__construct(ActionType::UPDATE_ALL, $table, $form);

		$this->filters = $filters;
	}
}
