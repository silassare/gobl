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
use Gobl\DBAL\Queries\QBSelect;
use Gobl\DBAL\Relations\LinkColumns;
use Gobl\DBAL\Relations\LinkThrough;
use Gobl\Gobl;
use Gobl\ORM\Generators\CSGeneratorORM;
use Gobl\ORM\ORM;
use Gobl\ORM\ORMEntity;
use Gobl\Tests\BaseTestCase;
use Throwable;

/**
 * Class LinkBatchTest.
 *
 * Unit tests for {@see LinkColumns::applyBatch()} and
 * {@see LinkColumns::groupBatchResults()} (Feature 1).
 *
 * Also covers the full implementations in {@see LinkThrough} and the computed-value
 * slot on {@see ORMEntity}.
 *
 * Uses the TEST_DB_NAMESPACE schema (clients, accounts) which has generated
 * ORM classes, so ORM::entity() can create entity instances without a live DB.
 * Assertions are made against the generated SQL string for `applyBatch`, and
 * against the grouping map for `groupBatchResults`.
 *
 * @covers \Gobl\DBAL\Relations\LinkColumns::applyBatch
 * @covers \Gobl\DBAL\Relations\LinkColumns::groupBatchResults
 * @covers \Gobl\DBAL\Relations\LinkThrough::applyBatch
 * @covers \Gobl\DBAL\Relations\LinkThrough::groupBatchResults
 *
 * @internal
 */
final class LinkBatchTest extends BaseTestCase
{
	/** The sample DB namespace (articles/tags/taggables) used for morph-through tests. */
	private const SAMPLE_NS = 'test';

	/** @var bool Whether ORM setup completed successfully */
	private static bool $setupOk = false;

	/** @var null|callable spl autoloader for the sample schema `test\` namespace */
	private static mixed $sampleAutoloader = null;

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

		try {
			ORM::getDatabase(self::SAMPLE_NS);
			ORM::undeclareNamespace(self::SAMPLE_NS);
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

		// Also set up ORM for the sample schema (articles, tags, taggables) so that
		// ORM::entity() can instantiate entities for morph-through batch tests.
		$sampleDb     = self::getSampleDB(MySQL::NAME);
		$sampleOrmDir = GOBL_TEST_ORM_OUTPUT . \DIRECTORY_SEPARATOR . 'sample';

		if (!\is_dir($sampleOrmDir . \DIRECTORY_SEPARATOR . 'Base')) {
			\mkdir($sampleOrmDir . \DIRECTORY_SEPARATOR . 'Base', 0o755, true);
		}

		(new CSGeneratorORM($sampleDb))->generate($sampleDb->getTables(), $sampleOrmDir);

		// Register a PSR-like autoloader for the `test\` namespace so PHP can find
		// the generated entity classes without a composer dump-autoload cycle.
		self::$sampleAutoloader = static function (string $class) use ($sampleOrmDir): void {
			$prefix = self::SAMPLE_NS . '\\';

			if (\str_starts_with($class, $prefix)) {
				$rel  = \str_replace('\\', \DIRECTORY_SEPARATOR, \substr($class, \strlen($prefix)));
				$file = $sampleOrmDir . \DIRECTORY_SEPARATOR . $rel . '.php';

				if (\is_file($file)) {
					require_once $file;
				}
			}
		};

		\spl_autoload_register(self::$sampleAutoloader);

		ORM::declareNamespace(self::SAMPLE_NS, $sampleDb, $sampleOrmDir);

		self::$setupOk = true;
	}

	public static function tearDownAfterClass(): void
	{
		try {
			ORM::undeclareNamespace(self::TEST_DB_NAMESPACE);
		} catch (Throwable) {
			// already undeclared
		}

		try {
			ORM::undeclareNamespace(self::SAMPLE_NS);
		} catch (Throwable) {
			// already undeclared
		}

		if (null !== self::$sampleAutoloader) {
			\spl_autoload_unregister(self::$sampleAutoloader);
			self::$sampleAutoloader = null;
		}

		self::$setupOk = false;

		parent::tearDownAfterClass();
	}

	protected function setUp(): void
	{
		parent::setUp();

		if (!self::$setupOk) {
			self::markTestSkipped('ORM entity test setup failed.');
		}
	}

	// ---------------------------------------------------------------------------
	// LinkColumns - applyBatch
	// ---------------------------------------------------------------------------

	/**
	 * applyBatch() for many-to-one accounts.client (account_client_id -> client_id)
	 * must add `client_id IN (...)` to the WHERE clause of the target query.
	 */
	public function testLinkColumnsApplyBatchManyToOne(): void
	{
		$db       = ORM::getDatabase(self::TEST_DB_NAMESPACE);
		$accounts = $db->getTableOrFail('accounts');
		$clients  = $db->getTableOrFail('clients');
		$link     = $accounts->getRelation('client')->getLink();

		self::assertInstanceOf(LinkColumns::class, $link);

		$acct1 = ORM::entity($accounts, false, false);
		$acct1->hydrate(['account_id' => 1, 'account_client_id' => 10]);

		$acct2 = ORM::entity($accounts, false, false);
		$acct2->hydrate(['account_id' => 2, 'account_client_id' => 20]);

		$qb = new QBSelect($db);
		$qb->from($clients->getFullName(), 'c');

		$applied = $link->applyBatch($qb, [$acct1, $acct2]);

		self::assertTrue($applied, 'applyBatch() must return true for a single-FK column link');

		$sql = $qb->getSqlQuery();
		self::assertStringContainsString('IN', $sql, 'Generated SQL must use IN clause');
		self::assertStringContainsString('client_id', $sql, 'Target column client_id must appear in SQL');
	}

	/**
	 * applyBatch() for one-to-many clients.accounts (client_id -> account_client_id)
	 * must add `account_client_id IN (...)` to the WHERE clause.
	 */
	public function testLinkColumnsApplyBatchOneToMany(): void
	{
		$db       = ORM::getDatabase(self::TEST_DB_NAMESPACE);
		$clients  = $db->getTableOrFail('clients');
		$accounts = $db->getTableOrFail('accounts');
		$link     = $clients->getRelation('accounts')->getLink();

		self::assertInstanceOf(LinkColumns::class, $link);

		$client1 = ORM::entity($clients, false, false);
		$client1->hydrate(['client_id' => 1]);

		$client2 = ORM::entity($clients, false, false);
		$client2->hydrate(['client_id' => 2]);

		$qb = new QBSelect($db);
		$qb->from($accounts->getFullName(), 'a');

		$applied = $link->applyBatch($qb, [$client1, $client2]);

		self::assertTrue($applied, 'applyBatch() must return true for one-to-many link');

		$sql = $qb->getSqlQuery();
		self::assertStringContainsString('IN', $sql);
		self::assertStringContainsString('account_client_id', $sql);
	}

	/**
	 * applyBatch() must return false when the host entity has FK value 0 and the
	 * column maps 0 -- this verifies we don't short-circuit on "falsy" values.
	 * Actually, since bigint columns coerce null -> 0, the only way to get an empty
	 * values list is an empty host array; this test just documents the FK-value
	 * is non-null (0) and applyBatch succeeds.
	 */
	public function testLinkColumnsApplyBatchWithZeroFKValueSucceeds(): void
	{
		$db       = ORM::getDatabase(self::TEST_DB_NAMESPACE);
		$accounts = $db->getTableOrFail('accounts');
		$clients  = $db->getTableOrFail('clients');
		$link     = $accounts->getRelation('client')->getLink();

		// account_client_id not provided -> coerced to 0 by bigint type
		$acct = ORM::entity($accounts, false, false);
		$acct->hydrate(['account_id' => 1]);

		$qb = new QBSelect($db);
		$qb->from($clients->getFullName(), 'c');

		// 0 is treated as a valid value (not null), so applyBatch returns true
		self::assertTrue(
			$link->applyBatch($qb, [$acct]),
			'applyBatch() must return true when FK value is 0 (coerced non-null bigint)'
		);
	}

	public function testLinkColumnsApplyBatchReturnsFalseOnEmptyHostList(): void
	{
		$db       = ORM::getDatabase(self::TEST_DB_NAMESPACE);
		$accounts = $db->getTableOrFail('accounts');
		$clients  = $db->getTableOrFail('clients');
		$link     = $accounts->getRelation('client')->getLink();

		$qb = new QBSelect($db);
		$qb->from($clients->getFullName(), 'c');

		self::assertFalse(
			$link->applyBatch($qb, []),
			'applyBatch() must return false for an empty host entity list'
		);
	}

	// ---------------------------------------------------------------------------
	// LinkColumns - groupBatchResults
	// ---------------------------------------------------------------------------

	/**
	 * For clients.accounts (one-to-many: client_id -> account_client_id)
	 * groupBatchResults must build a map host_pk -> [account, ...].
	 */
	public function testLinkColumnsGroupBatchResultsOneToMany(): void
	{
		$db       = ORM::getDatabase(self::TEST_DB_NAMESPACE);
		$clients  = $db->getTableOrFail('clients');
		$accounts = $db->getTableOrFail('accounts');
		$link     = $clients->getRelation('accounts')->getLink();

		$client1 = ORM::entity($clients, false, false);
		$client1->hydrate(['client_id' => 1]);
		$client2 = ORM::entity($clients, false, false);
		$client2->hydrate(['client_id' => 2]);

		$acct1 = ORM::entity($accounts, false, false);
		$acct1->hydrate(['account_id' => 10, 'account_client_id' => 1]);
		$acct2 = ORM::entity($accounts, false, false);
		$acct2->hydrate(['account_id' => 11, 'account_client_id' => 1]);
		$acct3 = ORM::entity($accounts, false, false);
		$acct3->hydrate(['account_id' => 20, 'account_client_id' => 2]);

		$grouped = $link->groupBatchResults([$client1, $client2], [$acct1, $acct2, $acct3]);

		self::assertArrayHasKey('1', $grouped, 'Client 1 must be in the group map');
		self::assertArrayHasKey('2', $grouped, 'Client 2 must be in the group map');
		self::assertCount(2, $grouped['1'], 'Client 1 must have 2 accounts');
		self::assertCount(1, $grouped['2'], 'Client 2 must have 1 account');
	}

	/**
	 * For accounts.client (many-to-one: account_client_id -> client_id):
	 * multiple accounts may share the same client_id; both must map to the same result.
	 */
	public function testLinkColumnsGroupBatchResultsManyToOne(): void
	{
		$db       = ORM::getDatabase(self::TEST_DB_NAMESPACE);
		$accounts = $db->getTableOrFail('accounts');
		$clients  = $db->getTableOrFail('clients');
		$link     = $accounts->getRelation('client')->getLink();

		// Two accounts sharing the same client
		$acct1 = ORM::entity($accounts, false, false);
		$acct1->hydrate(['account_id' => 10, 'account_client_id' => 1]);
		$acct2 = ORM::entity($accounts, false, false);
		$acct2->hydrate(['account_id' => 11, 'account_client_id' => 1]);

		$client1 = ORM::entity($clients, false, false);
		$client1->hydrate(['client_id' => 1]);

		$grouped = $link->groupBatchResults([$acct1, $acct2], [$client1]);

		self::assertArrayHasKey('10', $grouped, 'Account 10 must be in the group map');
		self::assertArrayHasKey('11', $grouped, 'Account 11 must be in the group map');
		self::assertCount(1, $grouped['10'], 'Account 10 must have its client');
		self::assertCount(1, $grouped['11'], 'Account 11 must also have its client (shared FK)');
		self::assertSame('1', (string) $grouped['10'][0]->client_id);
	}

	public function testLinkColumnsGroupBatchResultsEmptyResultsReturnsEmptyMap(): void
	{
		$db      = ORM::getDatabase(self::TEST_DB_NAMESPACE);
		$clients = $db->getTableOrFail('clients');
		$link    = $clients->getRelation('accounts')->getLink();

		$client1 = ORM::entity($clients, false, false);
		$client1->hydrate(['client_id' => 1]);

		$grouped = $link->groupBatchResults([$client1], []);

		self::assertEmpty($grouped, 'No results must produce an empty map');
	}

	public function testLinkColumnsGroupBatchResultsEmptyHostsReturnsEmptyMap(): void
	{
		$db       = ORM::getDatabase(self::TEST_DB_NAMESPACE);
		$accounts = $db->getTableOrFail('accounts');
		$link     = $accounts->getRelation('client')->getLink();

		$acct = ORM::entity($accounts, false, false);
		$acct->hydrate(['account_id' => 1, 'account_client_id' => 1]);

		$grouped = $link->groupBatchResults([], [$acct]);

		self::assertEmpty($grouped, 'No hosts must produce an empty map');
	}

	// ---------------------------------------------------------------------------
	// LinkThrough - applyBatch (articles.tags via morph-through)
	// ---------------------------------------------------------------------------

	/**
	 * applyBatch() with an empty host list must return false (nothing to filter on).
	 */
	public function testLinkThroughApplyBatchReturnsFalseOnEmptyHostList(): void
	{
		$db   = ORM::getDatabase(self::SAMPLE_NS);
		$arts = $db->getTableOrFail('articles');
		$rel  = $arts->getRelation('tags');
		$link = $rel->getLink();

		self::assertInstanceOf(
			LinkThrough::class,
			$link,
			'articles.tags via morph-through must use LinkThrough'
		);

		$tags = $db->getTableOrFail('tags');
		$qb   = new QBSelect($db);
		$qb->from($tags->getFullName(), 'tg');

		self::assertFalse(
			$link->applyBatch($qb, []),
			'LinkThrough::applyBatch must return false for an empty host entity list'
		);
	}

	/**
	 * applyBatch() with actual host entities must return true and produce SQL containing
	 * the pivot table join and the IN condition on the morph-key column.
	 */
	public function testLinkThroughApplyBatchReturnsTrueWithHosts(): void
	{
		$db        = ORM::getDatabase(self::SAMPLE_NS);
		$arts      = $db->getTableOrFail('articles');
		$tags      = $db->getTableOrFail('tags');
		$taggables = $db->getTableOrFail('taggables');
		$link      = $arts->getRelation('tags')->getLink();

		self::assertInstanceOf(LinkThrough::class, $link);

		$art1 = ORM::entity($arts, false, false);
		$art1->hydrate(['id' => 1]);

		$art2 = ORM::entity($arts, false, false);
		$art2->hydrate(['id' => 2]);

		$qb = new QBSelect($db);
		$qb->from($tags->getFullName(), 'tg');

		$applied = $link->applyBatch($qb, [$art1, $art2]);

		self::assertTrue($applied, 'applyBatch() must return true when host entities are provided');

		$sql = $qb->getSqlQuery();

		// The pivot table (taggables) must be joined.
		self::assertStringContainsString($taggables->getFullName(), $sql, 'Pivot JOIN must appear in SQL');
		// The IN clause must appear.
		self::assertStringContainsString('IN', $sql, 'IN clause must appear in SQL');
		// The computed batch_key slot must be present.
		self::assertStringContainsString(QBSelect::computedAlias('batch_key'), $sql, 'Computed batch_key alias must appear in SQL');
	}

	/**
	 * groupBatchResults() for an empty result set must return an empty map.
	 */
	public function testLinkThroughGroupBatchResultsReturnsEmptyForEmptyResults(): void
	{
		$db   = ORM::getDatabase(self::SAMPLE_NS);
		$arts = $db->getTableOrFail('articles');
		$link = $arts->getRelation('tags')->getLink();

		$art = ORM::entity($arts, false, false);
		$art->hydrate(['id' => 1]);

		self::assertSame([], $link->groupBatchResults([$art], []));
	}

	/**
	 * groupBatchResults() must route target entities back to their host using the
	 * `_gobl_batch_key` computed value we simulate by calling hydrate() with it.
	 */
	public function testLinkThroughGroupBatchResultsRoutesCorrectly(): void
	{
		$db   = ORM::getDatabase(self::SAMPLE_NS);
		$arts = $db->getTableOrFail('articles');
		$tags = $db->getTableOrFail('tags');
		$link = $arts->getRelation('tags')->getLink();

		self::assertInstanceOf(LinkThrough::class, $link);

		$art1 = ORM::entity($arts, false, false);
		$art1->hydrate(['id' => 10]);

		$art2 = ORM::entity($arts, false, false);
		$art2->hydrate(['id' => 20]);

		// Simulate PDO hydration: each tag carries `_gobl_batch_key = article.id of its owner`
		$tag1 = ORM::entity($tags, false, false);
		$tag1->hydrate(['id' => 1, '_gobl_batch_key' => 10]);

		$tag2 = ORM::entity($tags, false, false);
		$tag2->hydrate(['id' => 2, '_gobl_batch_key' => 10]);

		$tag3 = ORM::entity($tags, false, false);
		$tag3->hydrate(['id' => 3, '_gobl_batch_key' => 20]);

		$grouped = $link->groupBatchResults([$art1, $art2], [$tag1, $tag2, $tag3]);

		self::assertArrayHasKey('10', $grouped, 'Article 10 must be in the map');
		self::assertArrayHasKey('20', $grouped, 'Article 20 must be in the map');
		self::assertCount(2, $grouped['10'], 'Article 10 must have 2 tags');
		self::assertCount(1, $grouped['20'], 'Article 20 must have 1 tag');
	}

	// ---------------------------------------------------------------------------
	// ORMEntity - computed value slot
	// ---------------------------------------------------------------------------

	/**
	 * Setting a `_gobl_*` property during hydrate() must store it in the computed bag.
	 */
	public function testEntityComputedValueIsStoredViaHydrate(): void
	{
		$db       = ORM::getDatabase(self::TEST_DB_NAMESPACE);
		$accounts = $db->getTableOrFail('accounts');

		$entity = ORM::entity($accounts, false, false);
		$entity->hydrate(['account_id' => 1, '_gobl_batch_key' => 42]);

		self::assertTrue($entity->hasComputedValue('batch_key'), 'hasComputedValue must return true after hydrate');
		self::assertSame(42, $entity->getComputedValue('batch_key'), 'getComputedValue must return the injected value');
	}

	/**
	 * getComputedValue() must return null when the key is absent.
	 */
	public function testEntityGetComputedValueReturnsNullWhenAbsent(): void
	{
		$db       = ORM::getDatabase(self::TEST_DB_NAMESPACE);
		$accounts = $db->getTableOrFail('accounts');

		$entity = ORM::entity($accounts, false, false);
		$entity->hydrate(['account_id' => 1]);

		self::assertFalse($entity->hasComputedValue('batch_key'));
		self::assertNull($entity->getComputedValue('batch_key'));
	}

	/**
	 * A `_gobl_*` assignment must NOT dirty the entity or affect save().
	 */
	public function testEntityComputedValueDoesNotDirtyEntity(): void
	{
		$db       = ORM::getDatabase(self::TEST_DB_NAMESPACE);
		$accounts = $db->getTableOrFail('accounts');

		$entity = ORM::entity($accounts, false, false);
		$entity->hydrate(['account_id' => 1, 'account_client_id' => 5]);
		$entity->isSaved(true); // snapshot so it's clean

		// Inject a computed slot - must not change isSaved state
		$entity->account_id = 1; // re-assign same value

		/** @phpstan-ignore-next-line */
		$entity->{'_gobl_batch_key'} = 99; // direct __set

		self::assertTrue(
			$entity->isSaved(),
			'Computed value assignment must not dirty the entity'
		);
	}

	// ---------------------------------------------------------------------------
	// QBSelect - computedAlias / selectComputed
	// ---------------------------------------------------------------------------

	public function testQBSelectComputedAlias(): void
	{
		self::assertSame('_gobl_foo', QBSelect::computedAlias('foo'));
		self::assertSame('_gobl_batch_key', QBSelect::computedAlias('batch_key'));
	}

	public function testQBSelectSelectComputedAppendsToSelectClause(): void
	{
		$db = ORM::getDatabase(self::TEST_DB_NAMESPACE);

		$qb = new QBSelect($db);
		$qb->from('gobl_accounts', 'a');
		$qb->selectComputed('a.account_client_id', 'routing_key');

		$sql = $qb->getSqlQuery();

		self::assertStringContainsString('_gobl_routing_key', $sql, 'selectComputed must inject the alias into SELECT');
	}

	// ---------------------------------------------------------------------------
	// Gobl::isAllowedColumnName
	// ---------------------------------------------------------------------------

	public function testComputedValueIsForbiddenColumnName(): void
	{
		self::assertFalse(
			Gobl::isAllowedColumnName('computed_value'),
			'"computed_value" must be in the forbidden column names list'
		);
	}

	// ---------------------------------------------------------------------------
	// LinkMorph - applyBatch (host is child: taggables.tag morph)
	// ---------------------------------------------------------------------------

	/**
	 * taggables.tag is a plain FK (tag_id -> tags.id) so it uses LinkColumns.
	 * applyBatch on an empty host list must return false.
	 */
	public function testLinkColumnsApplyBatchForTaggablesTagReturnsFalseOnEmpty(): void
	{
		$db        = ORM::getDatabase(self::SAMPLE_NS);
		$taggables = $db->getTableOrFail('taggables');
		$tags      = $db->getTableOrFail('tags');
		$link      = $taggables->getRelation('tag')->getLink();

		self::assertInstanceOf(
			LinkColumns::class,
			$link,
			'taggables.tag (plain FK) must use LinkColumns'
		);

		$qb = new QBSelect($db);
		$qb->from($tags->getFullName(), 'tg');

		self::assertFalse(
			$link->applyBatch($qb, []),
			'applyBatch() must return false for empty host list'
		);
	}
}
