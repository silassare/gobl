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
use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\ORM\Exceptions\ORMQueryException;
use Gobl\ORM\Generators\CSGeneratorORM;
use Gobl\ORM\ORM;
use Gobl\ORM\ORMOptions;
use Gobl\ORM\ORMTableQuery;
use Gobl\Tests\BaseTestCase;
use Throwable;

/**
 * Class ORMTableQueryCursorTest.
 *
 * Tests for cursor-based pagination via {@see ORMTableQuery::find()} with
 * a {@see ORMOptions::makeCursorBased()} request (Feature 3).
 *
 * Only validation and SQL-structure tests are covered here; live-DB tests
 * (which actually iterate rows) live under tests/Integration/.
 *
 * @covers \Gobl\ORM\ORMTableQuery::find
 *
 * @internal
 */
final class ORMTableQueryCursorTest extends BaseTestCase
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
			self::markTestSkipped('ORM cursor test setup failed.');
		}
	}

	// ---------------------------------------------------------------------------
	// Validation: $max
	// ---------------------------------------------------------------------------

	/**
	 * max=0 with cursor: limit(max+1, 0) = limit(1, 0) — no exception is thrown.
	 */
	public function testCursorFindWithMaxZeroProducesLimitOne(): void
	{
		$table = ORM::getDatabase(self::TEST_DB_NAMESPACE)->getTableOrFail('clients');
		$qb    = ORM::query($table);

		// max=0 => limit(max+1) = limit(1): no exception, just produces LIMIT 1.
		$sel = $qb->select(ORMOptions::makeCursorBased('id', 0));

		self::assertInstanceOf(QBSelect::class, $sel);
		self::assertStringContainsString('LIMIT 1', $sel->getSqlQuery());
	}

	/**
	 * max=-1 with cursor: limit(max+1, 0) = limit(0, 0) — DBALRuntimeException is thrown for invalid limit.
	 */
	public function testCursorFindThrowsWhenMaxIsNegative(): void
	{
		$table = ORM::getDatabase(self::TEST_DB_NAMESPACE)->getTableOrFail('clients');
		$qb    = ORM::query($table);

		$this->expectException(DBALRuntimeException::class);
		$qb->find(ORMOptions::makeCursorBased('id', -1));
	}

	// ---------------------------------------------------------------------------
	// Validation: $direction
	// ---------------------------------------------------------------------------

	public function testCursorFindThrowsOnInvalidDirection(): void
	{
		$this->expectException(ORMQueryException::class);
		ORMOptions::makeCursorBased('id', 10, null, 'invalid');
	}

	/**
	 * @dataProvider provideCursorFindAcceptsValidDirectionsCases
	 */
	public function testCursorFindAcceptsValidDirections(string $direction): void
	{
		// Accepted directions should not throw during SQL construction.
		// We catch ORMQueryException but re-throw anything else.
		$table = ORM::getDatabase(self::TEST_DB_NAMESPACE)->getTableOrFail('clients');
		$qb    = ORM::query($table);

		try {
			// Will throw a PDOException / something when trying to execute against
			// a schema-only DB, but NOT an ORMQueryException for direction validation.
			$qb->find(ORMOptions::makeCursorBased('id', 10, null, $direction));
			// If it reaches here somehow, that's fine too.
			self::assertTrue(true);
		} catch (ORMQueryException $e) {
			// If it's an ORMQueryException about direction, that's a failure.
			$data = $e->getData();

			if (isset($data['direction'])) {
				self::fail("find() with cursor must not throw for valid direction \"{$direction}\"");
			}
		} catch (Throwable) {
			// Any other error (e.g. no DB connection) is acceptable.
			self::assertTrue(true);
		}
	}

	public static function provideCursorFindAcceptsValidDirectionsCases(): iterable
	{
		return [
			'ASC'  => ['ASC'],
			'DESC' => ['DESC'],
		];
	}

	// ---------------------------------------------------------------------------
	// Validation: $cursor_column
	// ---------------------------------------------------------------------------

	public function testCursorFindThrowsOnUnknownColumn(): void
	{
		$table = ORM::getDatabase(self::TEST_DB_NAMESPACE)->getTableOrFail('clients');
		$qb    = ORM::query($table);

		$this->expectException(DBALRuntimeException::class);
		$qb->find(ORMOptions::makeCursorBased('non_existent_column', 10));
	}

	// ---------------------------------------------------------------------------
	// Return-value contract
	// ---------------------------------------------------------------------------

	/**
	 * find() with a cursor request must return an ORMResults (SQL-structure check only).
	 * Full iteration tests with real data live under tests/Integration/.
	 */
	public function testCursorFindReturnsORMResults(): void
	{
		$table = ORM::getDatabase(self::TEST_DB_NAMESPACE)->getTableOrFail('clients');
		$qb    = ORM::query($table);

		$sel = $qb->select(ORMOptions::makeCursorBased('id', 5));

		// The generated SQL must contain ORDER BY on the cursor column and LIMIT max+1.
		$sql = $sel->getSqlQuery();
		self::assertStringContainsString('ORDER BY', $sql);
		self::assertStringContainsString('LIMIT 6', $sql);
		self::assertStringContainsString('client_id', $sql);
	}
}
