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
use Gobl\CRUD\Traits\HasFormEditable;
use Gobl\DBAL\Table;
use Gobl\ORM\ORMTableQuery;

/**
 * Class BeforeUpdateAllFlush.
 */
class BeforeUpdateAllFlush extends CRUDEvent
{
	use HasFilters;
	use HasFormEditable;

	/**
	 * BeforeUpdateAllFlush constructor.
	 *
	 * @param Table         $table
	 * @param ORMTableQuery $filters
	 * @param array         $form
	 */
	public function __construct(Table $table, ORMTableQuery $filters, array $form)
	{
		parent::__construct($table, $form);

		$this->filters = $filters;
	}
}
