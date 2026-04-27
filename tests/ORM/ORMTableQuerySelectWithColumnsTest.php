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
use Gobl\ORM\Exceptions\ORMRuntimeException;
use Gobl\ORM\Generators\CSGeneratorORM;
use Gobl\ORM\ORM;
use Gobl\ORM\ORMOptions;
use Gobl\ORM\ORMTableQuery;
use Gobl\Tests\BaseTestCase;
use Throwable;

/**
 * Class ORMTableQuerySelectWithColumnsTest.
 *
 * Tests for the public {@see ORMTableQuery::select()} method with column projections.
 *
 * Only validation and SQL-structure tests are covered here; live-DB tests
 * (which actually iterate rows) live under tests/Integration/.
 *
 * @internal
 *
 * @coversNothing
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

	/**
	 * must throw DBALRuntimeException for unknown column names.
	 */
	public function testThrowsForUnknownColumn(): void
	{
		$table = ORM::getDatabase(self::TEST_DB_NAMESPACE)->getTableOrFail('clients');
		$qb    = ORM::query($table);

		$this->expectException(DBALRuntimeException::class);
		$this->selectWithColumnProjections($qb, ['id', 'non_existent_column']);
	}

	/**
	 * must restrict the SELECT to the projected full column names.
	 */
	public function testSQLContainsOnlyProjectedColumns(): void
	{
		$db    = ORM::getDatabase(self::TEST_DB_NAMESPACE);
		$table = $db->getTableOrFail('clients');
		$qb    = ORM::query($table);
		$sel   = $this->selectWithColumnProjections($qb, ['id', 'first_name']);

		$sql = $sel->getSqlQuery();

		// Full column names for the 'clients' table with prefix 'client'.
		self::assertStringContainsString('client_id', $sql);
		self::assertStringContainsString('client_first_name', $sql);

		// Columns NOT in the projection must not appear in the SELECT list.
		self::assertStringNotContainsString('client_last_name', $sql);
		self::assertStringNotContainsString('client_given_name', $sql);
	}

	/**
	 * must accept full column names in addition to short names.
	 */
	public function testAcceptsFullColumnName(): void
	{
		$db    = ORM::getDatabase(self::TEST_DB_NAMESPACE);
		$table = $db->getTableOrFail('clients');
		$qb    = ORM::query($table);

		// 'client_id' is the full name (prefix 'client' + short 'id'); 'first_name' is short.
		$sel = $this->selectWithColumnProjections($qb, ['client_id', 'first_name']);
		$sql = $sel->getSqlQuery();

		self::assertStringContainsString('client_id', $sql);
		self::assertStringContainsString('client_first_name', $sql);
	}

	/**
	 * must throw ORMRuntimeException when an expected column is not allowed by restrict_to_columns.
	 */
	public function testThrowsWhenExpectedColumnNotInRestrictToColumns(): void
	{
		$db    = ORM::getDatabase(self::TEST_DB_NAMESPACE);
		$table = $db->getTableOrFail('clients');
		$qb    = ORM::query($table);

		// 'id' is in expected columns but not in restrict_to_columns.
		$this->expectException(ORMRuntimeException::class);
		$this->selectWithColumnProjections($qb, ['id', 'first_name'], ['first_name']);
	}

	/**
	 * must fall back to a full SELECT when no columns are projected.
	 */
	public function testFallsBackToFullSelectWhenNoColumns(): void
	{
		$db    = ORM::getDatabase(self::TEST_DB_NAMESPACE);
		$table = $db->getTableOrFail('widgets');
		$qb    = ORM::query($table);

		$sel    = $this->selectWithColumnProjections($qb, []);
		$sqlSel = $sel->getSqlQuery();

		// Fallback must select all non-private columns (alias.*).
		self::assertStringContainsString('.*', $sqlSel);
		// Private column must never appear.
		self::assertStringNotContainsString('internal_token', $sqlSel);
	}

	private static function selectWithColumnProjections(ORMTableQuery $qb, array $columns, array $restrict_to_columns = []): QBSelect
	{
		$options = new ORMOptions();

		$options->setExpectedColumns($columns);

		return $qb->select($options, $restrict_to_columns);
	}
}
