<?php

/**
 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gobl\DBAL\Relations;

use Gobl\DBAL\Table;

class OneToOne extends Relation
{
	/**
	 * OneToOne constructor.
	 *
	 * @param                  $name
	 * @param \Gobl\DBAL\Table $host_table
	 * @param \Gobl\DBAL\Table $target_table
	 * @param null|array       $columns
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function __construct($name, Table $host_table, Table $target_table, array $columns = null)
	{
		parent::__construct($name, $host_table, $target_table, $columns, Relation::ONE_TO_ONE);
	}
}
