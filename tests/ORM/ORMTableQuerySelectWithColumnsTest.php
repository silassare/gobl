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

use Gobl\DBAL\Builders\TableBuilder;
use Gobl\DBAL\Drivers\MySQL\MySQL;
use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\ORM\Generators\CSGeneratorORM;
use Gobl\ORM\ORM;
use Gobl\ORM\ORMTableQuery;
use Gobl\Tests\BaseTestCase;
use Throwable;

/**
 * Class ORMTableQuerySelectWithColumnsTest.
 *
 * Tests for the public {@see ORMTableQuery::selectWithColumns()} method.
 *
 * Only validation and SQL-structure tests are covered here; live-DB tests
 * (which actually iterate rows) live under tests/Integration/.
 *
 * @covers \Gobl\ORM\ORMTableQuery::selectWithColumns
 *
 * @internal
 */
final class ORMTableQuerySelectWithColumnsTest extends BaseTestCase
{
	/** @var bool Whether ORM setup completed successfully */
	private static bool $setupOk = false;

	// -------------------------------------------------------------------------
	// PHPUnit lifecycle
	// -------------------------------------------------------------------------

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		try {
			try {
				ORM::getDatabase(self::TEST_DB_NAMESPACE);
				ORM::undeclareNamespace(self::TEST_DB_NAMESPACE);
			} catch (Throwable) {
				// not declared yet - expected
			}

			$ormOutDir = GOBL_TEST_ORM_OUTPUT;

			if (!\is_dir($ormOutDir . \DIRECTORY_SEPARATOR . 'Base')) {
				\mkdir($ormOutDir . \DIRECTORY_SEPARATOR . 'Base', 0o755, true);
			}

			$db = self::getNewDbInstance(MySQL::NAME);
			$ns = $db->ns(self::TEST_DB_NAMESPACE);

			// Standard tables used by other tests.
			$ns->schema(self::getTablesDefinitions());

			// Extra table with a private column used by private-column projection tests.
			$ns->table('widgets', static function (TableBuilder $t) {
				$t->id();
				$t->string('label');
				$t->string('internal_token');
				$t->useColumn('internal_token')->setPrivate();
			});

			$ns->enableORM($ormOutDir);

			(new CSGeneratorORM($db))->generate($db->getTables(), $ormOutDir);

			$db->lock();

			self::$setupOk = true;
		} catch (Throwable) {
			self::$setupOk = false;
		}
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
			self::markTestSkipped('ORMTableQuerySelectWithColumnsTest setup failed.');
		}
	}

	// -------------------------------------------------------------------------
	// Validation: unknown column
	// -------------------------------------------------------------------------

	/**
	 * selectWithColumns() must throw DBALRuntimeException for unknown column names.
	 */
	public function testThrowsForUnknownColumn(): void
	{
		$table = ORM::getDatabase(self::TEST_DB_NAMESPACE)->getTableOrFail('clients');
		$qb    = ORM::query($table);

		$this->expectException(DBALRuntimeException::class);
		$qb->selectWithColumns(['id', 'non_existent_column']);
	}

	// -------------------------------------------------------------------------
	// Return value: QBSelect
	// -------------------------------------------------------------------------

	/**
	 * selectWithColumns() must return a QBSelect instance.
	 */
	public function testReturnsQBSelect(): void
	{
		$table = ORM::getDatabase(self::TEST_DB_NAMESPACE)->getTableOrFail('clients');
		$qb    = ORM::query($table);
		$sel   = $qb->selectWithColumns(['id', 'first_name']);

		self::assertInstanceOf(QBSelect::class, $sel);
	}

	// -------------------------------------------------------------------------
	// SQL structure: column restriction
	// -------------------------------------------------------------------------

	/**
	 * selectWithColumns() must restrict the SELECT to the projected full column names.
	 */
	public function testSQLContainsOnlyProjectedColumns(): void
	{
		$db    = ORM::getDatabase(self::TEST_DB_NAMESPACE);
		$table = $db->getTableOrFail('clients');
		$qb    = ORM::query($table);
		$sel   = $qb->selectWithColumns(['id', 'first_name']);

		$sql = $sel->getSqlQuery();

		// Full column names for the 'clients' table with prefix 'client'.
		self::assertStringContainsString('client_id', $sql);
		self::assertStringContainsString('client_first_name', $sql);

		// Columns NOT in the projection must not appear in the SELECT list.
		self::assertStringNotContainsString('client_last_name', $sql);
		self::assertStringNotContainsString('client_given_name', $sql);
	}

	/**
	 * selectWithColumns() must accept full column names in addition to short names.
	 */
	public function testAcceptsFullColumnName(): void
	{
		$db    = ORM::getDatabase(self::TEST_DB_NAMESPACE);
		$table = $db->getTableOrFail('clients');
		$qb    = ORM::query($table);

		// 'client_id' is the full name (prefix 'client' + short 'id'); 'first_name' is short.
		$sel = $qb->selectWithColumns(['client_id', 'first_name']);
		$sql = $sel->getSqlQuery();

		self::assertStringContainsString('client_id', $sql);
		self::assertStringContainsString('client_first_name', $sql);
	}

	// -------------------------------------------------------------------------
	// Private column handling
	// -------------------------------------------------------------------------

	/**
	 * selectWithColumns() must silently exclude private columns from the projection.
	 */
	public function testSilentlyExcludesPrivateColumns(): void
	{
		$db    = ORM::getDatabase(self::TEST_DB_NAMESPACE);
		$table = $db->getTableOrFail('widgets');
		$qb    = ORM::query($table);

		// 'internal_token' is private; it must be silently excluded.
		$sel = $qb->selectWithColumns(['id', 'label', 'internal_token']);
		$sql = $sel->getSqlQuery();

		// 'widgets' table has no column prefix so names are bare.
		self::assertStringContainsString('label', $sql);
		// Private column must not appear in the projected SELECT.
		self::assertStringNotContainsString('internal_token', $sql);
	}

	/**
	 * selectWithColumns() must fall back to a full SELECT when every requested column
	 * is private (no usable columns remain after filtering).
	 */
	public function testFallsBackToFullSelectWhenAllColumnsArePrivate(): void
	{
		$db    = ORM::getDatabase(self::TEST_DB_NAMESPACE);
		$table = $db->getTableOrFail('widgets');
		$qb    = ORM::query($table);

		// Only the private column is passed; should fall back to a full SELECT.
		$sel     = $qb->selectWithColumns(['internal_token']);
		$full    = $qb->select();
		$sqlSel  = $sel->getSqlQuery();
		$sqlFull = $full->getSqlQuery();

		// Both must produce the same SQL: fallback = full select().
		self::assertSame($sqlFull, $sqlSel);
		// Private column must never appear.
		self::assertStringNotContainsString('internal_token', $sqlSel);
	}
}
