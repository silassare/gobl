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
	 * Class CRUDDelete
	 *
	 * @package Gobl\CRUD
	 */
	class CRUDDelete extends CRUDFilterableAction
	{
		/**
		 * CRUDDeleteAction constructor.
		 *
		 * @param \Gobl\DBAL\Table $table
		 * @param array            $filters
		 */
		public function __construct(Table $table, array $filters)
		{
			parent::__construct(CRUD::DELETE, $table, $filters);

			$this->setError("DELETE_ERROR")
				 ->setSuccess("DELETED");
		}
	}
