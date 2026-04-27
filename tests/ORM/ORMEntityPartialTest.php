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
use Gobl\ORM\Exceptions\ORMRuntimeException;
use Gobl\ORM\Generators\CSGeneratorORM;
use Gobl\ORM\ORM;
use Gobl\ORM\ORMEntity;
use Gobl\ORM\Utils\ORMClassKind;
use Gobl\Tests\BaseTestCase;

/**
 * Class ORMEntityPartialTest.
 *
 * Tests for partial-entity support introduced per-relation column projection.
 * Covers {@see ORMEntity::markAsPartial()}, {@see ORMEntity::isPartial()},
 * {@see ORMEntity::isColumnLoaded()} and the guard in {@see ORMEntity::__get()}.
 *
 * Uses the standard clients table (column prefix: client).
 *
 * @covers \Gobl\ORM\ORMEntity::isColumnLoaded
 * @covers \Gobl\ORM\ORMEntity::isPartial
 * @covers \Gobl\ORM\ORMEntity::markAsPartial
 *
 * @internal
 */
final class ORMEntityPartialTest extends BaseTestCase
{
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		$db = self::getNewDbInstance(MySQL::NAME);
		$db->ns(self::TEST_DB_NAMESPACE)
			->schema(self::getTablesDefinitions())
			->enableORM(GOBL_TEST_ORM_OUTPUT);

		$ormOutDir = GOBL_TEST_ORM_OUTPUT;

		if (!\is_dir($ormOutDir . \DIRECTORY_SEPARATOR . 'Base')) {
			$generator = new CSGeneratorORM($db);
			$generator->generate($db->getTables(self::TEST_DB_NAMESPACE), $ormOutDir);
		}
	}

	/**
	 * New entities must NOT be partial by default.
	 */
	public function testNewEntityIsNotPartial(): void
	{
		$db     = ORM::getDatabase(self::TEST_DB_NAMESPACE);
		$table  = $db->getTableOrFail('clients');
		$entity = ORM::entity($table, true); // is_new = true

		self::assertFalse($entity->isPartial(), 'New entity must not be partial by default');
	}

	/**
	 * A non-new entity that was not marked is not partial.
	 */
	public function testNonPartialEntityIsNotPartial(): void
	{
		$db     = ORM::getDatabase(self::TEST_DB_NAMESPACE);
		$table  = $db->getTableOrFail('clients');
		$entity = ORM::entity($table, false);
		$entity->isSaved(true);

		self::assertFalse($entity->isPartial(), 'Non-new entity must not be partial unless markAsPartial() was called');
	}

	/**
	 * markAsPartial() must flip isPartial() to true.
	 */
	public function testMarkAsPartialFlipsIsPartial(): void
	{
		$entity = $this->makePartialClientEntity();

		self::assertTrue($entity->isPartial(), 'markAsPartial() must flip isPartial() to true');
	}

	/**
	 * markAsPartial() must return the same entity instance (fluent).
	 */
	public function testMarkAsPartialReturnsSameInstance(): void
	{
		$db     = ORM::getDatabase(self::TEST_DB_NAMESPACE);
		$table  = $db->getTableOrFail('clients');
		$entity = ORM::entity($table, false);

		$returned = $entity->markAsPartial(['client_id']);

		self::assertSame($entity, $returned, 'markAsPartial() must return the same entity instance');
	}

	public function testMarkAsPartialAcceptShortColumnName(): void
	{
		$db     = ORM::getDatabase(self::TEST_DB_NAMESPACE);
		$table  = $db->getTableOrFail('clients');
		$entity = ORM::entity($table, false);

		$entity->markAsPartial(['id']);

		self::assertTrue($entity->isColumnLoaded('id'), 'markAsPartial() must accept short column name and resolve it to full name');
	}

	/**
	 * isColumnLoaded() always returns true for new entities.
	 */
	public function testIsColumnLoadedAlwaysTrueForNewEntity(): void
	{
		$db     = ORM::getDatabase(self::TEST_DB_NAMESPACE);
		$table  = $db->getTableOrFail('clients');
		$entity = ORM::entity($table, true);

		self::assertTrue(
			$entity->isColumnLoaded('client_last_name'),
			'isColumnLoaded() must return true for new entities regardless of column'
		);
	}

	/**
	 * isColumnLoaded() always returns true for non-partial entities.
	 */
	public function testIsColumnLoadedAlwaysTrueForFullEntity(): void
	{
		$db     = ORM::getDatabase(self::TEST_DB_NAMESPACE);
		$table  = $db->getTableOrFail('clients');
		$entity = ORM::entity($table, false);
		$entity->isSaved(true);

		self::assertTrue(
			$entity->isColumnLoaded('client_last_name'),
			'isColumnLoaded() must return true for non-partial entities'
		);
	}

	/**
	 * isColumnLoaded() returns true for a column that was in the partial set.
	 */
	public function testIsColumnLoadedReturnsTrueForLoadedColumn(): void
	{
		$entity = $this->makePartialClientEntity();

		self::assertTrue(
			$entity->isColumnLoaded('client_id'),
			'client_id was in the partial set'
		);
		self::assertTrue(
			$entity->isColumnLoaded('client_first_name'),
			'client_first_name was in the partial set'
		);
	}

	/**
	 * isColumnLoaded() returns false for columns not in the partial set.
	 */
	public function testIsColumnLoadedReturnsFalseForUnloadedColumn(): void
	{
		$entity = $this->makePartialClientEntity();

		self::assertFalse(
			$entity->isColumnLoaded('client_last_name'),
			'client_last_name was NOT in the partial set'
		);
	}

	/**
	 * isColumnLoaded() also accepts short column names.
	 */
	public function testIsColumnLoadedAcceptsShortName(): void
	{
		$entity = $this->makePartialClientEntity();

		self::assertTrue($entity->isColumnLoaded('id'), 'short name "id" resolves to client_id which was loaded');
		self::assertFalse($entity->isColumnLoaded('last_name'), 'short name "last_name" resolves to client_last_name which was NOT loaded');
	}

	/**
	 * Reading a loaded column on a partial entity must succeed.
	 */
	public function testGetLoadedColumnOnPartialEntitySucceeds(): void
	{
		$entity = $this->makePartialClientEntity();

		self::assertIsInt($entity->client_id);
	}

	/**
	 * Reading an unloaded column on a partial entity must throw ORMRuntimeException.
	 */
	public function testGetUnloadedColumnOnPartialEntityThrows(): void
	{
		$entity = $this->makePartialClientEntity();

		$this->expectException(ORMRuntimeException::class);

		/** @noinspection PhpExpressionResultUnusedInspection */
		$entity->client_last_name;
	}

	/**
	 * Accessing an unloaded column via short name on a partial entity must also throw.
	 */
	public function testGetUnloadedColumnViaShortNameThrows(): void
	{
		$entity = $this->makePartialClientEntity();

		$this->expectException(ORMRuntimeException::class);

		/** @noinspection PhpExpressionResultUnusedInspection */
		$entity->last_name;
	}

	/**
	 * markAsPartial() after ORM::entity() must create a partial entity with the given projection.
	 */
	public function testMarkAsPartialAfterEntityFactoryCreatesPartialEntity(): void
	{
		$db     = ORM::getDatabase(self::TEST_DB_NAMESPACE);
		$table  = $db->getTableOrFail('clients');
		$entity = ORM::entity($table, false);
		$entity->markAsPartial(['client_id', 'client_first_name']);

		self::assertTrue($entity->isPartial(), 'markAsPartial() must mark the entity as partial');
		self::assertTrue($entity->isColumnLoaded('client_id'), 'client_id must be marked loaded');
		self::assertTrue($entity->isColumnLoaded('client_first_name'), 'client_first_name must be marked loaded');
		self::assertFalse($entity->isColumnLoaded('client_last_name'), 'client_last_name was not in the projection');
	}

	/**
	 * Generated entity::new() followed by markAsPartial() must produce a partial entity.
	 */
	public function testGeneratedEntityNewAndMarkAsPartial(): void
	{
		$db     = ORM::getDatabase(self::TEST_DB_NAMESPACE);
		$table  = $db->getTableOrFail('clients');

		/** @var class-string<ORMEntity> $entity_class */
		$entity_class = ORMClassKind::ENTITY->getClassFQN($table);
		$entity       = $entity_class::new(false);
		$entity->markAsPartial(['client_id', 'client_first_name']);

		self::assertTrue($entity->isPartial(), 'Entity marked via markAsPartial() must be partial');
		self::assertTrue($entity->isColumnLoaded('client_id'));
		self::assertFalse($entity->isColumnLoaded('client_last_name'));
	}

	/**
	 * ORM::entity() without markAsPartial() must create a non-partial entity.
	 */
	public function testEntityIsNotPartialByDefault(): void
	{
		$db     = ORM::getDatabase(self::TEST_DB_NAMESPACE);
		$table  = $db->getTableOrFail('clients');
		$entity = ORM::entity($table, false);

		self::assertFalse($entity->isPartial(), 'ORM::entity() without markAsPartial() must not be partial');
	}

	/**
	 * Creates a non-new Client entity marked as partial with client_id and client_first_name loaded.
	 */
	private function makePartialClientEntity(): ORMEntity
	{
		$db     = ORM::getDatabase(self::TEST_DB_NAMESPACE);
		$table  = $db->getTableOrFail('clients');
		$entity = ORM::entity($table, false);
		$entity->isSaved(true);
		$entity->markAsPartial([
			'client_id',
			'client_first_name',
		]);

		return $entity;
	}
}
