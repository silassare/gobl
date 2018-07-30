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
	 * Class CRUDCreate
	 *
	 * @package Gobl\CRUD
	 */
	class CRUDCreate extends CRUDBase
	{
		/**
		 * @var array
		 */
		private $form;

		public function __construct(Table $table, array $form)
		{
			parent::__construct(CRUD::CREATE, $table);
			$this->form = $form;
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