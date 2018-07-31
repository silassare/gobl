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

	use Gobl\DBAL\Column;
	use Gobl\DBAL\Table;

	/**
	 * Class CRUDColumnUpdate
	 *
	 * @package Gobl\CRUD
	 */
	class CRUDColumnUpdate extends CRUDBase
	{
		/**
		 * @var \Gobl\DBAL\Column
		 */
		private $column;

		/**
		 * CRUDColumnUpdate constructor.
		 *
		 * @param \Gobl\DBAL\Table  $table
		 * @param \Gobl\DBAL\Column $column
		 * @param array             $form
		 */
		public function __construct(Table $table, Column $column, array $form)
		{
			parent::__construct(CRUD::COLUMN_UPDATE, $table, $form);

			$this->column = $column;
		}

		/**
		 * @return \Gobl\DBAL\Column
		 */
		public function getColumn()
		{
			return $this->column;
		}
	}