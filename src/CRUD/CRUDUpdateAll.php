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
	 * Class CRUDUpdateAll
	 *
	 * @package Gobl\CRUD
	 */
	class CRUDUpdateAll extends CRUDFilterableAction
	{
		/**
		 * CRUDUpdateAll constructor.
		 *
		 * @param \Gobl\DBAL\Table $table
		 * @param array            $filters
		 * @param array            $form
		 */
		public function __construct(Table $table, array $filters, array $form)
		{
			parent::__construct(CRUD::UPDATE_ALL, $table, $filters, $form);
		}
	}