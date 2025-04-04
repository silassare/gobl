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
 * Class BeforePrivateColumnWrite.
 */
class BeforePrivateColumnWrite extends CRUDAction
{
	/**
	 * BeforePrivateColumnWrite constructor.
	 *
	 * @param Table  $table
	 * @param Column $column
	 * @param array  $form
	 */
	public function __construct(Table $table, protected Column $column, array $form)
	{
		parent::__construct(ActionType::PRIVATE_COLUMN_WRITE, $table, $form);
	}

	/**
	 * Returns the column that is being written.
	 *
	 * @return Column
	 */
	public function getColumn(): Column
	{
		return $this->column;
	}
}
