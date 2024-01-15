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
use Gobl\DBAL\Table;

/**
 * Class BeforeCreate.
 */
class BeforeCreate extends CRUDAction
{
	/**
	 * BeforeCreate constructor.
	 *
	 * @param Table $table
	 * @param array $form
	 */
	public function __construct(Table $table, array $form)
	{
		parent::__construct(ActionType::CREATE, $table, $form);
	}
}
