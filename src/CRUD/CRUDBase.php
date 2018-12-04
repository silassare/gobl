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
	 * Class CRUDBase
	 *
	 * @package Gobl\CRUD
	 */
	class CRUDBase
	{
		/**
		 * @var string
		 */
		protected $type;
		/**
		 * @var \Gobl\DBAL\Table
		 */
		protected $table;

		/**
		 * @var array
		 */
		protected $form;

		/**
		 * @var string
		 */
		protected $success = "OK";
		/**
		 * @var string
		 */
		protected $error = "ERROR";

		/**
		 * CRUDContext constructor.
		 *
		 * @param  string          $type
		 * @param \Gobl\DBAL\Table $table
		 * @param array            $form
		 */
		protected function __construct($type, Table $table, array $form = [])
		{
			$this->type  = $type;
			$this->table = $table;
			$this->form  = $form;
		}

		/**
		 * @return string
		 */
		public function getType()
		{
			return $this->type;
		}

		/**
		 * @return \Gobl\DBAL\Table
		 */
		public function getTable()
		{
			return $this->table;
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
		 *
		 * @return \Gobl\CRUD\CRUDBase
		 */
		public function setForm(array $form)
		{
			$this->form = $form;

			return $this;
		}

		/**
		 * @param $column
		 * @param $value
		 *
		 * @return \Gobl\CRUD\CRUDBase
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function setField($column, $value)
		{
			$this->table->assertHasColumn($column);

			$this->form[$column] = $value;

			return $this;
		}

		/**
		 * @param $column
		 *
		 * @return mixed
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function getField($column)
		{
			$this->table->assertHasColumn($column);

			if (isset($this->form[$column])) {
				return $this->form[$column];
			}

			return null;
		}

		/**
		 * @param string $error
		 *
		 * @return \Gobl\CRUD\CRUDBase
		 */
		public function setError($error)
		{
			$this->error = $error;

			return $this;
		}

		/**
		 * @param string $success
		 *
		 * @return \Gobl\CRUD\CRUDBase
		 */
		public function setSuccess($success)
		{
			$this->success = $success;

			return $this;
		}

		/**
		 * @return string
		 */
		public function getError()
		{
			return $this->error;
		}

		/**
		 * @return string
		 */
		public function getSuccess()
		{
			return $this->success;
		}
	}