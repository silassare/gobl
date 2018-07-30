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
	class CRUDUpdateAll extends CRUDBase
	{
		/**
		 * @var array
		 */
		private $filters;
		/**
		 * @var array
		 */
		private $form;

		public function __construct(Table $table, array $filters, array $form)
		{
			parent::__construct(CRUD::UPDATE_ALL, $table);
			$this->filters = $filters;
			$this->form    = $form;
		}

		/**
		 * @return array
		 */
		public function getFilters()
		{
			return $this->filters;
		}

		/**
		 * @param array $filters
		 */
		public function setFilters(array $filters)
		{
			$this->filters = $filters;
		}

		/**
		 * @return array
		 */
		public function getForm()
		{
			return $this->form;
		}

		/**
		 * @param array $form
		 */
		public function setForm(array $form)
		{
			$this->form = $form;
		}
	}