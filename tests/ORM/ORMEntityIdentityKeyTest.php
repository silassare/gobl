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
use Gobl\ORM\Exceptions\ORMException;
use Gobl\ORM\Generators\CSGeneratorORM;
use Gobl\ORM\ORM;
use Gobl\ORM\ORMEntity;
use Gobl\Tests\BaseTestCase;
use Throwable;

/**
 * Class ORMEntityIdentityKeyTest.
 *
 * Tests for {@see ORMEntity::toIdentityKey()}.
 *
 * @covers \Gobl\ORM\ORMEntity::toIdentityKey
 *
 * @internal
 */
final class ORMEntityIdentityKeyTest extends BaseTestCase
{
	private static bool $setupOk = false;

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		try {
			ORM::getDatabase(self::TEST_DB_NAMESPACE);
			ORM::undeclareNamespace(self::TEST_DB_NAMESPACE);
		} catch (Throwable) {
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
		}

		self::$setupOk = false;

		parent::tearDownAfterClass();
	}

	protected function setUp(): void
	{
		parent::setUp();

		if (!self::$setupOk) {
			self::markTestSkipped('ORM identity key test setup failed.');
		}
	}

	// -------------------------------------------------------------------------
	// Single integer PK (clients - client_id)
	// -------------------------------------------------------------------------

	public function testSingleIntPKProducesStringOfValue(): void
	{
		$table  = ORM::getDatabase(self::TEST_DB_NAMESPACE)->getTableOrFail('clients');
		$entity = ORM::entity($table);
		$entity->hydrate(['client_id' => 42]);

		self::assertSame('42', $entity->toIdentityKey());
	}

	public function testSingleIntPKNullThrows(): void
	{
		$table  = ORM::getDatabase(self::TEST_DB_NAMESPACE)->getTableOrFail('clients');
		$entity = ORM::entity($table);
		// client_id is auto-increment and defaults to null -> toIdentityFilters throws

		$this->expectException(ORMException::class);

		$entity->toIdentityKey();
	}

	public function testDifferentEntityPKsProduceDifferentKeys(): void
	{
		$table   = ORM::getDatabase(self::TEST_DB_NAMESPACE)->getTableOrFail('clients');
		$entity1 = ORM::entity($table);
		$entity1->hydrate(['client_id' => 1]);

		$entity2 = ORM::entity($table);
		$entity2->hydrate(['client_id' => 2]);

		self::assertNotSame($entity1->toIdentityKey(), $entity2->toIdentityKey());
	}

	// -------------------------------------------------------------------------
	// String PK (currencies - ccy_code)
	// -------------------------------------------------------------------------

	public function testStringPKProducesCorrectKey(): void
	{
		$table  = ORM::getDatabase(self::TEST_DB_NAMESPACE)->getTableOrFail('currencies');
		$entity = ORM::entity($table);
		$entity->hydrate(['ccy_code' => 'USD']);

		self::assertSame('USD', $entity->toIdentityKey());
	}

	public function testStringPKKeyMatchesCodeColumn(): void
	{
		$table  = ORM::getDatabase(self::TEST_DB_NAMESPACE)->getTableOrFail('currencies');
		$entity = ORM::entity($table);
		$entity->hydrate(['ccy_code' => 'EUR']);

		self::assertSame('EUR', $entity->toIdentityKey());
	}
}
