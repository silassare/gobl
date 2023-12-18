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

use Gobl\DBAL\Exceptions\DBALException;
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
	 * @param string                           $name    The table name
	 * @param null|callable(TableBuilder):void $factory The table factory
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function table(string $name, ?callable $factory = null): TableBuilder
	{
		if (!isset($this->cache[$name])) {
			$this->cache[$name] = new TableBuilder($this->rdbms, $this->namespace, $name);

			// we add the table before running the factory
			// because the factory may need to access the table
			// or use it in column type reference.
			$this->rdbms->addTable($this->cache[$name]->getTable());
		}

		$factory && $this->cache[$name]->factory($factory);

		return $this->cache[$name];
	}

	/**
	 * Loads a schema to this namespace.
	 *
	 * @param array $schema
	 *
	 * @return $this
	 */
	public function schema(array $schema): self
	{
		$this->rdbms->loadSchema($schema, $this->namespace);

		return $this;
	}

	/**
	 * Enables the ORM for this namespace.
	 *
	 * @param string $out_dir The ORM output directory
	 *
	 * @return $this
	 *
	 * @throws DBALException
	 */
	public function enableORM(string $out_dir): self
	{
		$this->pack();
		ORM::declareNamespace($this->namespace, $this->rdbms, $out_dir);

		return $this;
	}

	/**
	 * Packs all tables in this namespace.
	 *
	 * @throws DBALException
	 *
	 * @internal should be called only by the RDBMS instance before locking
	 */
	public function pack(): void
	{
		foreach ($this->cache as $tb) {
			$tb->packConstraints();
		}
		foreach ($this->cache as $tb) {
			$tb->packRelations();
		}
	}
}
