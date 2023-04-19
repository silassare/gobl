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
	 * @var \Gobl\DBAL\Builders\TableBuilder[]
	 */
	private array $cache = [];

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
	 * Returns the table builder instance for the given table name.
	 *
	 * @param string                      $name    The table name
	 * @param callable(TableBuilder):void $factory The table factory
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function table(string $name, callable $factory): TableBuilder
	{
		if (isset($this->cache[$name])) {
			return $this->cache[$name];
		}

		$this->cache[$name] = $table_builder = new TableBuilder($this->rdbms, $this->namespace, $name);

		$table_builder->factory($factory);

		$this->rdbms->addTable($table_builder->getTable());

		return $table_builder;
	}
}
