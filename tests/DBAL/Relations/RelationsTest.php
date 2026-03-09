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

use Gobl\DBAL\Relations\RelationType;
use Gobl\DBAL\Table;
use Gobl\Tests\BaseTestCase;

/**
 * Class RelationsTest.
 *
 * Tests that the relation definitions on tables (belongsTo / hasMany)
 * produce the expected relation types and link the correct host/target tables.
 *
 * Uses getSampleDB() which defines users, roles, tags, taggables, articles.
 *
 * @covers \Gobl\DBAL\Relations\ManyToOne
 * @covers \Gobl\DBAL\Relations\OneToMany
 * @covers \Gobl\DBAL\Relations\Relation
 *
 * @internal
 */
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

		self::assertSame('articles', $relation->getHostTable()->getName());
	}
	// ----------------------------------------------------------------------
	// Helpers
	// ----------------------------------------------------------------------

	/** Returns the sample DB and the named table from it. */
	private function getTable(string $name): Table
	{
		$db = self::getSampleDB();

		return $db->getTableOrFail($name);
	}
}
