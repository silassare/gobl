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

use Gobl\DBAL\Builders\TableBuilder;
use Gobl\DBAL\Relations\LinkColumns;
use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Relations\ManyToOne;
use Gobl\DBAL\Relations\RelationOnAttach;
use Gobl\DBAL\Relations\RelationOnRemove;
use Gobl\DBAL\Relations\OneToMany;
use Gobl\DBAL\Relations\Relation;
use Gobl\DBAL\Relations\RelationType;
use Gobl\DBAL\Table;
use Gobl\Tests\BaseTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Class RelationsTest.
 *
 * Tests that the relation definitions on tables (belongsTo / hasMany)
 * produce the expected relation types and link the correct host/target tables.
 *
 * Uses getSampleDB() which defines users, roles, tags, taggables, articles.
 *
 * @internal
 */
#[CoversClass(LinkColumns::class)]
#[CoversClass(ManyToOne::class)]
#[CoversClass(OneToMany::class)]
#[CoversClass(Relation::class)]
final class RelationsTest extends BaseTestCase
{
	// ----------------------------------------------------------------------
	// users table
	// ----------------------------------------------------------------------

	public function testUsersHasManyRoles(): void
	{
		$table    = $this->getTable('users');
		$relation = $table->getRelation('roles');

		self::assertNotNull($relation, 'users => roles relation not found');
		self::assertSame(RelationType::ONE_TO_MANY, $relation->getType());
		self::assertSame('roles', $relation->getTargetTable()->getName());
	}

	// ----------------------------------------------------------------------
	// roles table
	// ----------------------------------------------------------------------

	public function testRolesBelongsToUser(): void
	{
		$table    = $this->getTable('roles');
		$relation = $table->getRelation('user');

		self::assertNotNull($relation, 'roles => user relation not found');
		self::assertSame(RelationType::MANY_TO_ONE, $relation->getType());
		self::assertSame('users', $relation->getTargetTable()->getName());
	}

	public function testRolesHasManyUsers(): void
	{
		$table    = $this->getTable('roles');
		$relation = $table->getRelation('users');

		self::assertNotNull($relation, 'roles => users relation not found');
		self::assertSame(RelationType::ONE_TO_MANY, $relation->getType());
		self::assertSame('users', $relation->getTargetTable()->getName());
	}

	// ----------------------------------------------------------------------
	// taggables table
	// ----------------------------------------------------------------------

	public function testTaggablesBelongsToTag(): void
	{
		$table    = $this->getTable('taggables');
		$relation = $table->getRelation('tag');

		self::assertNotNull($relation, 'taggables => tag relation not found');
		self::assertSame(RelationType::MANY_TO_ONE, $relation->getType());
		self::assertSame('tags', $relation->getTargetTable()->getName());
	}

	// ----------------------------------------------------------------------
	// articles table
	// ----------------------------------------------------------------------

	public function testArticlesBelongsToUser(): void
	{
		$table    = $this->getTable('articles');
		$relation = $table->getRelation('user');

		self::assertNotNull($relation, 'articles => user relation not found');
		self::assertSame(RelationType::MANY_TO_ONE, $relation->getType());
		self::assertSame('users', $relation->getTargetTable()->getName());
	}

	public function testArticlesHasManyTagsThroughTaggables(): void
	{
		$table    = $this->getTable('articles');
		$relation = $table->getRelation('tags');

		self::assertNotNull($relation, 'articles => tags relation not found');
		self::assertSame(RelationType::ONE_TO_MANY, $relation->getType());
		self::assertSame('tags', $relation->getTargetTable()->getName());
	}

	public function testArticlesHasManyRecentlyAddedTagsThroughTaggables(): void
	{
		$table    = $this->getTable('articles');
		$relation = $table->getRelation('recently_added_tags');

		self::assertNotNull($relation, 'articles => recently_added_tags relation not found');
		self::assertSame(RelationType::ONE_TO_MANY, $relation->getType());
		self::assertSame('tags', $relation->getTargetTable()->getName());
	}

	// ----------------------------------------------------------------------
	// Non-existent relation returns null
	// ----------------------------------------------------------------------

	public function testNonExistentRelationReturnsNull(): void
	{
		$table = $this->getTable('tags');
		self::assertNull($table->getRelation('does_not_exist'));
	}

	// ----------------------------------------------------------------------
	// Relation mutiplicity helpers
	// ----------------------------------------------------------------------

	public function testRelationTypeIsMultiple(): void
	{
		self::assertTrue(RelationType::ONE_TO_MANY->isMultiple());
		self::assertFalse(RelationType::ONE_TO_ONE->isMultiple());
		self::assertFalse(RelationType::MANY_TO_ONE->isMultiple());
	}

	// ----------------------------------------------------------------------
	// Relation host table
	// ----------------------------------------------------------------------

	public function testRelationHostTable(): void
	{
		$db       = self::getSampleDB();
		$articles = $db->getTableOrFail('articles');
		$relation = $articles->getRelation('tags');

		self::assertNotNull($relation);

		self::assertSame('articles', $relation->getHostTable()->getName());
	}

	/**
	 * Which side holds the key is read off the columns, from the foreign key either side declares:
	 * a role points at its user, so the role's relation to its user is held by the role.
	 */
	public function testALinkOfColumnsKnowsWhichSideHoldsTheKey(): void
	{
		self::assertTrue($this->columnsLinkOf('roles', 'user')->isHostHoldingKey());
		self::assertFalse($this->columnsLinkOf('users', 'roles')->isHostHoldingKey());
	}

	/**
	 * Columns given by hand are matched to the foreign key they are; with none declared, the side
	 * that points at the other's primary key holds it.
	 */
	public function testColumnsGivenByHandHoldTheKeyOnTheSideTheyPointFrom(): void
	{
		$db = self::getNewDbInstance();
		$ns = $db->ns('holds_test');

		$clients = $ns->table('clients', static function (TableBuilder $t): void {
			$t->columnPrefix('client');
			$t->id();
		});

		$ns->table('accounts', static function (TableBuilder $t): void {
			$t->columnPrefix('account');
			$t->id();
			$t->foreign('client_id', 'clients', 'id');
			$t->belongsTo('client')->from('clients')->usingColumns(['client_id' => 'id']);
		});

		// No foreign key at all: a note points at its client's primary key by convention only.
		$ns->table('notes', static function (TableBuilder $t): void {
			$t->columnPrefix('note');
			$t->id();
			$t->bigint('client_ref')->unsigned();
			$t->belongsTo('client')->from('clients')->usingColumns(['client_ref' => 'id']);
		});

		$clients->factory(static function (TableBuilder $t): void {
			$t->hasMany('accounts')->from('accounts')->usingColumns(['id' => 'client_id']);
			$t->hasMany('notes')->from('notes')->usingColumns(['id' => 'client_ref']);
		});

		$db->lock();

		$link = static function (string $table, string $relation) use ($db): LinkColumns {
			$found = $db->getTableOrFail($table)->getRelation($relation)?->getLink();

			self::assertInstanceOf(LinkColumns::class, $found);

			return $found;
		};

		self::assertTrue($link('accounts', 'client')->isHostHoldingKey());
		self::assertFalse($link('clients', 'accounts')->isHostHoldingKey());
		self::assertTrue($link('notes', 'client')->isHostHoldingKey());
		self::assertFalse($link('clients', 'notes')->isHostHoldingKey());
	}
	// ----------------------------------------------------------------------
	// Helpers
	// ----------------------------------------------------------------------

	/**
	 * The write strategies a schema declares are read back as declared, the defaults are not
	 * written out, and an unknown one is refused rather than read as the default.
	 */
	public function testWriteStrategiesRoundTripThroughASchema(): void
	{
		$schema = static fn (array $extra): array => [
			'parents' => [
				'column_prefix' => 'parent',
				'constraints'   => [['type' => 'primary_key', 'columns' => ['id']]],
				'columns'       => ['id' => ['type' => 'bigint', 'auto_increment' => true, 'unsigned' => true]],
				'relations'     => ['kids' => ['type' => 'one-to-many', 'target' => 'kids'] + $extra],
			],
			'kids' => [
				'column_prefix' => 'kid',
				'constraints'   => [
					['type' => 'primary_key', 'columns' => ['id']],
					['type' => 'foreign_key', 'reference' => 'parents', 'columns' => ['parent_id' => 'id']],
				],
				'columns' => [
					'id'        => ['type' => 'bigint', 'auto_increment' => true, 'unsigned' => true],
					'parent_id' => 'ref:parents.id',
				],
			],
		];

		$db = self::getNewDbInstance();
		$db->ns('strategies')->schema($schema(['on_remove' => 'delete', 'on_attach' => 'move']));

		$kids = $db->getTableOrFail('parents')->getRelation('kids');

		self::assertNotNull($kids);
		self::assertSame(RelationOnRemove::DELETE, $kids->getOnRemove());
		self::assertSame(RelationOnAttach::MOVE, $kids->getOnAttach());
		self::assertSame('delete', $kids->toArray()['on_remove'] ?? null);

		$plain = self::getNewDbInstance();
		$plain->ns('strategies')->schema($schema([]));

		self::assertArrayNotHasKey('on_remove', $plain->getTableOrFail('parents')->getRelation('kids')?->toArray() ?? []);

		$this->expectException(DBALException::class);

		self::getNewDbInstance()->ns('strategies')->schema($schema(['on_remove' => 'shred']));
	}

	/** The link of a relation of the sample DB, which these tests know to be one of columns. */
	private function columnsLinkOf(string $table, string $relation): LinkColumns
	{
		$link = $this->getTable($table)->getRelation($relation)?->getLink();

		self::assertInstanceOf(LinkColumns::class, $link);

		return $link;
	}

	/** Returns the sample DB and the named table from it. */
	private function getTable(string $name): Table
	{
		$db = self::getSampleDB();

		return $db->getTableOrFail($name);
	}
}
