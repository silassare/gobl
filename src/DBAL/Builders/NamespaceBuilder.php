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
use Gobl\ORM\ORM;

/**
 * Class NamespaceBuilder.
 */
final class NamespaceBuilder
{
	/**
	 * @var \Gobl\DBAL\Builders\TableBuilder[]
	 */
	private array $cache = [];

	/**
	 * NamespaceBuilder constructor.
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

	/**
	 * Adds tables to the namespace.
	 *
	 * @param array $tables The tables to add
	 */
	public function addTables(array $tables): self
	{
		$this->rdbms->addTables($tables, $this->namespace);

		return $this;
	}

	/**
	 * Enables the ORM for this namespace.
	 *
	 * @return $this
	 */
	public function enableORM(): self
	{
		ORM::setDatabase($this->namespace, $this->rdbms);

		return $this;
	}
}
