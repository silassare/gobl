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

namespace Gobl\Tests\ORM;

use Gobl\DBAL\Drivers\MySQL\MySQL;
use Gobl\ORM\Generators\CSGeneratorORM;
use Gobl\ORM\ORM;
use Gobl\ORM\ORMController;
use Gobl\Tests\BaseTestCase;
use Throwable;

/**
 * Class ORMControllerBatchTest.
 *
 * Unit tests for the batch and count methods added to {@see ORMController}
 * as part of Feature 1 and Feature 2.
 *
 * These tests ONLY cover code paths that do not require a live DB connection
 * (principally the "empty host list" early-return and method-existence guards).
 * Full e2e behaviour (actual SQL execution, grouping, fallback) is tested via
 * integration tests under tests/Integration/.
 *
 * @covers \Gobl\ORM\ORMController::countRelatives
 * @covers \Gobl\ORM\ORMController::countRelativesBatch
 * @covers \Gobl\ORM\ORMController::getAllRelativesBatch
 * @covers \Gobl\ORM\ORMController::getRelativeBatch
 *
 * @internal
 */
final class ORMControllerBatchTest extends BaseTestCase
{
	/** @var bool Whether ORM setup completed successfully */
	private static bool $setupOk = false;

	// ---------------------------------------------------------------------------
	// PHPUnit lifecycle
	// ---------------------------------------------------------------------------

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		try {
			ORM::getDatabase(self::TEST_DB_NAMESPACE);
			ORM::undeclareNamespace(self::TEST_DB_NAMESPACE);
		} catch (Throwable) {
			// not declared yet, expected
		}

		$ormOutDir = GOBL_TEST_ORM_OUTPUT;

		if (!\is_dir($ormOutDir . \DIRECTORY_SEPARATOR . 'Base')) {
			\mkdir($ormOutDir . \DIRECTORY_SEPARATOR . 'Base', 0o755, true);
		}

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
			self::markTestSkipped('ORM controller batch test setup failed.');
		}
	}

	// ---------------------------------------------------------------------------
	// Empty-host-list early return
	// ---------------------------------------------------------------------------

	/**
	 * getRelativeBatch() must return an empty array immediately when given no hosts.
	 */
	public function testGetRelativeBatchReturnsEmptyForEmptyHostList(): void
	{
		$db       = ORM::getDatabase(self::TEST_DB_NAMESPACE);
		$accounts = $db->getTableOrFail('accounts');
		$ctrl     = ORM::ctrl($accounts);
		$relation = $accounts->getRelation('client');

		$result = $ctrl->getRelativeBatch([], $relation);

		self::assertSame([], $result, 'getRelativeBatch([]) must return []');
	}

	/**
	 * getAllRelativesBatch() must return an empty array immediately when given no hosts.
	 */
	public function testGetAllRelativesBatchReturnsEmptyForEmptyHostList(): void
	{
		$db      = ORM::getDatabase(self::TEST_DB_NAMESPACE);
		$clients = $db->getTableOrFail('clients');
		$ctrl    = ORM::ctrl($clients);
		$rel     = $clients->getRelation('accounts');

		$result = $ctrl->getAllRelativesBatch([], $rel);

		self::assertSame([], $result, 'getAllRelativesBatch([]) must return []');
	}

	/**
	 * countRelativesBatch() must return an empty array immediately when given no hosts.
	 */
	public function testCountRelativesBatchReturnsEmptyForEmptyHostList(): void
	{
		$db      = ORM::getDatabase(self::TEST_DB_NAMESPACE);
		$clients = $db->getTableOrFail('clients');
		$ctrl    = ORM::ctrl($clients);
		$rel     = $clients->getRelation('accounts');

		$result = $ctrl->countRelativesBatch([], $rel);

		self::assertSame([], $result, 'countRelativesBatch([]) must return []');
	}

	// ---------------------------------------------------------------------------
	// Method existence guard
	// ---------------------------------------------------------------------------

	/**
	 * Verify getRelativeBatch, getAllRelativesBatch, countRelatives, countRelativesBatch
	 * exist as public methods on the generated controller.
	 */
	public function testBatchMethodsExistOnController(): void
	{
		$db      = ORM::getDatabase(self::TEST_DB_NAMESPACE);
		$clients = $db->getTableOrFail('clients');
		$ctrl    = ORM::ctrl($clients);

		self::assertTrue(
			\method_exists($ctrl, 'getRelativeBatch'),
			'getRelativeBatch() must exist on ORMController'
		);
		self::assertTrue(
			\method_exists($ctrl, 'getAllRelativesBatch'),
			'getAllRelativesBatch() must exist on ORMController'
		);
		self::assertTrue(
			\method_exists($ctrl, 'countRelatives'),
			'countRelatives() must exist on ORMController'
		);
		self::assertTrue(
			\method_exists($ctrl, 'countRelativesBatch'),
			'countRelativesBatch() must exist on ORMController'
		);
	}
}
