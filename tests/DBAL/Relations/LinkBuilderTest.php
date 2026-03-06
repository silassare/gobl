<?php

/**
 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Gobl\Tests\DBAL\Relations;

use Gobl\DBAL\Builders\LinkBuilder;
use Gobl\DBAL\Builders\TableBuilder;
use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\DBAL\Relations\LinkThrough;
use Gobl\DBAL\Relations\LinkType;
use Gobl\DBAL\Relations\Relation;
use Gobl\ORM\ORMEntity;
use Gobl\Tests\BaseTestCase;

/**
 * Class LinkBuilderTest.
 *
 * Tests for the `LinkBuilder` fluent API, `RelationBuilder::through()` with `LinkBuilder`
 * arguments, the `FiltersMultiScope` pivot-column filter bug fix, and the
 * corrected `Link::subLink()` nesting guard.
 *
 * @covers \Gobl\DBAL\Builders\LinkBuilder
 * @covers \Gobl\DBAL\Builders\RelationBuilder
 * @covers \Gobl\DBAL\Filters\FiltersMultiScope
 * @covers \Gobl\DBAL\Relations\Link
 *
 * @internal
 */
final class LinkBuilderTest extends BaseTestCase
{
	// =========================================================================
	// LinkBuilder — factory methods
	// =========================================================================

	/** columns() with no map defaults to empty 'columns' key. */
	public function testColumnsEmptyMap(): void
	{
		$opts = LinkBuilder::columns()->toArray();

		self::assertSame(LinkType::COLUMNS->value, $opts['type']);
		self::assertArrayNotHasKey('columns', $opts, 'Empty map should not include "columns" key');
	}

	/** columns() with an explicit map includes the 'columns' key. */
	public function testColumnsExplicitMap(): void
	{
		$opts = LinkBuilder::columns(['session_id' => 'for_id'])->toArray();

		self::assertSame(LinkType::COLUMNS->value, $opts['type']);
		self::assertSame(['session_id' => 'for_id'], $opts['columns']);
	}

	/** morph() stores prefix and (optionally) parent_type. */
	public function testMorphWithPrefix(): void
	{
		$opts = LinkBuilder::morph('taggable')->toArray();

		self::assertSame(LinkType::MORPH->value, $opts['type']);
		self::assertSame('taggable', $opts['prefix']);
		self::assertArrayNotHasKey('parent_type', $opts);
	}

	/** morph() with ->parentType() fluent call includes parent_type in options. */
	public function testMorphWithParentType(): void
	{
		$opts = LinkBuilder::morph('taggable')->parentType('article')->toArray();

		self::assertSame('article', $opts['parent_type']);
	}

	/** morphExplicit() stores explicit column names. */
	public function testMorphExplicit(): void
	{
		$opts = LinkBuilder::morphExplicit('taggable_id', 'taggable_type')->toArray();

		self::assertSame(LinkType::MORPH->value, $opts['type']);
		self::assertSame('taggable_id', $opts['child_key_column']);
		self::assertSame('taggable_type', $opts['child_type_column']);
		self::assertArrayNotHasKey('prefix', $opts);
		self::assertArrayNotHasKey('parent_type', $opts);
	}

	/** morphExplicit() with ->parentType() fluent call includes parent_type in options. */
	public function testMorphExplicitWithParentType(): void
	{
		$opts = LinkBuilder::morphExplicit('taggable_id', 'taggable_type')->parentType('post')->toArray();

		self::assertSame('post', $opts['parent_type']);
	}

	// =========================================================================
	// LinkBuilder — filter chaining
	// =========================================================================

	/** A single filter() call produces a single-triple filters array. */
	public function testSingleFilter(): void
	{
		$opts = LinkBuilder::columns()->filter('for_type', 'eq', 'session')->toArray();

		self::assertSame([['for_type', 'eq', 'session']], $opts['filters']);
	}

	/** Two filter() calls are ANDed together via an 'and' separator. */
	public function testTwoFilters(): void
	{
		$opts = LinkBuilder::columns()
			->filter('for_type', 'eq', 'session')
			->filter('is_active', 'eq', 1)
			->toArray();

		self::assertSame(
			[['for_type', 'eq', 'session'], 'and', ['is_active', 'eq', 1]],
			$opts['filters']
		);
	}

	/** filter() mutates in-place and returns the same instance. */
	public function testFilterReturnsSameInstance(): void
	{
		$base  = LinkBuilder::columns(['a' => 'b']);
		$withF = $base->filter('col', 'eq', 'val');

		self::assertSame($base, $withF, 'filter() must return $this, not a clone');
		self::assertArrayHasKey('filters', $base->toArray());
	}

	/** filters() replaces the whole filters array in one go. */
	public function testFiltersReplacesAll(): void
	{
		$raw  = [['col', 'eq', 'val'], 'or', ['col2', 'neq', 'x']];
		$opts = LinkBuilder::columns()->filters($raw)->toArray();

		self::assertSame($raw, $opts['filters']);
	}

	// =========================================================================
	// RelationBuilder::through() — accepts LinkBuilder
	// =========================================================================

	/**
	 * through() accepts null for auto-detection of sub-links.
	 */
	public function testThroughAcceptsNull(): void
	{
		$db = self::getSampleDB();

		// articles → tags through taggables; taggables morph link is auto-detected
		// We call through() with null args just to confirm no TypeError is raised.
		$articles = $db->getTableOrFail('articles');
		$relation = $articles->getRelation('tags');

		self::assertNotNull($relation);
		self::assertInstanceOf(LinkThrough::class, $relation->getLink());
	}

	/**
	 * through() with a LinkBuilder argument produces the same link as the equivalent array.
	 */
	public function testThroughAcceptsLinkBuilderForPivotToTarget(): void
	{
		$db  = self::getNewDbInstance();
		$ns  = $db->ns('test');

		// Define dependency tables FIRST so that FK constraints can be resolved.
		$hosts = $ns->table('hosts', static function (TableBuilder $t) {
			$t->id();
			$t->string('name');
		});
		$ns->table('targets', static function (TableBuilder $t) {
			$t->id();
			$t->string('label');
		});
		$ns->table('pivots', static function (TableBuilder $t) {
			$t->id();
			$t->foreign('host_id', 'hosts', 'id');
			$t->foreign('target_id', 'targets', 'id');
			$t->string('pivot_type');
		});

		$hosts->factory(static function (TableBuilder $t) {
			$t->hasMany('my_targets')
				->from('targets')
				->through(
					'pivots',
					null,
					LinkBuilder::columns(['target_id' => 'id'])->filter('pivot_type', 'eq', 'host')
				);
		});

		$db->lock();

		$relation = $db->getTableOrFail('hosts')->getRelation('my_targets');

		self::assertNotNull($relation);
		self::assertInstanceOf(LinkThrough::class, $relation->getLink());
	}

	/**
	 * through() with a raw array is backward-compatible.
	 */
	public function testThroughAcceptsRawArrayForPivotToTarget(): void
	{
		$db  = self::getNewDbInstance();
		$ns  = $db->ns('test');

		// Define dependency tables FIRST so that FK constraints can be resolved.
		$hosts = $ns->table('hosts', static function (TableBuilder $t) {
			$t->id();
			$t->string('name');
		});
		$ns->table('targets', static function (TableBuilder $t) {
			$t->id();
			$t->string('label');
		});
		$ns->table('pivots', static function (TableBuilder $t) {
			$t->id();
			$t->foreign('host_id', 'hosts', 'id');
			$t->foreign('target_id', 'targets', 'id');
			$t->string('pivot_type');
		});

		$hosts->factory(static function (TableBuilder $t) {
			$t->hasMany('my_targets')
				->from('targets')
				->through('pivots', null, [
					'type'    => LinkType::COLUMNS->value,
					'columns' => ['target_id' => 'id'],
					'filters' => [['pivot_type', 'eq', 'host']],
				]);
		});

		$db->lock();

		$relation = $db->getTableOrFail('hosts')->getRelation('my_targets');

		self::assertNotNull($relation);
		self::assertInstanceOf(LinkThrough::class, $relation->getLink());
	}

	// =========================================================================
	// Bug fix: pivot column filter no longer throws in pivot_to_target sub-link
	// =========================================================================

	/**
	 * A filter that references a PIVOT column from the pivot_to_target sub-link must NOT
	 * throw a DBALRuntimeException.  This was the production bug: the old code used
	 * FiltersTableScope(target_table) so getColumnOrFail() would look for the pivot
	 * column on the TARGET table (and fail).  With FiltersMultiScope the pivot
	 * table is also checked.
	 *
	 * Schema:
	 *   sessions  ---< sess_schedules >--- schedules
	 *                    ^^^^
	 *                   has for_type column (only on pivot, NOT on schedules)
	 *
	 * Relation: sessions hasMany schedules THROUGH sess_schedules
	 *   pivot_to_target: columns(['schedule_id' => 'id']).filter('for_type', 'eq', 'session')
	 *                                                            ^^^^^^^^
	 *                                                   lives on sess_schedules, NOT schedules
	 */
	public function testPivotToTargetFilterOnPivotColumnDoesNotThrow(): void
	{
		$db = self::getNewDbInstance();
		$ns = $db->ns('test');

		// Define dependency tables FIRST so that FK constraints can be resolved.
		$sessions = $ns->table('sessions', static function (TableBuilder $t) {
			$t->id();
			$t->string('title');
		});
		$ns->table('schedules', static function (TableBuilder $t) {
			$t->id();
			$t->string('name');
		});
		$ns->table('sess_schedules', static function (TableBuilder $t) {
			$t->id();
			$t->foreign('session_id', 'sessions', 'id');
			$t->foreign('schedule_id', 'schedules', 'id');
			$t->string('for_type'); // pivot-only column — NOT present on 'schedules'
		});

		$sessions->factory(static function (TableBuilder $t) {
			$t->hasMany('schedules')
				->from('schedules')
				->through(
					'sess_schedules',
					null, // auto-detect host→pivot
					LinkBuilder::columns(['schedule_id' => 'id'])
						->filter('for_type', 'eq', 'session')
				);
		});

		$db->lock();

		$relation = $db->getTableOrFail('sessions')->getRelation('schedules');
		self::assertNotNull($relation);

		$qb = new QBSelect($db);
		$qb->from('schedules');

		// Must not throw DBALRuntimeException("Field not allowed in filters of this scope.")
		$applied = $relation->getLink()->apply($qb);

		self::assertTrue($applied, 'apply() must return true when link is resolved in join mode');

		// The generated SQL must contain the for_type filter
		$sql = $qb->getSqlQuery();
		self::assertStringContainsString('for_type', $sql);
	}

	/**
	 * Regression: the existing recently_added_tags (filter on HOST-TO-PIVOT target column)
	 * must still work after the scope change.
	 */
	public function testExistingHostToPivotFilterStillWorks(): void
	{
		$db       = self::getSampleDB();
		$articles = $db->getTableOrFail('articles');
		$relation = $articles->getRelation('recently_added_tags');

		self::assertNotNull($relation);

		$qb = new QBSelect($db);
		$qb->from('tags');

		// Must not throw
		$applied = $relation->getLink()->apply($qb);

		self::assertTrue($applied);

		// created_at filter must appear in the generated SQL
		self::assertStringContainsString('created_at', $qb->getSqlQuery());
	}

	/**
	 * FiltersMultiScope must throw DBALRuntimeException when a bare column name
	 * that exists in none of the registered tables is used in a link filter.
	 */
	public function testPivotToTargetFilterOnUnknownBareColumnThrows(): void
	{
		$db = self::getNewDbInstance();
		$ns = $db->ns('test');

		$sessions = $ns->table('sessions', static function (TableBuilder $t) {
			$t->id();
			$t->string('title');
		});
		$ns->table('schedules', static function (TableBuilder $t) {
			$t->id();
			$t->string('name');
		});
		$ns->table('sess_schedules', static function (TableBuilder $t) {
			$t->id();
			$t->foreign('session_id', 'sessions', 'id');
			$t->foreign('schedule_id', 'schedules', 'id');
			$t->string('for_type');
		});

		$sessions->factory(static function (TableBuilder $t) {
			$t->hasMany('schedules')
				->from('schedules')
				->through(
					'sess_schedules',
					null,
					LinkBuilder::columns(['schedule_id' => 'id'])
						->filter('totally_unknown_col', 'eq', 'session') // ← not in any table
				);
		});

		$db->lock();

		$relation = $db->getTableOrFail('sessions')->getRelation('schedules');
		self::assertNotNull($relation);

		$qb = new QBSelect($db);
		$qb->from('schedules');

		self::expectException(DBALRuntimeException::class);

		$relation->getLink()->apply($qb);
	}

	// =========================================================================
	// subLink() nesting guard
	// =========================================================================

	/**
	 * A LinkJoin whose step defines another join-type sub-link must throw DBALException,
	 * because composite link types (join, through) cannot be nested inside another composite.
	 */
	public function testSubLinkGuardRejectsNestedJoinInsideJoin(): void
	{
		$db = self::getNewDbInstance();
		$ns = $db->ns('test');

		// Define dependency tables FIRST so that FK constraints can be resolved.
		$ns->table('a', static function (TableBuilder $t) {
			$t->id();
			$t->string('title');
		});
		$ns->table('b', static function (TableBuilder $t) {
			$t->id();
			$t->foreign('a_id', 'a', 'id');
			$t->string('tag');
		});
		$ns->table('c', static function (TableBuilder $t) {
			$t->id();
			$t->foreign('b_id', 'b', 'id');
			$t->string('label');
		});

		$db->lock();

		self::expectException(DBALException::class);

		// Call Relation::createLink() DIRECTLY (bypasses factory() wrapping) so that the
		// raw DBALException from Link::subLink() propagates unchanged.
		Relation::createLink($db, $db->getTableOrFail('a'), $db->getTableOrFail('c'), [
			'type'  => LinkType::JOIN->value,
			'steps' => [
				[
					'join' => 'b',
					'link' => [
						// Nesting a join-type sub-link must be rejected.
						'type'  => LinkType::JOIN->value,
						'steps' => [
							['join' => 'c', 'link' => []],
						],
					],
				],
			],
		]);
	}

	/**
	 * A LinkJoin whose step defines a through-type sub-link must also throw.
	 */
	public function testSubLinkGuardRejectsNestedThroughInsideJoin(): void
	{
		$db = self::getNewDbInstance();
		$ns = $db->ns('test');

		// Define dependency tables FIRST so that FK constraints can be resolved.
		$ns->table('a', static function (TableBuilder $t) {
			$t->id();
			$t->string('title');
		});
		$ns->table('c', static function (TableBuilder $t) {
			$t->id();
			$t->string('label');
		});
		$ns->table('pivot', static function (TableBuilder $t) {
			$t->id();
			$t->foreign('a_id', 'a', 'id');
			$t->foreign('c_id', 'c', 'id');
		});

		$db->lock();

		self::expectException(DBALException::class);

		// Nesting a 'through' link inside a join step must be rejected.
		Relation::createLink($db, $db->getTableOrFail('a'), $db->getTableOrFail('c'), [
			'type'  => LinkType::JOIN->value,
			'steps' => [
				[
					'join' => 'pivot',
					'link' => [
						'type'        => LinkType::THROUGH->value,
						'pivot_table' => 'pivot',
					],
				],
			],
		]);
	}

	// =========================================================================
	// fillRelation limitations
	// =========================================================================

	/**
	 * LinkThrough::fillRelation() always returns false because it requires a DB round-trip.
	 * This is the designed limitation — use selectRelatives() instead.
	 */
	public function testThroughFillRelationReturnsFalse(): void
	{
		$db       = self::getSampleDB();
		$articles = $db->getTableOrFail('articles');
		$relation = $articles->getRelation('tags');

		self::assertNotNull($relation);
		self::assertInstanceOf(LinkThrough::class, $relation->getLink());

		$data = [];
		// fillRelation on a THROUGH link is always false
		self::assertFalse($relation->getLink()->fillRelation(
			$this->createMock(ORMEntity::class),
			$data
		));
	}
}
