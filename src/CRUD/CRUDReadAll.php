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
	 * Class CRUDReadAll
	 *
	 * @package Gobl\CRUD
	 */
	class CRUDReadAll extends CRUDBase
	{
		/**
		 * @var array
		 */
		private $filters;

		public function __construct(Table $table, array $filters)
		{
			parent::__construct(CRUD::READ_ALL, $table);
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

		/**
		 * @param string $column
		 * @param mixed  $filter
		 *
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function setFilter($column, $filter)
		{
			$this->table->assertHasColumn($column);
			$this->filters[$column] = $filter;
		}
	}