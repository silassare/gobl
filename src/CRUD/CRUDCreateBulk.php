<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the Gobl package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace Gobl\CRUD;

	use Gobl\DBAL\Table;

	/**
	 * Class CRUDCreateBulk
	 *
	 * @package Gobl\CRUD
	 */
	class CRUDCreateBulk extends CRUDBase
	{
		/**
		 * CRUDCreateBulk constructor.
		 *
		 * @param \Gobl\DBAL\Table $table
		 * @param array            $form_list
		 */
		public function __construct(Table $table, array $form_list)
		{
			parent::__construct(CRUD::CREATE_BULK, $table, $form_list);
		}
	}