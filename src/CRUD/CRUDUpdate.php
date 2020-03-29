<?php

/**
 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gobl\CRUD;

use Gobl\DBAL\Table;

/**
 * Class CRUDUpdate
 */
class CRUDUpdate extends CRUDFilterableAction
{
	/**
	 * CRUDUpdate constructor.
	 */
	public function __construct(Table $table, array $filters, array $form)
	{
		parent::__construct(CRUD::UPDATE, $table, $filters, $form);

		$this->setError('UPDATE_ERROR')
			 ->setSuccess('UPDATED');
	}
}
