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
use Gobl\DBAL\Column;
use Gobl\DBAL\Table;

/**
 * Class BeforeColumnPKWrite.
 */
final class BeforePKColumnWrite extends CRUDAction
{
	/**
	 * BeforePKColumnWrite constructor.
	 */
	public function __construct(Table $table, protected Column $column, array $form, protected readonly bool $updating)
	{
		parent::__construct(ActionType::PK_COLUMN_WRITE, $table, $form);
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

	/**
	 * Returns true if this action results from an update action.
	 */
	public function isUpdating(): bool
	{
		return $this->updating;
	}
}
