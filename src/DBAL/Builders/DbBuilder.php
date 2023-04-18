<?php

/**
 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Gobl\DBAL\Builders;

use Gobl\DBAL\Interfaces\RDBMSInterface;

/**
 * Class DbBuilder.
 */
final class DbBuilder
{
	/**
	 * DbBuilder constructor.
	 *
	 * @param \Gobl\DBAL\Interfaces\RDBMSInterface $rdbms     The database
	 * @param string                               $namespace The database namespace
	 */
	public function __construct(
		private readonly RDBMSInterface $rdbms,
		private readonly string $namespace,
	) {
	}

	/**
	 * Creates a new table.
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function table(string $name, callable $factory): TableBuilder
	{
		$table_builder = new TableBuilder($this->rdbms, $this->namespace, $name);

		$table_builder->factory($factory);

		$this->rdbms->addTable($table_builder->getTable());

		return $table_builder;
	}
}
