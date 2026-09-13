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

namespace Gobl\Tests\DBAL;

use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Table;
use Gobl\Tests\BaseTestCase;
use Throwable;

/**
 * Class DbLazySchemaTest.
 *
 * @covers \Gobl\DBAL\Db
 * @covers \Gobl\DBAL\Table
 *
 * @internal
 */
final class DbLazySchemaTest extends BaseTestCase
{
	public function testEagerByDefault(): void
	{
		self::assertFalse(self::getNewDbInstance()->isLazySchema());
		self::assertTrue(self::getNewDbInstance()->setLazySchema()->isLazySchema());
	}

	public function testBuildsTheSameTablesAsAnEagerLoad(): void
	{
		$eager = self::getNewDbInstance();
		$eager->ns(self::TEST_DB_NAMESPACE)
			->schema(self::getTablesDefinitions());

		$lazy = self::lazyDb();

		// One built ahead of the others, out of the declaration order.
		$lazy->getTable('transactions');

		// Locked first: a column's diff key derives from its table's only once locked with it.
		$eager->lock();
		$lazy->lock();

		self::assertSame(\array_keys($eager->getTables()), \array_keys($lazy->getTables()));
		self::assertSame($eager->toSchemaArray(), $lazy->toSchemaArray());
		self::assertSame(
			$eager->getGenerator()
				->buildDatabase(),
			$lazy->getGenerator()
				->buildDatabase()
		);
	}

	public function testTablesAreBuiltWhenFirstUsed(): void
	{
		$db = self::getNewDbInstance()
			->setLazySchema();

		$db->ns('Lazy\Db')
			->schema([
				'good'   => ['columns' => ['id' => ['type' => 'int']]],
				'broken' => ['columns' => ['id' => ['type' => 'not-a-type']]],
			]);

		// Declared: the invalid definition is only read when its table is used.
		self::assertTrue($db->hasTable('broken'));
		self::assertInstanceOf(Table::class, $db->getTable('good'));

		try {
			$db->getTable('broken');
			self::fail('An invalid table definition should fail when its table is first used.');
		} catch (DBALException $e) {
			self::assertStringContainsString('"broken"', $e->getMessage());
		}

		// And every time after, rather than handing out a half-built table.
		$this->expectException(DBALException::class);
		$this->expectExceptionMessage('The table "broken" could not be built.');

		$db->getTable('broken');
	}

	public function testAColumnReferenceReadsTheReferencedDefinition(): void
	{
		$db = self::getNewDbInstance()
			->setLazySchema();

		$db->ns('Lazy\Db')
			->schema([
				'users' => ['columns' => [
					'id'  => ['type' => 'bigint', 'unsigned' => true],
					'bad' => ['type' => 'not-a-type'],
				]],
				'posts' => ['columns' => [
					'id'      => ['type' => 'int'],
					'user_id' => 'ref:users.id',
				]],
			]);

		// "users" would fail to build: its definition is read, it is not built.
		$user_id = $db->getTableOrFail('posts')
			->getColumnOrFail('user_id')
			->getType();

		self::assertSame('bigint', $user_id->getName());
		self::assertTrue($user_id->toArray()['unsigned'] ?? false);
	}

	public function testNamesAreTakenWhenDeclared(): void
	{
		$db     = self::getNewDbInstance()->setLazySchema();
		$prefix = $db->getConfig()
			->getDbTablePrefix();
		$full   = empty($prefix) ? 'users' : $prefix . '_users';

		$db->ns('Lazy\Db')
			->schema(['users' => ['columns' => ['id' => ['type' => 'int']]]]);

		self::assertTrue($db->hasTable('users'));
		self::assertTrue($db->hasTable($full));
		self::assertSame($full, $db->getTableOrFail($full)->getFullName());

		$this->expectException(DBALException::class);
		$this->expectExceptionMessage('The table name conflict with an existing table name or full name: "users".');

		$db->addTable(new Table('users'));
	}

	public function testForeignKeysAndRelationsReachTablesBuiltOnDemand(): void
	{
		$db           = self::lazyDb();
		$transactions = $db->getTableOrFail('transactions');

		$fk = $transactions->getForeignKeyConstraints();
		self::assertCount(1, $fk);

		$accounts = $transactions->getRelation('account')
			?->getTargetTable();
		self::assertInstanceOf(Table::class, $accounts);

		// The target's own relations, read through the relation, finish building it.
		$client = $accounts->getRelation('client');
		self::assertNotNull($client);

		self::assertSame($db->getTableOrFail('accounts'), $accounts);
		self::assertSame($db->getTableOrFail('clients'), $client->getTargetTable());
		self::assertSame($accounts, \array_values($fk)[0]->getReferenceTable());

		// Both ways round: clients -> accounts -> client.
		self::assertSame(
			$db->getTableOrFail('clients'),
			$db->getTableOrFail('clients')
				->getRelation('accounts')
				?->getTargetTable()
				->getRelation('client')
				?->getTargetTable()
		);
	}

	public function testTablesBuiltAfterTheLockAreLocked(): void
	{
		$db = self::lazyDb();

		$db->lock();

		$clients = $db->getTableOrFail('clients');

		self::assertTrue($clients->isLocked());
		self::assertSame($clients, $db->getTableByMorphType($clients->getMorphType()));

		// reached through a relation: locked once something finishes building it
		$accounts = $clients->getRelation('accounts')
			?->getTargetTable();
		self::assertInstanceOf(Table::class, $accounts);
		self::assertNotNull($accounts->getRelation('transactions'));
		self::assertTrue($accounts->isLocked());
	}

	public function testTablesCannotBeDeclaredOnceLocked(): void
	{
		$db = self::getNewDbInstance()->setLazySchema();

		$db->lock();

		try {
			$db->ns('Lazy\Db')
				->schema(['users' => ['columns' => ['id' => ['type' => 'int']]]]);
			self::fail('A locked database should refuse new tables.');
		} catch (Throwable $t) {
			self::assertFalse($db->hasTable('users'));
		}
	}

	private static function lazyDb(): RDBMSInterface
	{
		$db = self::getNewDbInstance()->setLazySchema();

		$db->ns(self::TEST_DB_NAMESPACE)
			->schema(self::getTablesDefinitions());

		return $db;
	}
}
