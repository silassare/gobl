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
use Gobl\Gobl;
use Gobl\ORM\Generators\CSGeneratorORM;
use Gobl\ORM\ORM;
use Gobl\ORM\ORMEntity;
use Gobl\ORM\ORMTableQuery;
use Gobl\Tests\BaseTestCase;
use Throwable;

/**
 * Class LinkBatchTest.
 *
 * Unit tests for {@see ORMTableQuery::selectRelativesBatch()} and the computed-value
 * slot on {@see ORMEntity}.
 *
 * Uses the TEST_DB_NAMESPACE schema (clients, accounts) and the sample schema
 * (articles, tags, taggables) which have generated ORM classes, so ORM::entity()
 * can create entity instances without a live DB.
 * Assertions are made against the generated SQL string.
 *
 * @covers \Gobl\ORM\ORMTableQuery::selectRelativesBatch
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
	// ORMTableQuery::selectRelativesBatch - SQL-level tests
	// ---------------------------------------------------------------------------

	/**
	 * selectRelativesBatch() must return null when the host entity list is empty.
	 */
	public function testSelectRelativesBatchReturnsNullForEmptyHosts(): void
	{
		$db       = ORM::getDatabase(self::TEST_DB_NAMESPACE);
		$accounts = $db->getTableOrFail('accounts');
		$clients  = $db->getTableOrFail('clients');

		// many-to-one: accounts.client (host=accounts, target=clients)
		$relation = $accounts->getRelation('client');
		$qb       = ORM::query($clients);
		self::assertNull($qb->selectRelativesBatch($relation, []));

		// one-to-many: clients.accounts (host=clients, target=accounts)
		$relation = $clients->getRelation('accounts');
		$qb       = ORM::query($accounts);
		self::assertNull($qb->selectRelativesBatch($relation, []));
	}

	/**
	 * selectRelativesBatch() for many-to-one accounts.client must JOIN the host table
	 * and add an IN clause on the host PK column.
	 */
	public function testSelectRelativesBatchManyToOne(): void
	{
		$db       = ORM::getDatabase(self::TEST_DB_NAMESPACE);
		$accounts = $db->getTableOrFail('accounts');
		$clients  = $db->getTableOrFail('clients');

		$relation = $accounts->getRelation('client'); // host=accounts, target=clients

		$acct1 = ORM::entity($accounts, false, false);
		$acct1->hydrate(['account_id' => 1, 'account_client_id' => 10]);
		$acct1->isSaved(true);

		$acct2 = ORM::entity($accounts, false, false);
		$acct2->hydrate(['account_id' => 2, 'account_client_id' => 20]);
		$acct2->isSaved(true);

		$qb  = ORM::query($clients);
		$sel = $qb->selectRelativesBatch($relation, [$acct1, $acct2]);

		self::assertNotNull($sel, 'selectRelativesBatch() must return a QBSelect for a non-empty host list');

		$sql = $sel->getSqlQuery();

		self::assertStringContainsString('IN', $sql, 'SQL must use IN clause for the host PK values');
		self::assertStringContainsString('account_id', $sql, 'Host PK (account_id) must appear in SQL');
		self::assertStringContainsString(
			QBSelect::computedAlias(ORMTableQuery::BATCH_HOST_IDENTITY_KEY),
			$sql,
			'Computed batch routing alias must appear in SQL'
		);
	}

	/**
	 * selectRelativesBatch() for one-to-many clients.accounts must JOIN the host table
	 * and add an IN clause on the host PK column.
	 */
	public function testSelectRelativesBatchOneToMany(): void
	{
		$db       = ORM::getDatabase(self::TEST_DB_NAMESPACE);
		$clients  = $db->getTableOrFail('clients');
		$accounts = $db->getTableOrFail('accounts');

		$relation = $clients->getRelation('accounts'); // host=clients, target=accounts

		$client1 = ORM::entity($clients, false, false);
		$client1->hydrate(['client_id' => 1]);
		$client1->isSaved(true);

		$client2 = ORM::entity($clients, false, false);
		$client2->hydrate(['client_id' => 2]);
		$client2->isSaved(true);

		$qb  = ORM::query($accounts);
		$sel = $qb->selectRelativesBatch($relation, [$client1, $client2]);

		self::assertNotNull($sel);

		$sql = $sel->getSqlQuery();

		self::assertStringContainsString('IN', $sql);
		self::assertStringContainsString('client_id', $sql, 'Host PK (client_id) must appear in SQL');
		self::assertStringContainsString(
			QBSelect::computedAlias(ORMTableQuery::BATCH_HOST_IDENTITY_KEY),
			$sql
		);
	}

	/**
	 * A host entity with FK value 0 (coerced non-null bigint) must be handled correctly.
	 */
	public function testSelectRelativesBatchWithZeroFKValueSucceeds(): void
	{
		$db       = ORM::getDatabase(self::TEST_DB_NAMESPACE);
		$accounts = $db->getTableOrFail('accounts');
		$clients  = $db->getTableOrFail('clients');

		$relation = $accounts->getRelation('client');

		// account_client_id not provided -> coerced to 0 by bigint type; account_id = 1
		$acct = ORM::entity($accounts, false, false);
		$acct->hydrate(['account_id' => 1]);
		$acct->isSaved(true);

		$qb  = ORM::query($clients);
		$sel = $qb->selectRelativesBatch($relation, [$acct]);

		self::assertNotNull($sel, 'selectRelativesBatch() must succeed when FK value is 0 (coerced non-null bigint)');
	}

	// ---------------------------------------------------------------------------
	// ORMTableQuery::selectRelativesBatch - LinkThrough (articles.tags via morph-through)
	// ---------------------------------------------------------------------------

	/**
	 * selectRelativesBatch() must return null for an empty host entity list (LinkThrough).
	 */
	public function testSelectRelativesBatchForLinkThroughReturnsNullForEmptyHosts(): void
	{
		$db       = ORM::getDatabase(self::SAMPLE_NS);
		$articles = $db->getTableOrFail('articles');
		$tags     = $db->getTableOrFail('tags');
		$relation = $articles->getRelation('tags'); // host=articles, target=tags

		$tq = ORM::query($tags);
		self::assertNull($tq->selectRelativesBatch($relation, []));
	}

	/**
	 * selectRelativesBatch() for articles.tags (LinkThrough via taggables) must JOIN the
	 * pivot table and add an IN clause with the computed batch routing alias.
	 */
	public function testSelectRelativesBatchForLinkThroughWithHosts(): void
	{
		$db        = ORM::getDatabase(self::SAMPLE_NS);
		$articles  = $db->getTableOrFail('articles');
		$tags      = $db->getTableOrFail('tags');
		$taggables = $db->getTableOrFail('taggables');
		$relation  = $articles->getRelation('tags');

		$art1 = ORM::entity($articles, false, false);
		$art1->hydrate(['id' => 1]);
		$art1->isSaved(true);

		$art2 = ORM::entity($articles, false, false);
		$art2->hydrate(['id' => 2]);
		$art2->isSaved(true);

		$qb  = ORM::query($tags);
		$sel = $qb->selectRelativesBatch($relation, [$art1, $art2]);

		self::assertNotNull($sel, 'selectRelativesBatch() must return a QBSelect for non-empty host list');

		$sql = $sel->getSqlQuery();

		self::assertStringContainsString($taggables->getFullName(), $sql, 'Pivot table (taggables) must appear in SQL');
		self::assertStringContainsString('IN', $sql, 'IN clause must appear in SQL');
		self::assertStringContainsString(
			QBSelect::computedAlias(ORMTableQuery::BATCH_HOST_IDENTITY_KEY),
			$sql,
			'Computed batch routing alias must appear in SQL'
		);
	}

	/**
	 * The computed batch routing key injected by selectRelativesBatch() is readable via
	 * getComputedValue(ORMTableQuery::BATCH_HOST_IDENTITY_KEY) after PDO hydration.
	 */
	public function testSelectRelativesBatchRoutingKeyIsReadableViaGetComputedValue(): void
	{
		$db       = ORM::getDatabase(self::TEST_DB_NAMESPACE);
		$accounts = $db->getTableOrFail('accounts');

		// Simulate PDO hydration: the batch key is injected as _gobl_batch_host_identity_key.
		$entity = ORM::entity($accounts, false, false);
		$entity->hydrate([
			'account_id'                                        => 10,
			'_gobl_' . ORMTableQuery::BATCH_HOST_IDENTITY_KEY   => 5,
		]);

		self::assertTrue($entity->hasComputedValue(ORMTableQuery::BATCH_HOST_IDENTITY_KEY));
		self::assertSame(5, $entity->getComputedValue(ORMTableQuery::BATCH_HOST_IDENTITY_KEY));
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
		$qb->selectComputed('routing_key', 'a.account_client_id');

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
	// ORMTableQuery::selectRelativesBatch - taggables.tag (LinkColumns, SAMPLE_NS)
	// ---------------------------------------------------------------------------

	/**
	 * taggables.tag is a plain FK (tag_id -> tags.id) so it uses LinkColumns.
	 * selectRelativesBatch() must return null for an empty host list.
	 */
	public function testSelectRelativesBatchForTaggablesTagReturnsNullForEmptyHosts(): void
	{
		$db        = ORM::getDatabase(self::SAMPLE_NS);
		$taggables = $db->getTableOrFail('taggables');
		$tags      = $db->getTableOrFail('tags');
		$relation  = $taggables->getRelation('tag'); // host=taggables, target=tags

		self::assertInstanceOf(
			LinkColumns::class,
			$relation->getLink(),
			'taggables.tag (plain FK) must use LinkColumns'
		);

		$tq = ORM::query($tags);
		self::assertNull($tq->selectRelativesBatch($relation, []));
	}
}
