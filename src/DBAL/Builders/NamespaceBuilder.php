<?php

/**
 * Copyright (c) Emile Silas Sare.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Gobl\DBAL\Builders;

use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Table;
use Gobl\ORM\ORM;

/**
 * Class NamespaceBuilder.
 */
final class NamespaceBuilder
{
	/**
	 * @var TableBuilder[]
	 */
	private array $cache = [];

	/**
	 * NamespaceBuilder constructor.
	 *
	 * @param RDBMSInterface $rdbms     The database
	 * @param string         $namespace The database namespace
	 */
	public function __construct(
		private readonly RDBMSInterface $rdbms,
		private readonly string $namespace,
	) {}

	/**
	 * Returns (and lazily creates) the `TableBuilder` for the given table name.
	 *
	 * If the table does not yet exist in the RDBMS, a new `Table` is created, added to
	 * the RDBMS, and assigned to this namespace. The builder is cached by name so that
	 * repeated calls return the same instance.
	 *
	 * When a `$factory` is provided it is forwarded to {@see TableBuilder::factory()}.
	 *
	 * @param string                           $name    the table name (without prefix)
	 * @param null|callable(TableBuilder):void $factory optional inline factory for declaring columns/relations
	 *
	 * @return TableBuilder
	 *
	 * @throws DBALRuntimeException when the table exists in a different namespace
	 */
	public function table(string $name, ?callable $factory = null): TableBuilder
	{
		if (!isset($this->cache[$name])) {
			$table = $this->rdbms->getTable($name);

			if (!$table) {
				$table = new Table($name, $this->rdbms->getConfig()
					->getDbTablePrefix());
				// we add the table before running the factory
				// because the factory may need to access the table
				// or use it in column type reference.
				$this->rdbms->addTable($table);
				$table->setNamespace($this->namespace);
			} elseif ($table->getNamespace() !== $this->namespace) {
				throw new DBALRuntimeException(
					\sprintf(
						'Table "%s" already exists in namespace "%s" and cannot be used in namespace "%s".',
						$name,
						$table->getNamespace(),
						$this->namespace
					)
				);
			}

			$this->cache[$name] = new TableBuilder($this->rdbms, $table);
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
	 * Enables the ORM for this namespace, generating entity/query/results/controller classes.
	 *
	 * Calls {@see pack()} as a side effect first, flushing all deferred constraints and
	 * relations before ORM class generation begins. This ordering ensures the table
	 * structure is complete before the generator reads it.
	 *
	 * @param string $out_dir the directory where ORM PHP files will be written
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
	 * Runs `packConstraints()` then `packRelations()` on every cached `TableBuilder`.
	 *
	 * This materializes all deferred FK, index, and relation factory callbacks.
	 * Called automatically by {@see enableORM()} and by `Db::lock()` before sealing
	 * the schema.
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
