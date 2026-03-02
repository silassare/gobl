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

namespace Gobl\Tests\ORM\LiveDB;

use Gobl\CRUD\Events\BeforeDeleteAll;
use Gobl\CRUD\Events\BeforePKColumnWrite;
use Gobl\CRUD\Events\BeforeUpdateAll;
use Gobl\DBAL\Drivers\MySQL\MySQL;
use Gobl\DBAL\Drivers\PostgreSQL\PostgreSQL;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\ORM\Generators\CSGeneratorORM;
use Gobl\ORM\ORM;
use Gobl\Tests\BaseTestCase;
use PHPUtils\Events\Event;
use PHPUtils\Events\EventManager;
use Throwable;

/**
 * Class ORMLiveTestCase.
 *
 * Abstract base for ORM integration tests that run against a real database.
 * Each concrete subclass targets one driver (MySQL, PostgreSQL, SQLite).
 *
 * Setup sequence per driver:
 *   1. Build a live DB connection from env vars
 *   2. Declare the ORM namespace via enableORM()
 *   3. Generate PHP entity/controller/query classes into tests/Db/
 *   4. Lock the schema and execute the full DDL (buildDatabase)
 *
 * Tear-down sequence:
 *   1. Drop all test tables
 *   2. Undeclare the ORM namespace so the next driver can re-declare it
 *
 * @internal
 *
 * @coversNothing
 */
abstract class ORMLiveTestCase extends BaseTestCase
{
	/** @var null|RDBMSInterface Shared live-DB connection for all tests in the class */
	protected static ?RDBMSInterface $db = null;

	/** @var bool Indicates that setUpBeforeClass failed (e.g. missing env credentials) */
	protected static bool $setupFailed = false;

	// -------------------------------------------------------------------------
	// PHPUnit lifecycle
	// -------------------------------------------------------------------------

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass(); // loads .env.test

		static::$setupFailed = false;

		// Guard: if a previous test class forgot to undeclare, clean up now
		try {
			ORM::getDatabase(self::TEST_DB_NAMESPACE);
			ORM::undeclareNamespace(self::TEST_DB_NAMESPACE);
		} catch (Throwable) {
			// namespace was not declared that is the expected state
		}

		try {
			$db = static::getNewDbInstance(static::getDriverName());
		} catch (Throwable $t) {
			static::$setupFailed = true;
			gobl_test_log(\sprintf('Error building live DB for %s: %s', static::getDriverName(), $t->getMessage()));

			return;
		}

		if (null === $db) {
			static::$setupFailed = true;
			gobl_test_log(\sprintf('Live DB not configured for %s (check env vars).', static::getDriverName()));

			return;
		}

		$ormOutDir = GOBL_TEST_ORM_OUTPUT;

		try {
			// Ensure the output directory (and its Base/ sub-directory) exist
			if (!\is_dir($ormOutDir . \DIRECTORY_SEPARATOR . 'Base')) {
				\mkdir($ormOutDir . \DIRECTORY_SEPARATOR . 'Base', 0o755, true);
			}

			// Declare the ORM namespace and generate entity class files
			$db->ns(self::TEST_DB_NAMESPACE)
				->schema(self::getTablesDefinitions())
				->enableORM($ormOutDir);

			(new CSGeneratorORM($db))->generate($db->getTables(), $ormOutDir);

			// Lock the schema and create all tables in the live database
			$db->lock();
			$db->executeMulti($db->getGenerator()->buildDatabase());

			static::$db = $db;
		} catch (Throwable $t) {
			static::$setupFailed = true;
			gobl_test_log(\sprintf('Error setting up live DB for %s: %s', static::getDriverName(), $t->getMessage()));
		}
	}

	public static function tearDownAfterClass(): void
	{
		if (null !== static::$db) {
			try {
				static::$db->executeMulti(static::buildDropAllSql(static::$db));
			} catch (Throwable) {
				// best-effort: ignore drop errors
			}

			try {
				ORM::undeclareNamespace(self::TEST_DB_NAMESPACE);
			} catch (Throwable) {
				// already undeclared or was never fully declared
			}

			static::$db = null;
		}

		parent::tearDownAfterClass();
	}

	protected function setUp(): void
	{
		parent::setUp();

		if (static::$setupFailed || null === static::$db) {
			self::markTestSkipped(
				\sprintf('%s live database is not available (check env vars).', static::getDriverName())
			);
		}
	}

	// -------------------------------------------------------------------------
	// Tests
	// -------------------------------------------------------------------------

	/**
	 * addItem() persists a new entity and returns it with its generated PK.
	 */
	public function testAddItem(): void
	{
		$ctrl   = ORM::ctrl(static::$db->getTableOrFail('clients'));
		$entity = $ctrl->addItem([
			'client_first_name' => 'Alice',
			'client_last_name'  => 'Smith',
			'client_given_name' => 'ASmith',
			'client_gender'     => 'female',
		]);

		self::assertNotNull($entity->id, 'Auto-incremented id must be set after insert');
		self::assertSame('Alice', (string) $entity->first_name);
		self::assertSame('Smith', (string) $entity->last_name);
		self::assertFalse($entity->isNew(), 'Entity must not be new after persistence');
		self::assertTrue($entity->isSaved(), 'Entity must be saved after persistence');
	}

	/**
	 * getItem() retrieves the entity inserted by testAddItem().
	 */
	public function testGetItem(): void
	{
		$ctrl = ORM::ctrl(static::$db->getTableOrFail('clients'));

		$created = $ctrl->addItem([
			'client_first_name' => 'Bob',
			'client_last_name'  => 'Jones',
			'client_given_name' => 'BJones',
			'client_gender'     => 'male',
		]);

		$fetched = $ctrl->getItem(['client_id' => $created->id]);

		self::assertNotNull($fetched, 'getItem must return the inserted entity');
		self::assertSame((string) $created->id, (string) $fetched->id);
		self::assertSame('Bob', (string) $fetched->first_name);
		self::assertSame('Jones', (string) $fetched->last_name);
	}

	/**
	 * updateOneItem() modifies a field and the returned entity reflects the change.
	 */
	public function testUpdateOneItem(): void
	{
		$ctrl = ORM::ctrl(static::$db->getTableOrFail('clients'));

		$created = $ctrl->addItem([
			'client_first_name' => 'Carol',
			'client_last_name'  => 'White',
			'client_given_name' => 'CWhite',
			'client_gender'     => 'female',
		]);

		$updated = $ctrl->updateOneItem(
			['client_id' => $created->id],
			['client_last_name' => 'Black']
		);

		self::assertNotNull($updated, 'updateOneItem must return the updated entity');
		self::assertSame('Carol', (string) $updated->first_name);
		self::assertSame('Black', (string) $updated->last_name);
	}

	/**
	 * deleteOneItem() removes the entity and subsequent getItem() returns null.
	 */
	public function testDeleteOneItem(): void
	{
		$ctrl = ORM::ctrl(static::$db->getTableOrFail('clients'));

		$created = $ctrl->addItem([
			'client_first_name' => 'Dave',
			'client_last_name'  => 'Brown',
			'client_given_name' => 'DBrown',
			'client_gender'     => 'male',
		]);

		$deleted = $ctrl->deleteOneItem(['client_id' => $created->id]);

		self::assertNotNull($deleted, 'deleteOneItem must return the deleted entity');

		// Soft-deletable entities should not appear in default queries
		$re_fetched = $ctrl->getItem(['client_id' => $created->id]);
		self::assertNull($re_fetched, 'Deleted entity must not be returned by getItem');
	}

	/**
	 * deleteAllItems() without LIMIT removes all matching rows.
	 *
	 * This exercises the no-LIMIT DELETE path where the ORM WHERE clause uses
	 * alias-prefixed column names (e.g. _a_.client_last_name).
	 */
	public function testDeleteAllItemsNoLimit(): void
	{
		$ctrl    = ORM::ctrl(static::$db->getTableOrFail('clients'));
		$channel = static::$db->getTableOrFail('clients')->getFullName();
		$marker  = 'DeleteAll_' . \uniqid();

		$detach = EventManager::listen(
			BeforeDeleteAll::class,
			static fn() => true,
			Event::RUN_DEFAULT,
			$channel
		);

		try {
			$ctrl->addItem(['client_first_name' => 'A', 'client_last_name' => $marker, 'client_given_name' => 'DA', 'client_gender' => 'unknown']);
			$ctrl->addItem(['client_first_name' => 'B', 'client_last_name' => $marker, 'client_given_name' => 'DB', 'client_gender' => 'unknown']);
			$ctrl->addItem(['client_first_name' => 'C', 'client_last_name' => $marker, 'client_given_name' => 'DC', 'client_gender' => 'unknown']);

			$count = $ctrl->deleteAllItems(['client_last_name' => $marker]);

			self::assertSame(3, $count, 'deleteAllItems must remove exactly 3 rows');
			self::assertEmpty($ctrl->getAllItems(['client_last_name' => $marker]), 'No matching rows must remain');
		} finally {
			$detach();
		}
	}

	/**
	 * updateAllItems() without LIMIT updates all matching rows.
	 *
	 * This exercises the no-LIMIT UPDATE path where the ORM WHERE clause uses
	 * alias-prefixed column names (e.g. _m_.client_last_name).
	 */
	public function testUpdateAllItemsNoLimit(): void
	{
		$ctrl    = ORM::ctrl(static::$db->getTableOrFail('clients'));
		$channel = static::$db->getTableOrFail('clients')->getFullName();
		$marker  = 'UpdateAll_' . \uniqid();

		$detach = EventManager::listen(
			BeforeUpdateAll::class,
			static fn() => true,
			Event::RUN_DEFAULT,
			$channel
		);

		try {
			$ctrl->addItem(['client_first_name' => 'X', 'client_last_name' => $marker, 'client_given_name' => 'UX', 'client_gender' => 'unknown']);
			$ctrl->addItem(['client_first_name' => 'Y', 'client_last_name' => $marker, 'client_given_name' => 'UY', 'client_gender' => 'unknown']);

			$count = $ctrl->updateAllItems(['client_last_name' => $marker], ['client_last_name' => $marker . '_updated']);

			self::assertSame(2, $count, 'updateAllItems must update exactly 2 rows');
			self::assertEmpty($ctrl->getAllItems(['client_last_name' => $marker]), 'Old marker must match nothing');
			self::assertCount(2, $ctrl->getAllItems(['client_last_name' => $marker . '_updated']), 'Updated rows must be found');
		} finally {
			$detach();
		}
	}

	/**
	 * getAllItems() returns the correct count and types.
	 */
	public function testGetAllItems(): void
	{
		$ctrl = ORM::ctrl(static::$db->getTableOrFail('clients'));

		$suffixes = ['One', 'Two', 'Three'];
		$ids      = [];

		foreach ($suffixes as $suffix) {
			$entity = $ctrl->addItem([
				'client_first_name' => 'Test' . $suffix,
				'client_last_name'  => 'GetAll',
				'client_given_name' => 'TGA' . $suffix,
				'client_gender'     => 'unknown',
			]);
			$ids[] = (string) $entity->id;
		}

		$all = $ctrl->getAllItems(['client_last_name' => 'GetAll']);

		self::assertGreaterThanOrEqual(\count($suffixes), \count($all));

		foreach ($all as $item) {
			self::assertSame('GetAll', (string) $item->last_name);
		}
	}

	/**
	 * ORM::query() can be used with filters in both old-style and new-style format.
	 */
	public function testTableQueryFilters(): void
	{
		$ctrl  = ORM::ctrl(static::$db->getTableOrFail('clients'));
		$table = static::$db->getTableOrFail('clients');

		$created = $ctrl->addItem([
			'client_first_name' => 'QueryTest',
			'client_last_name'  => 'FilterUser',
			'client_given_name' => 'QTFU',
			'client_gender'     => 'unknown',
		]);

		// Old-style filter (associative)
		$tq1     = ORM::query($table, ['client_id' => $created->id]);
		$entity1 = $tq1->find(1)->fetchClass();
		self::assertNotNull($entity1);
		self::assertSame((string) $created->id, (string) $entity1->id);

		// New-style filter (indexed)
		$tq2     = ORM::query($table, ['client_id', 'eq', $created->id]);
		$entity2 = $tq2->find(1)->fetchClass();
		self::assertNotNull($entity2);
		self::assertSame((string) $created->id, (string) $entity2->id);
	}

	/**
	 * ORMResults can be iterated and provides fetchClass(), totalCount(), etc.
	 */
	public function testORMResults(): void
	{
		$ctrl  = ORM::ctrl(static::$db->getTableOrFail('clients'));
		$table = static::$db->getTableOrFail('clients');

		// Insert a few entities with a unique last name for this test
		$last  = 'ORMResultsUser_' . \uniqid();
		$count = 3;

		for ($i = 0; $i < $count; ++$i) {
			$ctrl->addItem([
				'client_first_name' => 'Res' . $i,
				'client_last_name'  => $last,
				'client_given_name' => 'R' . $i,
				'client_gender'     => 'unknown',
			]);
		}

		$results = ORM::query($table, ['client_last_name' => $last])->find($count);

		self::assertSame($count, $results->totalCount());

		$collected = [];
		foreach ($results as $entity) {
			$collected[] = $entity;
		}

		self::assertCount($count, $collected);
		foreach ($collected as $entity) {
			self::assertSame($last, (string) $entity->last_name);
		}
	}

	/**
	 * ORMEntity: entity setters validate values and getters return correct types.
	 */
	public function testORMEntityGettersAndSetters(): void
	{
		$ctrl  = ORM::ctrl(static::$db->getTableOrFail('clients'));
		$table = static::$db->getTableOrFail('clients');

		$entity = $ctrl->addItem([
			'client_first_name' => 'EntityTest',
			'client_last_name'  => 'Setters',
			'client_given_name' => 'ETS',
			'client_gender'     => 'male',
		]);

		// Entity should have correct initial values
		self::assertSame('EntityTest', (string) $entity->first_name);
		self::assertSame('male', (string) $entity->gender);
		self::assertTrue((bool) $entity->valid, 'Default valid flag should be true');
		self::assertFalse($entity->isNew(), 'Persisted entity is not new');
		self::assertTrue($entity->isSaved(), 'Persisted entity is saved');

		// toArray() / toRow() keys are full column names (with prefix)
		$arr = $entity->toArray();
		self::assertArrayHasKey('client_id', $arr);
		self::assertArrayHasKey('client_first_name', $arr);
		self::assertArrayHasKey('client_last_name', $arr);
		self::assertArrayHasKey('client_gender', $arr);
		self::assertArrayHasKey('client_valid', $arr);

		// toIdentityFilters() returns a valid PK filter
		$filters = $entity->toIdentityFilters();
		self::assertNotEmpty($filters);
	}

	/**
	 * Currency table (no FK): full CRUD round-trip.
	 */
	public function testCurrencyCRUD(): void
	{
		$ctrl    = ORM::ctrl(static::$db->getTableOrFail('currencies'));
		$code    = 'TST';
		$channel = static::$db->getTableOrFail('currencies')->getFullName();

		// currencies.ccy_code is a non-auto-increment string PK.
		// CRUD requires explicit authorization to write PK columns.
		// Register a scoped listener and detach it when the test ends.
		$detach = EventManager::listen(
			BeforePKColumnWrite::class,
			static fn() => true,
			Event::RUN_DEFAULT,
			$channel
		);

		try {
			// Create
			$created = $ctrl->addItem([
				'ccy_code'   => $code,
				'ccy_name'   => 'Test Currency',
				'ccy_symbol' => 'T$',
			]);

			self::assertSame($code, (string) $created->code);
			self::assertSame('Test Currency', (string) $created->name);
			self::assertSame('T$', (string) $created->symbol);

			// Read
			$fetched = $ctrl->getItem(['ccy_code' => $code]);
			self::assertNotNull($fetched);
			self::assertSame($code, (string) $fetched->code);

			// Update
			$updated = $ctrl->updateOneItem(['ccy_code' => $code], ['ccy_name' => 'Updated Currency']);
			self::assertNotNull($updated);
			self::assertSame('Updated Currency', (string) $updated->name);

			// Delete
			$deleted = $ctrl->deleteOneItem(['ccy_code' => $code]);
			self::assertNotNull($deleted);

			$gone = $ctrl->getItem(['ccy_code' => $code]);
			self::assertNull($gone, 'Currency must be gone after delete');
		} finally {
			$detach();
		}
	}

	// -------------------------------------------------------------------------
	// Abstract interface implemented by concrete driver test classes
	// -------------------------------------------------------------------------

	/**
	 * Human-readable driver name used in skip/failure messages.
	 *
	 * @return string
	 */
	abstract protected static function getDriverName(): string;

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Builds a DROP TABLE SQL string for all test tables in reverse FK order.
	 * Syntax is adjusted per driver type.
	 *
	 * @param RDBMSInterface $db
	 *
	 * @return string
	 */
	protected static function buildDropAllSql(RDBMSInterface $db): string
	{
		$type = $db->getType();

		// Reverse FK order: dependent tables first (logical names).
		$logical = ['transactions', 'accounts', 'clients', 'currencies'];

		// Resolve to actual full table names (includes namespace prefix, e.g. "gObL_clients").
		$tables = \array_map(
			static fn(string $name) => $db->getTableOrFail($name)->getFullName(),
			$logical
		);

		$lines = [];

		if (MySQL::NAME === $type) {
			$lines[] = 'SET FOREIGN_KEY_CHECKS=0;';
			foreach ($tables as $t) {
				$lines[] = \sprintf('DROP TABLE IF EXISTS `%s`;', $t);
			}
			$lines[] = 'SET FOREIGN_KEY_CHECKS=1;';
		} elseif (PostgreSQL::NAME === $type) {
			foreach ($tables as $t) {
				$lines[] = \sprintf('DROP TABLE IF EXISTS "%s" CASCADE;', $t);
			}
		} else {
			// SQLite and others
			foreach ($tables as $t) {
				$lines[] = \sprintf('DROP TABLE IF EXISTS "%s";', $t);
			}
		}

		return \implode(\PHP_EOL, $lines);
	}
}
