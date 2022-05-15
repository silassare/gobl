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

/**
 * Class CRUDCreate.
 */
class CRUDCreate extends CRUDAction
{
	/**
	 * CRUDCreate constructor.
	 *
	 * @param \Gobl\DBAL\Table $table
	 * @param array            $form
	 */
	public function __construct(Table $table, array $form)
	{
		parent::__construct(CRUDActionType::CREATE, $table, $form);
	}
}
