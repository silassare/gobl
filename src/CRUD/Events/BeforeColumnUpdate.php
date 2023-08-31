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
use Gobl\DBAL\Column;
use Gobl\DBAL\Table;

/**
 * Class BeforeColumnUpdate.
 */
class BeforeColumnUpdate extends CRUDAction
{
	/**
	 * BeforeColumnUpdate constructor.
	 *
	 * @param \Gobl\DBAL\Table  $table
	 * @param \Gobl\DBAL\Column $column
	 * @param array             $form
	 */
	public function __construct(Table $table, protected Column $column, array $form)
	{
		parent::__construct(ActionType::COLUMN_UPDATE, $table, $form);
	}

	/**
	 * Returns the column that is being updated.
	 *
	 * @return \Gobl\DBAL\Column
	 */
	public function getColumn(): Column
	{
		return $this->column;
	}
}
