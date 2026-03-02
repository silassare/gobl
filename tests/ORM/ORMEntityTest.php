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

namespace Gobl\Tests\ORM;

use Gobl\DBAL\Drivers\MySQL\MySQL;
use Gobl\ORM\Exceptions\ORMRuntimeException;
use Gobl\ORM\Generators\CSGeneratorORM;
use Gobl\ORM\ORM;
use Gobl\ORM\ORMEntity;
use Gobl\Tests\BaseTestCase;
use Throwable;

/**
 * Class ORMEntityTest.
 *
 * Unit/integration tests for ORMEntity behaviour that do NOT require a live database
 * connection. The ORM namespace is declared with a schema-only in-memory MySQL db instance,
 * and PHP entity classes are generated once into tests/Db/ (the PSR-4 output directory).
 *
 * @covers \Gobl\ORM\ORMEntity
 *
 * @internal
 */
final class ORMEntityTest extends BaseTestCase
{
	/** @var bool Whether ORM setup completed successfully */
	private static bool $setupOk = false;

	// -------------------------------------------------------------------------
	// PHPUnit lifecycle
	// -------------------------------------------------------------------------

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		// Guard: if a stale namespace is already registered (e.g. test isolation failure),
		// clean it up so we can re-declare cleanly.
		try {
			ORM::getDatabase(self::TEST_DB_NAMESPACE);
			ORM::undeclareNamespace(self::TEST_DB_NAMESPACE);
		} catch (Throwable) {
			// not declared, expected
		}

		$ormOutDir = GOBL_TEST_ORM_OUTPUT;

		if (!\is_dir($ormOutDir . \DIRECTORY_SEPARATOR . 'Base')) {
			\mkdir($ormOutDir . \DIRECTORY_SEPARATOR . 'Base', 0o755, true);
		}

		// Build a schema-only db instance (no live DB connection needed)
		$db = self::getNewDbInstance(MySQL::NAME);
		$db->ns(self::TEST_DB_NAMESPACE)
			->schema(self::getTablesDefinitions())
			->enableORM($ormOutDir);

		(new CSGeneratorORM($db))->generate($db->getTables(), $ormOutDir);

		$db->lock();

		self::$setupOk = true;
	}

	public static function tearDownAfterClass(): void
	{
		try {
			ORM::undeclareNamespace(self::TEST_DB_NAMESPACE);
		} catch (Throwable) {
			// already undeclared
		}

		self::$setupOk = false;

		parent::tearDownAfterClass();
	}

	protected function setUp(): void
	{
		parent::setUp();

		if (!self::$setupOk) {
			self::markTestSkipped('ORM entity test setup failed.');
		}
	}

	// -------------------------------------------------------------------------
	// Tests: new entity initialisation
	// -------------------------------------------------------------------------

	/**
	 * A newly created entity reports isNew() === true and isSaved() === false.
	 */
	public function testNewEntityState(): void
	{
		$table  = ORM::getDatabase(self::TEST_DB_NAMESPACE)->getTableOrFail('clients');
		$entity = ORM::entity($table);

		self::assertTrue($entity->isNew());
		self::assertFalse($entity->isSaved());
	}

	/**
	 * The auto-increment id column returns null on a brand-new entity.
	 */
	public function testAutoIncrementColumnNullOnNew(): void
	{
		$table  = ORM::getDatabase(self::TEST_DB_NAMESPACE)->getTableOrFail('clients');
		$entity = ORM::entity($table);

		self::assertNull($entity->id, 'Auto-increment id must be null for a new entity');
	}

	/**
	 * Setting a column via magic __set and reading it back with __get returns the set value.
	 */
	public function testMagicSetAndGet(): void
	{
		$table  = ORM::getDatabase(self::TEST_DB_NAMESPACE)->getTableOrFail('clients');
		$entity = ORM::entity($table);

		$entity->first_name = 'Alice';
		$entity->last_name  = 'Smith';
		$entity->given_name = 'ASmith';
		$entity->gender     = 'female';

		self::assertSame('Alice', (string) $entity->first_name);
		self::assertSame('Smith', (string) $entity->last_name);
		self::assertSame('ASmith', (string) $entity->given_name);
		self::assertSame('female', (string) $entity->gender);
	}

	/**
	 * Full column names (with prefix) can also be used with magic __get/__set.
	 */
	public function testFullColumnNameAccessors(): void
	{
		$table  = ORM::getDatabase(self::TEST_DB_NAMESPACE)->getTableOrFail('clients');
		$entity = ORM::entity($table);

		$entity->client_first_name = 'Bob';

		self::assertSame('Bob', (string) $entity->client_first_name);
		// Short name alias must also reflect the change
		self::assertSame('Bob', (string) $entity->first_name);
	}

	/**
	 * __isset() returns true for known columns, false for unknown ones.
	 */
	public function testIssetOnColumns(): void
	{
		$table  = ORM::getDatabase(self::TEST_DB_NAMESPACE)->getTableOrFail('clients');
		$entity = ORM::entity($table);

		self::assertTrue(isset($entity->first_name), 'Known column must pass isset');
		self::assertTrue(isset($entity->client_id), 'Full column name must pass isset');
		self::assertFalse(isset($entity->non_existent), 'Unknown column must fail isset');
	}

	/**
	 * In strict mode, accessing an unknown property throws an ORMRuntimeException.
	 */
	public function testStrictModeThrowsOnUnknownColumn(): void
	{
		$table  = ORM::getDatabase(self::TEST_DB_NAMESPACE)->getTableOrFail('clients');
		$entity = ORM::entity($table, true, true); // strict = true

		$this->expectException(ORMRuntimeException::class);

		/** @noinspection PhpUndefinedFieldInspection */
		$_ = $entity->totally_unknown_column;
	}

	/**
	 * In non-strict mode, accessing an unknown property returns null silently.
	 */
	public function testNonStrictModeReturnsNullOnUnknownColumn(): void
	{
		$table  = ORM::getDatabase(self::TEST_DB_NAMESPACE)->getTableOrFail('clients');
		$entity = ORM::entity($table, true, false); // strict = false

		/** @noinspection PhpUndefinedFieldInspection */
		$value = $entity->totally_unknown_column;
		self::assertNull($value);
	}

	/**
	 * hydrate() fills entity from a row array (keyed by full column names).
	 */
	public function testHydrateFromRow(): void
	{
		$table  = ORM::getDatabase(self::TEST_DB_NAMESPACE)->getTableOrFail('clients');
		$entity = ORM::entity($table);

		$entity->hydrate([
			'client_first_name' => 'Carol',
			'client_last_name'  => 'White',
			'client_given_name' => 'CWhite',
			'client_gender'     => 'female',
		]);

		self::assertSame('Carol', (string) $entity->first_name);
		self::assertSame('White', (string) $entity->last_name);
		self::assertSame('female', (string) $entity->gender);
	}

	/**
	 * A non-new entity (simulated with is_new=false) reports isSaved() === true.
	 */
	public function testNonNewEntityIsSaved(): void
	{
		$table  = ORM::getDatabase(self::TEST_DB_NAMESPACE)->getTableOrFail('clients');
		$entity = ORM::entity($table, false); // is_new = false

		self::assertFalse($entity->isNew());
		self::assertTrue($entity->isSaved());
	}

	/**
	 * toArray() returns an array keyed by full column names (including prefix).
	 */
	public function testToArrayReturnsFullColumnNames(): void
	{
		$table  = ORM::getDatabase(self::TEST_DB_NAMESPACE)->getTableOrFail('clients');
		$entity = ORM::entity($table);

		$entity->hydrate([
			'client_first_name' => 'Dave',
			'client_last_name'  => 'Brown',
			'client_given_name' => 'DBrown',
			'client_gender'     => 'male',
		]);

		$arr = $entity->toArray();

		// toArray() / toRow() keys are always FULL column names (with prefix)
		self::assertArrayHasKey('client_first_name', $arr);
		self::assertArrayHasKey('client_last_name', $arr);
		self::assertArrayHasKey('client_gender', $arr);
		self::assertArrayHasKey('client_valid', $arr);
		self::assertSame('Dave', (string) ($arr['client_first_name'] ?? null));
	}

	/**
	 * Columns with defaults return their default values on a new entity before being set.
	 */
	public function testDefaultValues(): void
	{
		$table  = ORM::getDatabase(self::TEST_DB_NAMESPACE)->getTableOrFail('clients');
		$entity = ORM::entity($table);

		// `valid` defaults to true
		self::assertTrue((bool) $entity->valid, 'valid column must default to true');
	}

	/**
	 * A currency entity (PK is a string, no auto-increment) can be constructed and populated.
	 */
	public function testCurrencyEntityFromScratch(): void
	{
		$table  = ORM::getDatabase(self::TEST_DB_NAMESPACE)->getTableOrFail('currencies');
		$entity = ORM::entity($table);

		$entity->code   = 'USD';
		$entity->name   = 'US Dollar';
		$entity->symbol = '$';

		self::assertSame('USD', (string) $entity->code);
		self::assertSame('US Dollar', (string) $entity->name);
		self::assertSame('$', (string) $entity->symbol);
	}
}
