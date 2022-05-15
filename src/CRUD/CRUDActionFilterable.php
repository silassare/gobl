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

namespace Gobl\CRUD;

use Gobl\DBAL\Table;
use Gobl\ORM\ORMTableQuery;

/**
 * Class CRUDActionFilterable.
 */
class CRUDActionFilterable extends CRUDAction
{
	/**
	 * CRUDActionFilterable constructor.
	 *
	 * @param \Gobl\CRUD\CRUDActionType $type
	 * @param \Gobl\DBAL\Table          $table
	 * @param \Gobl\ORM\ORMTableQuery   $filters
	 * @param array                     $form
	 */
	protected function __construct(
		CRUDActionType $type,
		Table $table,
		protected ORMTableQuery $filters,
		array $form = []
	) {
		parent::__construct($type, $table, $form);
	}

	/**
	 * Filters getter.
	 *
	 * @return \Gobl\ORM\ORMTableQuery
	 */
	public function getFilters(): ORMTableQuery
	{
		return $this->filters;
	}
}
