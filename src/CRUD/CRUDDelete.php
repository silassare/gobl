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
	class CRUDDelete extends CRUDBase
	{
		/**
		 * @var array
		 */
		private $filters;

		public function __construct(Table $table, array $filters)
		{
			parent::__construct(CRUD::DELETE, $table);
			$this->filters = $filters;
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
	}