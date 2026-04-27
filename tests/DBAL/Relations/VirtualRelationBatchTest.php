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

namespace Gobl\Tests\DBAL\Relations;

use Gobl\DBAL\Drivers\MySQL\MySQL;
use Gobl\DBAL\Relations\Interfaces\RelationControllerInterface;
use Gobl\ORM\Generators\CSGeneratorORM;
use Gobl\ORM\ORM;
use Gobl\ORM\ORMEntityRelationController;
use Gobl\ORM\ORMOptions;
use Gobl\Tests\BaseTestCase;
use Throwable;

/**
 * Class VirtualRelationBatchTest.
 *
 * Tests for Feature 6: adding getBatch() to {@see RelationControllerInterface}
 * and the concrete implementation in {@see ORMEntityRelationController}.
 *
 * These tests do NOT require a live DB connection; they verify:
 *   - ORMEntityRelationController implements RelationControllerInterface
 *   - getBatch() exists on both the interface and the concrete class
 *   - getBatch([]) returns an empty array without touching the DB
 *
 * @covers \Gobl\ORM\ORMEntityRelationController::getBatch
 *
 * @internal
 */
final class VirtualRelationBatchTest extends BaseTestCase
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
			self::markTestSkipped('Virtual relation batch test setup failed.');
		}
	}

	// ---------------------------------------------------------------------------
	// Interface contract
	// ---------------------------------------------------------------------------

	/**
	 * ORMEntityRelationController must implement RelationControllerInterface.
	 */
	public function testORMEntityRelationControllerImplementsInterface(): void
	{
		$db       = ORM::getDatabase(self::TEST_DB_NAMESPACE);
		$accounts = $db->getTableOrFail('accounts');
		$relation = $accounts->getRelation('client');

		$ctrl = new ORMEntityRelationController($relation);

		self::assertInstanceOf(
			RelationControllerInterface::class,
			$ctrl,
			'ORMEntityRelationController must implement RelationControllerInterface'
		);
	}

	/**
	 * getBatch() must be declared on RelationControllerInterface.
	 */
	public function testGetBatchExistsOnInterface(): void
	{
		self::assertTrue(
			\method_exists(RelationControllerInterface::class, 'getBatch'),
			'RelationControllerInterface must declare getBatch()'
		);
	}

	/**
	 * ORMEntityRelationController::getBatch() must exist as a public method.
	 */
	public function testGetBatchExistsOnConcreteClass(): void
	{
		$db       = ORM::getDatabase(self::TEST_DB_NAMESPACE);
		$accounts = $db->getTableOrFail('accounts');
		$relation = $accounts->getRelation('client');
		$ctrl     = new ORMEntityRelationController($relation);

		self::assertTrue(
			\method_exists($ctrl, 'getBatch'),
			'ORMEntityRelationController must have a public getBatch() method'
		);
	}

	// ---------------------------------------------------------------------------
	// Empty-host-list early return
	// ---------------------------------------------------------------------------

	/**
	 * getBatch([]) must return an empty array without touching the DB.
	 *
	 * The delegate (getAllRelativesBatch) short-circuits on empty host list before
	 * calling runInTransaction, so no live DB is needed.
	 */
	public function testGetBatchReturnsEmptyForEmptyHostList(): void
	{
		$db       = ORM::getDatabase(self::TEST_DB_NAMESPACE);
		$accounts = $db->getTableOrFail('accounts');
		$relation = $accounts->getRelation('client');
		$ctrl     = new ORMEntityRelationController($relation);

		$options = new ORMOptions();
		$result  = $ctrl->getBatch([], $options);

		self::assertSame(
			[],
			$result,
			'getBatch([]) must return [] without requiring a DB connection'
		);
	}

	/**
	 * getBatch() on a one-to-many relation (clients -> accounts) must also
	 * short-circuit on empty host list.
	 */
	public function testGetBatchForOneToManyReturnsEmptyForEmptyHostList(): void
	{
		$db      = ORM::getDatabase(self::TEST_DB_NAMESPACE);
		$clients = $db->getTableOrFail('clients');
		$rel     = $clients->getRelation('accounts');
		$ctrl    = new ORMEntityRelationController($rel);

		$options = new ORMOptions();
		$result  = $ctrl->getBatch([], $options);

		self::assertSame(
			[],
			$result,
			'getBatch([]) for one-to-many must return []'
		);
	}
}
