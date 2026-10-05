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

namespace Gobl\Tests\Integration\DBAL;

use Gobl\DBAL\Builders\TableBuilder;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\ORM\Generators\CSGeneratorORM;
use Gobl\ORM\ORM;
use Gobl\DBAL\Exceptions\DBALUniqueViolationException;
use Gobl\Tests\BaseTestCase;
use Throwable;

/**
 * A write that meets a unique key (or the primary key) is the same error on every database: it names
 * the table and the key's columns, so a caller tells a taken email from any other failure.
 *
 * The table lives in its own namespace, so the shared test schema (and the snapshots built from it)
 * stays untouched.
 */
abstract class DBALUniqueViolationTestCase extends BaseTestCase
{
	protected const NAMESPACE = 'Gobl\Tests\DbUniqueViolation';

	protected static ?RDBMSInterface $db = null;

	protected static bool $setupFailed = false;

	/** @var null|callable the PSR-4 autoloader of the generated classes */
	private static mixed $autoloader = null;

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		static::$setupFailed = false;

		try {
			ORM::undeclareNamespace(static::NAMESPACE);
		} catch (Throwable) {
			// not declared, the expected state
		}

		$out_dir = GOBL_TEST_ORM_OUTPUT . \DIRECTORY_SEPARATOR . 'UniqueViolation';

		try {
			$db = static::getNewDbInstance(static::getDriverName());
		} catch (Throwable $t) {
			static::$setupFailed = true;
			gobl_log(\sprintf('Error building live DB for %s: %s', static::getDriverName(), $t->getMessage()));

			return;
		}

		try {
			if (!\is_dir($out_dir . \DIRECTORY_SEPARATOR . 'Base')) {
				\mkdir($out_dir . \DIRECTORY_SEPARATOR . 'Base', 0o755, true);
			}

			$prefix           = static::NAMESPACE . '\\';
			self::$autoloader = static function (string $class) use ($out_dir, $prefix): void {
				if (!\str_starts_with($class, $prefix)) {
					return;
				}

				$file = $out_dir . \DIRECTORY_SEPARATOR
					. \str_replace('\\', \DIRECTORY_SEPARATOR, \substr($class, \strlen($prefix))) . '.php';

				if (\is_file($file)) {
					require_once $file;
				}
			};

			\spl_autoload_register(self::$autoloader);

			$db->ns(static::NAMESPACE)
				->table('uniq_probes', static function (TableBuilder $tb): void {
					$tb->plural('uniq_probes')
						->singular('uniq_probe')
						->columnPrefix('probe');

					$tb->id();
					$tb->string('code')->max(60);
					$tb->string('zone')->max(10);
					$tb->string('seat')->max(10);
					$tb->unique('code');
					$tb->unique('zone', 'seat');
				});

			$db->ns(static::NAMESPACE)->enableORM($out_dir);

			(new CSGeneratorORM($db))->generate($db->getTables(static::NAMESPACE), $out_dir);

			$db->lock();
			$db->executeMulti($db->getGenerator()->buildDatabase());

			static::$db = $db;
		} catch (Throwable $t) {
			static::$setupFailed = true;
			gobl_log(\sprintf(
				'Error setting up the unique violation DB for %s: %s',
				static::getDriverName(),
				$t->getMessage()
			));
		}
	}

	public static function tearDownAfterClass(): void
	{
		if (null !== static::$db) {
			try {
				static::$db->executeMulti('DROP TABLE IF EXISTS uniq_probes;');
			} catch (Throwable) {
				// best effort
			}

			try {
				ORM::undeclareNamespace(static::NAMESPACE);
			} catch (Throwable) {
				// already undeclared
			}

			static::$db = null;
		}

		if (null !== self::$autoloader) {
			\spl_autoload_unregister(self::$autoloader);
			self::$autoloader = null;
		}

		parent::tearDownAfterClass();
	}

	protected function setUp(): void
	{
		parent::setUp();

		if (static::$setupFailed || null === static::$db) {
			self::markTestSkipped(\sprintf('Live DB not available for %s.', static::getDriverName()));
		}
	}

	public function testATakenValueNamesItsKey(): void
	{
		$table = static::$db->getTableOrFail('uniq_probes');
		$ctrl  = ORM::ctrl($table);

		$ctrl->addItem(['code' => 'taken', 'zone' => 'a', 'seat' => '1']);

		try {
			$ctrl->addItem(['code' => 'taken', 'zone' => 'a', 'seat' => '2']);
			self::fail('a taken value must be refused');
		} catch (DBALUniqueViolationException $e) {
			self::assertSame($table, $e->getTable());
			self::assertSame(['probe_code'], $e->getColumns());
			self::assertSame('GOBL_UNIQUE_KEY_VIOLATION', $e->getMessage());
		}
	}

	public function testATakenPairNamesBothColumns(): void
	{
		$ctrl = ORM::ctrl(static::$db->getTableOrFail('uniq_probes'));

		$ctrl->addItem(['code' => 'first', 'zone' => 'b', 'seat' => '1']);

		try {
			$ctrl->addItem(['code' => 'second', 'zone' => 'b', 'seat' => '1']);
			self::fail('a taken pair must be refused');
		} catch (DBALUniqueViolationException $e) {
			self::assertSame(['probe_zone', 'probe_seat'], $e->getColumns());
		}
	}

	public function testATakenIdNamesThePrimaryKey(): void
	{
		$table   = static::$db->getTableOrFail('uniq_probes');
		$created = ORM::ctrl($table)->addItem(['code' => 'own-id', 'zone' => 'c', 'seat' => '1']);

		try {
			ORM::query($table)
				->insert(['probe_id' => $created->id, 'probe_code' => 'other', 'probe_zone' => 'c', 'probe_seat' => '2'])
				->execute();
			self::fail('a taken id must be refused');
		} catch (DBALUniqueViolationException $e) {
			self::assertSame($table->getPrimaryKeyConstraint(), $e->getConstraint());
			self::assertSame(['probe_id'], $e->getColumns());
		}
	}

	abstract protected static function getDriverName(): string;
}
