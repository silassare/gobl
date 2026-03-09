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

namespace Gobl\ORM;

use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\DBAL\Table;
use Gobl\Gobl;
use Gobl\ORM\Exceptions\ORMQueryException;
use Gobl\ORM\Exceptions\ORMRuntimeException;
use Gobl\ORM\Utils\ORMClassKind;
use PHPUtils\FS\FSUtils;
use Throwable;

/**
 * Class ORM.
 */
class ORM
{
	/** @var array<string, array{db:RDBMSInterface, out_dir:string}> */
	private static array $namespaces = [];

	/**
	 * Registers a database namespace with the ORM.
	 *
	 * Associates `$namespace` with a database instance and an on-disk output directory
	 * (where generated entity/controller/result classes live). The directory is resolved
	 * relative to the project cache dir via `FSUtils` and must be a writable directory.
	 *
	 * Throws `ORMRuntimeException` if the namespace is already declared or if
	 * `$out_dir` is not a writable directory.
	 *
	 * @param string         $namespace
	 * @param RDBMSInterface $db
	 * @param string         $out_dir
	 */
	public static function declareNamespace(string $namespace, RDBMSInterface $db, string $out_dir): void
	{
		if (isset(self::$namespaces[$namespace])) {
			throw new ORMRuntimeException(\sprintf('Namespace "%s" is already declared in the ORM.', $namespace));
		}

		$fs       = new FSUtils(Gobl::getProjectCacheDir());
		$abs_path = $fs->resolve($out_dir);

		try {
			$fs->filter()
				->isWritable()
				->isDir()
				->assert($abs_path);

			self::$namespaces[$namespace] = [
				'db'      => $db,
				'out_dir' => $abs_path,
			];
		} catch (Throwable $t) {
			throw new ORMRuntimeException(\sprintf('Invalid ORM output directory declared for "%s".', $namespace), [
				'out_dir'       => $out_dir,
				'absolute_path' => $abs_path,
				'namespace'     => $namespace,
			], $t);
		}
	}

	/**
	 * Returns the table instance for a given table name in a given namespace.
	 *
	 * @param string $namespace  the database namespace
	 * @param string $table_name the table name
	 *
	 * @return Table
	 */
	public static function table(string $namespace, string $table_name): Table
	{
		return self::getDatabase($namespace)
			->getTableOrFail($table_name);
	}

	/**
	 * Returns the db instance for the given namespace.
	 *
	 * @param string $namespace the database namespace
	 *
	 * @return RDBMSInterface
	 */
	public static function getDatabase(string $namespace): RDBMSInterface
	{
		if (!isset(self::$namespaces[$namespace])) {
			throw new ORMRuntimeException(\sprintf('Namespace "%s" was not declared in the ORM.', $namespace));
		}

		return self::$namespaces[$namespace]['db'];
	}

	/**
	 * Replaces the db instance for an already-declared namespace.
	 *
	 * Use this to point a namespace at a different RDBMS instance (e.g. in tests
	 * when switching between MySQL, PostgreSQL, and SQLite) without tearing down
	 * and re-declaring the whole namespace.
	 *
	 * @param string         $namespace the database namespace
	 * @param RDBMSInterface $db        the new db instance to use
	 */
	public static function setDatabase(string $namespace, RDBMSInterface $db): void
	{
		if (!isset(self::$namespaces[$namespace])) {
			throw new ORMRuntimeException(\sprintf('Namespace "%s" was not declared in the ORM.', $namespace));
		}

		self::$namespaces[$namespace]['db'] = $db;
	}

	/**
	 * Removes a single declared namespace.
	 *
	 * Mainly useful in tests to allow re-declaring a namespace with different settings.
	 *
	 * @param string $namespace the database namespace to remove
	 */
	public static function undeclareNamespace(string $namespace): void
	{
		unset(self::$namespaces[$namespace]);
	}

	/**
	 * Removes all declared namespaces.
	 *
	 * Useful in test teardown to reset global ORM state between test runs.
	 */
	public static function resetNamespaces(): void
	{
		self::$namespaces = [];
	}

	/**
	 * Returns the output directory for the given namespace.
	 *
	 * @param string $namespace the database namespace
	 *
	 * @return string
	 */
	public static function getOutputDirectory(string $namespace): string
	{
		if (!isset(self::$namespaces[$namespace])) {
			throw new ORMRuntimeException(\sprintf('Namespace "%s" was not declared in the ORM.', $namespace));
		}

		return self::$namespaces[$namespace]['out_dir'];
	}

	/**
	 * Returns a new entity controller instance for a given table.
	 *
	 * @param Table $table
	 *
	 * @return ORMController
	 */
	public static function ctrl(Table $table): ORMController
	{
		/** @var ORMController $ctrl_class */
		$ctrl_class = ORMClassKind::CONTROLLER->getClassFQN($table);

		return $ctrl_class::new();
	}

	/**
	 * Returns a new entity instance for a given table.
	 *
	 * @param Table $table
	 * @param bool  $is_new
	 * @param bool  $strict
	 *
	 * @return ORMEntity
	 */
	public static function entity(Table $table, bool $is_new = true, bool $strict = true): ORMEntity
	{
		/** @var ORMEntity $entity_class */
		$entity_class = ORMClassKind::ENTITY->getClassFQN($table);

		return $entity_class::new($is_new, $strict);
	}

	/**
	 * Returns a new entity results instance for a given table and queries.
	 *
	 * @param Table    $table
	 * @param QBSelect $qb
	 *
	 * @return ORMResults
	 */
	public static function results(Table $table, QBSelect $qb): ORMResults
	{
		/** @var ORMResults $results_class */
		$results_class = ORMClassKind::RESULTS->getClassFQN($table);

		return $results_class::new($qb);
	}

	/**
	 * Returns a new table query instance for a given table and filters.
	 *
	 * @param Table $table
	 * @param array $filters
	 *
	 * @return ORMTableQuery
	 */
	public static function query(Table $table, array $filters = []): ORMTableQuery
	{
		/** @var ORMTableQuery $class */
		$class = ORMClassKind::QUERY->getClassFQN($table);

		$tq = $class::new();

		if (!empty($filters)) {
			try {
				$tq->where($filters);
			} catch (Throwable $t) {
				throw new ORMQueryException('Failed to apply filters to query.', [
					'_filters' => $filters,
					'_table'   => $table->getName(),
				], $t);
			}
		}

		return $tq;
	}
}
