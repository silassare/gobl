<?php

/**
 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gobl\CRUD;

use Gobl\DBAL\Table;

/**
 * Class CRUDFilterableAction
 */
class CRUDFilterableAction extends CRUDBase
{
	/**
	 * @var array
	 */
	protected $filters;

	/**
	 * CRUDFilterable constructor.
	 *
	 * @param string           $type
	 * @param \Gobl\DBAL\Table $table
	 * @param array            $filters
	 * @param array            $form
	 */
	protected function __construct($type, Table $table, array $filters, array $form = [])
	{
		parent::__construct($type, $table, $form);

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
	 *
	 * @return \Gobl\CRUD\CRUDFilterableAction
	 */
	public function setFilters(array $filters)
	{
		$this->filters = $filters;

		return $this;
	}

	/**
	 * @param string $column
	 * @param mixed  $filter
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 *
	 * @return \Gobl\CRUD\CRUDFilterableAction
	 */
	public function setFilter($column, $filter)
	{
		$this->table->assertHasColumn($column);

		$this->filters[$column] = $filter;

		return $this;
	}
}
