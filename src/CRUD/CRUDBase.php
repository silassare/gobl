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
		 * @var string
		 */
		protected $success = "";
		/**
		 * @var string
		 */
		protected $error = "";

		/**
		 * CRUDContext constructor.
		 *
		 * @param  string          $type
		 * @param \Gobl\DBAL\Table $table
		 */
		protected function __construct($type, Table $table)
		{
			$this->type  = $type;
			$this->table = $table;
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