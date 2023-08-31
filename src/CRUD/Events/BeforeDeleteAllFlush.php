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

use Gobl\CRUD\CRUDEvent;
use Gobl\CRUD\Traits\HasFilters;
use Gobl\DBAL\Table;
use Gobl\ORM\ORMTableQuery;

/**
 * Class BeforeDeleteAllFlush.
 */
class BeforeDeleteAllFlush extends CRUDEvent
{
	use HasFilters;

	/**
	 * BeforeDeleteAllFlush constructor.
	 *
	 * @param \Gobl\DBAL\Table        $table
	 * @param \Gobl\ORM\ORMTableQuery $filters
	 */
	public function __construct(Table $table, ORMTableQuery $filters)
	{
		parent::__construct($table);

		$this->filters = $filters;
	}
}
