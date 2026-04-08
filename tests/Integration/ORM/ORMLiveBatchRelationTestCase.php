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

namespace Gobl\Tests\Integration\ORM;

use Gobl\DBAL\Builders\TableBuilder;
use Gobl\DBAL\Drivers\MySQL\MySQL;
use Gobl\DBAL\Drivers\PostgreSQL\PostgreSQL;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\ORM\Generators\CSGeneratorORM;
use Gobl\ORM\ORM;
use Gobl\ORM\ORMEntity;
use Gobl\Tests\BaseTestCase;
use Throwable;

/**
 * Class ORMLiveBatchRelationTestCase.
 *
 * Abstract base for integration tests covering all four link types
 * (LinkColumns, LinkThrough, LinkMorph, LinkJoin) using real DB queries.
 *
 * Schema used:
 *   bat_authors       (id, name)
 *   bat_genres        (id, name)
 *   bat_books         (id, title, author_id FK->bat_authors, soft-deletable)
 *   bat_book_genres   pivot (id, book_id FK->bat_books, genre_id FK->bat_genres)
 *   bat_author_genres pivot (id, author_id FK->bat_authors, genre_id FK->bat_genres)
 *   bat_reviews       morph child (id, body, reviewable_id, reviewable_type)
 *
 * Relations:
 *   bat_books.author        many-to-one  LinkColumns
 *   bat_authors.books       one-to-many  LinkColumns
 *   bat_books.genres        one-to-many  LinkThrough  (via bat_book_genres)
 *   bat_books.reviews       one-to-many  LinkMorph    (bat_books is morph parent)
 *   bat_authors.genres_direct  one-to-many  LinkJoin  (authors->bat_author_genres->genres, 1 step)
 *
 * Fixture:
 *   Authors:  Alice, Bob
 *   Books:    PHP Manual (Alice), SQL Guide (Alice), Linux Primer (Bob)
 *   Genres:   Programming, Databases
 *   Book-Genre pivot:   PHP Manual->Programming, SQL Guide->Databases, Linux Primer->Programming
 *   Author-Genre pivot: Alice->Programming, Alice->Databases, Bob->Programming
 *   Reviews:  Excellent! (->SQL Guide), Very helpful! (->Linux Primer), Comprehensive! (->Linux Primer)
 *
 * @internal
 *
 * @coversNothing
 */
abstract class ORMLiveBatchRelationTestCase extends BaseTestCase
{
	protected const BATCH_REL_DB_NAMESPACE = 'Gobl\Tests\BatchRelDb';

	/** @var null|RDBMSInterface Shared live-DB connection for all tests in the class */
	protected static ?RDBMSInterface $db = null;

	/** @var bool Indicates that setUpBeforeClass failed (e.g. missing env credentials) */
	protected static bool $setupFailed = false;

	/** @var array<string, ORMEntity> Inserted fixture entities keyed by a short name */
	protected static array $fixture = [];

	/** @var null|callable Registered PSR-4 autoloader for generated BatchRel classes */
	private static mixed $autoloader = null;

	// -------------------------------------------------------------------------
	// PHPUnit lifecycle
	// -------------------------------------------------------------------------

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		static::$setupFailed = false;
		static::$fixture     = [];

		// Guard: undeclare any leftover namespace from a previous run.
		try {
			ORM::getDatabase(self::BATCH_REL_DB_NAMESPACE);
			ORM::undeclareNamespace(self::BATCH_REL_DB_NAMESPACE);
		} catch (Throwable) {
			// not declared yet, expected
		}

		try {
			$db = static::getNewDbInstance(static::getDriverName());
		} catch (Throwable $t) {
			static::$setupFailed = true;
			gobl_log(\sprintf('BatchRel: error building live DB for %s: %s', static::getDriverName(), $t->getMessage()));

			return;
		}

		if (null === $db) {
			static::$setupFailed = true;
			gobl_log(\sprintf('BatchRel: live DB not configured for %s (check env vars).', static::getDriverName()));

			return;
		}

		$ormOutDir = self::getOrmOutDir();

		try {
			// Ensure the output directory and its Base/ sub-directory exist.
			if (!\is_dir($ormOutDir . \DIRECTORY_SEPARATOR . 'Base')) {
				\mkdir($ormOutDir . \DIRECTORY_SEPARATOR . 'Base', 0o755, true);
			}

			// Register a PSR-4 autoloader for the generated BatchRel entity classes.
			$prefix           = self::BATCH_REL_DB_NAMESPACE . '\\';
			self::$autoloader = static function (string $class) use ($ormOutDir, $prefix): void {
				if (!\str_starts_with($class, $prefix)) {
					return;
				}
				$rel  = \str_replace('\\', \DIRECTORY_SEPARATOR, \substr($class, \strlen($prefix)));
				$file = $ormOutDir . \DIRECTORY_SEPARATOR . $rel . '.php';

				if (\is_file($file)) {
					require_once $file;
				}
			};
			\spl_autoload_register(self::$autoloader);

			// Build the test schema.
			$ns = $db->ns(self::BATCH_REL_DB_NAMESPACE);

			$authorsBuilder = $ns->table('bat_authors', static function (TableBuilder $t): void {
				$t->plural('bat_authors')->singular('bat_author');
				$t->id();
				$t->string('name');
			});

			$ns->table('bat_genres', static function (TableBuilder $t): void {
				$t->plural('bat_genres')->singular('bat_genre');
				$t->id();
				$t->string('name');
			});

			$booksBuilder = $ns->table('bat_books', static function (TableBuilder $t): void {
				$t->plural('bat_books')->singular('bat_book');
				$t->id();
				$t->string('title');
				$t->foreign('author_id', 'bat_authors', 'id');
				// soft-deletable is required by LinkMorph (bat_books is the morph parent)
				$t->softDeletable();
			});

			$ns->table('bat_book_genres', static function (TableBuilder $t): void {
				$t->plural('bat_book_genres')->singular('bat_book_genre');
				$t->id();
				$t->foreign('book_id', 'bat_books', 'id');
				$t->foreign('genre_id', 'bat_genres', 'id');
			});

			$ns->table('bat_author_genres', static function (TableBuilder $t): void {
				$t->plural('bat_author_genres')->singular('bat_author_genre');
				$t->id();
				$t->foreign('author_id', 'bat_authors', 'id');
				$t->foreign('genre_id', 'bat_genres', 'id');
			});

			$ns->table('bat_reviews', static function (TableBuilder $t): void {
				$t->plural('bat_reviews')->singular('bat_review');
				$t->id();
				$t->string('body');
				$t->morph('reviewable');
			});

			// Add relations that require tables defined later in the sequence.
			$booksBuilder->factory(static function (TableBuilder $t): void {
				$t->belongsTo('author')->from('bat_authors');
				$t->hasMany('genres')->from('bat_genres')->through('bat_book_genres');
				$t->hasMany('reviews')->from('bat_reviews')->usingMorph('reviewable');
			});

			$authorsBuilder->factory(static function (TableBuilder $t): void {
				$t->hasMany('books')->from('bat_books');
				// LinkJoin via a single pivot table (2-hop: authors->bat_author_genres->genres).
				$t->hasMany('genres_direct')
					->from('bat_genres')
					->usingJoin(['steps' => [
						['join' => 'bat_author_genres', 'link' => ['type' => 'columns']],
					]]);
			});

			// Register namespace with ORM and generate entity/controller/query classes.
			$ns->enableORM($ormOutDir);
			(new CSGeneratorORM($db))->generate($db->getTables(self::BATCH_REL_DB_NAMESPACE), $ormOutDir);

			// Lock schema and create all tables in the live database.
			$db->lock();
			$db->executeMulti($db->getGenerator()->buildDatabase());

			static::$db = $db;

			// Insert the shared fixture data used by all read-only tests.
			static::insertFixture($db);
		} catch (Throwable $t) {
			static::$setupFailed = true;
			gobl_log(\sprintf('BatchRel: setup failed for %s: %s', static::getDriverName(), $t->getMessage()));
		}
	}

	public static function tearDownAfterClass(): void
	{
		if (null !== static::$db) {
			try {
				static::$db->executeMulti(static::buildDropAllSql(static::$db));
			} catch (Throwable) {
				// best-effort: ignore drop errors
			}

			try {
				ORM::undeclareNamespace(self::BATCH_REL_DB_NAMESPACE);
			} catch (Throwable) {
				// already undeclared or was never fully declared
			}

			static::$db = null;
		}

		static::$fixture = [];

		if (null !== self::$autoloader) {
			\spl_autoload_unregister(self::$autoloader);
			self::$autoloader = null;
		}

		parent::tearDownAfterClass();
	}

	protected function setUp(): void
	{
		parent::setUp();

		if (static::$setupFailed || null === static::$db) {
			self::markTestSkipped(
				\sprintf('%s live database is not available for batch-relation tests.', static::getDriverName())
			);
		}
	}

	// -------------------------------------------------------------------------
	// Tests -- LinkColumns
	// -------------------------------------------------------------------------

	/**
	 * LinkColumns (many-to-one): getRelativeBatch fetches one author per book.
	 *
	 * Host: bat_books, Target: bat_authors, Relation: books.author
	 */
	public function testLinkColumnsGetRelativeBatch(): void
	{
		$db          = static::$db;
		$authorsCtrl = ORM::ctrl($db->getTableOrFail('bat_authors'));
		$booksTable  = $db->getTableOrFail('bat_books');
		$relation    = $booksTable->getRelation('author');

		/** @var ORMEntity[] $books */
		$books = [
			static::$fixture['php_manual'],
			static::$fixture['sql_guide'],
			static::$fixture['linux_primer'],
		];

		$map = $authorsCtrl->getRelativeBatch($books, $relation);

		self::assertCount(\count($books), $map, 'getRelativeBatch must return one entry per host');

		$phpManual   = static::$fixture['php_manual'];
		$sqlGuide    = static::$fixture['sql_guide'];
		$linuxPrimer = static::$fixture['linux_primer'];
		$alice       = static::$fixture['alice'];
		$bob         = static::$fixture['bob'];
		$aliceId     = $alice->toIdentityKey();
		$bobId       = $bob->toIdentityKey();

		// PHP Manual and SQL Guide both belong to Alice.
		self::assertNotNull($map[$phpManual->toIdentityKey()], 'PHP Manual must have an author');
		self::assertSame($aliceId, $map[$phpManual->toIdentityKey()]->toIdentityKey());

		self::assertNotNull($map[$sqlGuide->toIdentityKey()], 'SQL Guide must have an author');
		self::assertSame($aliceId, $map[$sqlGuide->toIdentityKey()]->toIdentityKey());

		// Linux Primer belongs to Bob (different target from Alice's books).
		self::assertNotNull($map[$linuxPrimer->toIdentityKey()], 'Linux Primer must have an author');
		self::assertSame($bobId, $map[$linuxPrimer->toIdentityKey()]->toIdentityKey());
	}

	/**
	 * LinkColumns (one-to-many): getAllRelativesBatch fetches all books per author.
	 *
	 * Host: bat_authors, Target: bat_books, Relation: authors.books
	 */
	public function testLinkColumnsGetAllRelativesBatch(): void
	{
		$db           = static::$db;
		$booksCtrl    = ORM::ctrl($db->getTableOrFail('bat_books'));
		$authorsTable = $db->getTableOrFail('bat_authors');
		$relation     = $authorsTable->getRelation('books');

		/** @var ORMEntity[] $authors */
		$authors = [static::$fixture['alice'], static::$fixture['bob']];

		$map = $booksCtrl->getAllRelativesBatch($authors, $relation);

		self::assertCount(\count($authors), $map, 'getAllRelativesBatch must return one entry per host');

		$alice = static::$fixture['alice'];
		$bob   = static::$fixture['bob'];

		// Alice has two books: PHP Manual and SQL Guide.
		self::assertCount(2, $map[$alice->toIdentityKey()], 'Alice must have 2 books');

		// Bob has one book: Linux Primer.
		self::assertCount(1, $map[$bob->toIdentityKey()], 'Bob must have 1 book');

		// Verify book IDs for Alice.
		$aliceBooksIds = \array_map(static fn(ORMEntity $e) => $e->toIdentityKey(), $map[$alice->toIdentityKey()]);
		self::assertContains(static::$fixture['php_manual']->toIdentityKey(), $aliceBooksIds);
		self::assertContains(static::$fixture['sql_guide']->toIdentityKey(), $aliceBooksIds);

		// Verify book ID for Bob.
		self::assertSame(
			static::$fixture['linux_primer']->toIdentityKey(),
			$map[$bob->toIdentityKey()][0]->toIdentityKey()
		);
	}

	/**
	 * LinkColumns (one-to-many): countRelativesBatch counts books per author.
	 */
	public function testLinkColumnsCountRelativesBatch(): void
	{
		$db           = static::$db;
		$booksCtrl    = ORM::ctrl($db->getTableOrFail('bat_books'));
		$authorsTable = $db->getTableOrFail('bat_authors');
		$relation     = $authorsTable->getRelation('books');

		$authors = [static::$fixture['alice'], static::$fixture['bob']];

		$map = $booksCtrl->countRelativesBatch($authors, $relation);

		self::assertSame(2, $map[static::$fixture['alice']->toIdentityKey()], 'Alice must have 2 books');
		self::assertSame(1, $map[static::$fixture['bob']->toIdentityKey()], 'Bob must have 1 book');
	}

	// -------------------------------------------------------------------------
	// Tests -- LinkThrough
	// -------------------------------------------------------------------------

	/**
	 * LinkThrough: getAllRelativesBatch fetches genres per book via the pivot table.
	 *
	 * Host: bat_books, Target: bat_genres, Relation: books.genres (through bat_book_genres)
	 */
	public function testLinkThroughGetAllRelativesBatch(): void
	{
		$db         = static::$db;
		$genresCtrl = ORM::ctrl($db->getTableOrFail('bat_genres'));
		$booksTable = $db->getTableOrFail('bat_books');
		$relation   = $booksTable->getRelation('genres');

		$books = [
			static::$fixture['php_manual'],
			static::$fixture['sql_guide'],
			static::$fixture['linux_primer'],
		];

		$map = $genresCtrl->getAllRelativesBatch($books, $relation);

		self::assertCount(\count($books), $map, 'getAllRelativesBatch must return one entry per host');

		$phpManualKey   = static::$fixture['php_manual']->toIdentityKey();
		$sqlGuideKey    = static::$fixture['sql_guide']->toIdentityKey();
		$linuxPrimerKey = static::$fixture['linux_primer']->toIdentityKey();

		// PHP Manual -> Programming only.
		self::assertCount(1, $map[$phpManualKey], 'PHP Manual must have 1 genre');
		self::assertSame(
			static::$fixture['programming']->toIdentityKey(),
			$map[$phpManualKey][0]->toIdentityKey()
		);

		// SQL Guide -> Databases only.
		self::assertCount(1, $map[$sqlGuideKey], 'SQL Guide must have 1 genre');
		self::assertSame(
			static::$fixture['databases']->toIdentityKey(),
			$map[$sqlGuideKey][0]->toIdentityKey()
		);

		// Linux Primer -> Programming only.
		self::assertCount(1, $map[$linuxPrimerKey], 'Linux Primer must have 1 genre');
		self::assertSame(
			static::$fixture['programming']->toIdentityKey(),
			$map[$linuxPrimerKey][0]->toIdentityKey()
		);
	}

	/**
	 * LinkThrough: countRelativesBatch counts genres per book via pivot.
	 */
	public function testLinkThroughCountRelativesBatch(): void
	{
		$db         = static::$db;
		$genresCtrl = ORM::ctrl($db->getTableOrFail('bat_genres'));
		$booksTable = $db->getTableOrFail('bat_books');
		$relation   = $booksTable->getRelation('genres');

		$books = [
			static::$fixture['php_manual'],
			static::$fixture['sql_guide'],
			static::$fixture['linux_primer'],
		];

		$map = $genresCtrl->countRelativesBatch($books, $relation);

		self::assertSame(1, $map[static::$fixture['php_manual']->toIdentityKey()]);
		self::assertSame(1, $map[static::$fixture['sql_guide']->toIdentityKey()]);
		self::assertSame(1, $map[static::$fixture['linux_primer']->toIdentityKey()]);
	}

	// -------------------------------------------------------------------------
	// Tests -- LinkMorph
	// -------------------------------------------------------------------------

	/**
	 * LinkMorph: getAllRelativesBatch fetches reviews for each book.
	 *
	 * Host: bat_books (morph parent), Target: bat_reviews (morph child)
	 * Relation: books.reviews via LinkMorph('reviewable')
	 */
	public function testLinkMorphGetAllRelativesBatch(): void
	{
		$db           = static::$db;
		$reviewsCtrl  = ORM::ctrl($db->getTableOrFail('bat_reviews'));
		$booksTable   = $db->getTableOrFail('bat_books');
		$relation     = $booksTable->getRelation('reviews');

		$books = [
			static::$fixture['php_manual'],
			static::$fixture['sql_guide'],
			static::$fixture['linux_primer'],
		];

		$map = $reviewsCtrl->getAllRelativesBatch($books, $relation);

		self::assertCount(\count($books), $map, 'getAllRelativesBatch must return one entry per host');

		$phpManualKey   = static::$fixture['php_manual']->toIdentityKey();
		$sqlGuideKey    = static::$fixture['sql_guide']->toIdentityKey();
		$linuxPrimerKey = static::$fixture['linux_primer']->toIdentityKey();

		// PHP Manual has no reviews.
		self::assertCount(0, $map[$phpManualKey], 'PHP Manual must have 0 reviews');

		// SQL Guide has 1 review: "Excellent!"
		self::assertCount(1, $map[$sqlGuideKey], 'SQL Guide must have 1 review');

		// Linux Primer has 2 reviews: "Very helpful!" and "Comprehensive!"
		self::assertCount(2, $map[$linuxPrimerKey], 'Linux Primer must have 2 reviews');
	}

	/**
	 * LinkMorph: countRelativesBatch counts reviews per book.
	 */
	public function testLinkMorphCountRelativesBatch(): void
	{
		$db          = static::$db;
		$reviewsCtrl = ORM::ctrl($db->getTableOrFail('bat_reviews'));
		$booksTable  = $db->getTableOrFail('bat_books');
		$relation    = $booksTable->getRelation('reviews');

		$books = [
			static::$fixture['php_manual'],
			static::$fixture['sql_guide'],
			static::$fixture['linux_primer'],
		];

		$map = $reviewsCtrl->countRelativesBatch($books, $relation);

		self::assertSame(0, $map[static::$fixture['php_manual']->toIdentityKey()], 'PHP Manual: 0 reviews');
		self::assertSame(1, $map[static::$fixture['sql_guide']->toIdentityKey()], 'SQL Guide: 1 review');
		self::assertSame(2, $map[static::$fixture['linux_primer']->toIdentityKey()], 'Linux Primer: 2 reviews');
	}

	// -------------------------------------------------------------------------
	// Tests -- LinkJoin
	// -------------------------------------------------------------------------

	/**
	 * LinkJoin: getAllRelativesBatch fetches genres for each author
	 * via a 2-hop join: bat_authors -> bat_author_genres -> bat_genres.
	 *
	 * Host: bat_authors, Target: bat_genres, Relation: authors.genres_direct
	 */
	public function testLinkJoinGetAllRelativesBatch(): void
	{
		$db           = static::$db;
		$genresCtrl   = ORM::ctrl($db->getTableOrFail('bat_genres'));
		$authorsTable = $db->getTableOrFail('bat_authors');
		$relation     = $authorsTable->getRelation('genres_direct');

		$authors = [static::$fixture['alice'], static::$fixture['bob']];

		$map = $genresCtrl->getAllRelativesBatch($authors, $relation);

		self::assertCount(\count($authors), $map, 'getAllRelativesBatch must return one entry per host');

		$aliceKey = static::$fixture['alice']->toIdentityKey();
		$bobKey   = static::$fixture['bob']->toIdentityKey();

		// Alice has 2 direct author-genre rows: Programming and Databases.
		self::assertCount(2, $map[$aliceKey], 'Alice must have 2 genres via author_genres pivot');

		$aliceGenreIds = \array_map(static fn(ORMEntity $e) => $e->toIdentityKey(), $map[$aliceKey]);
		\sort($aliceGenreIds);
		$expectedAlice = [
			static::$fixture['databases']->toIdentityKey(),
			static::$fixture['programming']->toIdentityKey(),
		];
		\sort($expectedAlice);
		self::assertSame($expectedAlice, $aliceGenreIds, 'Alice must have Programming and Databases genres');

		// Bob has 1 direct author-genre row: Programming.
		self::assertCount(1, $map[$bobKey], 'Bob must have 1 genre via author_genres pivot');
		self::assertSame(
			static::$fixture['programming']->toIdentityKey(),
			$map[$bobKey][0]->toIdentityKey(),
			'Bob must have the Programming genre'
		);
	}

	// -------------------------------------------------------------------------
	// Tests -- Per-relation column projection (partial entities)
	// -------------------------------------------------------------------------

	/**
	 * Per-relation select projection: entities loaded via getRelativeBatch with a select list
	 * must be marked as partial and only expose the projected columns.
	 *
	 * Uses LinkColumns (many-to-one): books.author -> bat_authors, projected to ['id'].
	 */
	public function testRelationSelectProjectionMakesEntitiesPartialViaGetRelativeBatch(): void
	{
		$db          = static::$db;
		$authorsCtrl = ORM::ctrl($db->getTableOrFail('bat_authors'));
		$booksTable  = $db->getTableOrFail('bat_books');
		$relation    = clone $booksTable->getRelation('author');
		$relation->setSelect(['id']);

		$books = [static::$fixture['php_manual'], static::$fixture['sql_guide']];

		$map = $authorsCtrl->getRelativeBatch($books, $relation);

		self::assertCount(\count($books), $map);

		foreach ($map as $author) {
			self::assertNotNull($author, 'each book must resolve to an author');
			self::assertTrue($author->isPartial(), 'entity loaded with a select projection must be partial');
			self::assertTrue($author->isColumnLoaded('id'), 'projected column "id" must be loaded');
			self::assertFalse($author->isColumnLoaded('name'), 'non-projected column "name" must not be loaded');
		}
	}

	/**
	 * Per-relation select projection: entities loaded via getAllRelativesBatch with a select list
	 * must be marked as partial.
	 *
	 * Uses LinkColumns (one-to-many): authors.books -> bat_books, projected to ['id', 'title'].
	 */
	public function testRelationSelectProjectionMakesEntitiesPartialViaGetAllRelativesBatch(): void
	{
		$db           = static::$db;
		$booksCtrl    = ORM::ctrl($db->getTableOrFail('bat_books'));
		$authorsTable = $db->getTableOrFail('bat_authors');
		$relation     = clone $authorsTable->getRelation('books');
		$relation->setSelect(['id', 'title']);

		$authors = [static::$fixture['alice']];

		$map = $booksCtrl->getAllRelativesBatch($authors, $relation);

		$aliceKey = static::$fixture['alice']->toIdentityKey();

		self::assertNotEmpty($map[$aliceKey], 'Alice must have books');

		foreach ($map[$aliceKey] as $book) {
			self::assertTrue($book->isPartial(), 'entity loaded with a select projection must be partial');
			self::assertTrue($book->isColumnLoaded('id'), 'projected column "id" must be loaded');
			self::assertTrue($book->isColumnLoaded('title'), 'projected column "title" must be loaded');
			self::assertFalse($book->isColumnLoaded('author_id'), 'non-projected column "author_id" must not be loaded');
		}
	}

	/**
	 * Without a select projection, entities loaded via getAllRelativesBatch must NOT be partial.
	 */
	public function testRelationWithoutSelectProjectionLoadsFullEntities(): void
	{
		$db           = static::$db;
		$booksCtrl    = ORM::ctrl($db->getTableOrFail('bat_books'));
		$authorsTable = $db->getTableOrFail('bat_authors');
		$relation     = $authorsTable->getRelation('books');

		self::assertNull($relation->getSelect(), 'books relation must not have a select projection by default');

		$authors = [static::$fixture['alice']];
		$map     = $booksCtrl->getAllRelativesBatch($authors, $relation);

		$aliceKey = static::$fixture['alice']->toIdentityKey();

		foreach ($map[$aliceKey] as $book) {
			self::assertFalse($book->isPartial(), 'entity loaded without a select projection must not be partial');
		}
	}

	// -------------------------------------------------------------------------
	// Abstract driver selector
	// -------------------------------------------------------------------------

	/**
	 * Returns the driver name for this concrete test class (e.g. 'mysql', 'postgresql', 'sqlite').
	 */
	abstract protected static function getDriverName(): string;

	/**
	 * Builds a DROP TABLE SQL string for all batch-relation test tables in reverse FK order.
	 * Syntax is adjusted per driver type.
	 *
	 * @param RDBMSInterface $db
	 *
	 * @return string
	 */
	protected static function buildDropAllSql(RDBMSInterface $db): string
	{
		$type = $db->getType();

		// Reverse FK order: dependant tables first.
		$logical = ['bat_author_genres', 'bat_book_genres', 'bat_reviews', 'bat_books', 'bat_genres', 'bat_authors'];

		$tables = \array_map(
			static fn(string $name) => $db->getTableOrFail($name)->getFullName(),
			$logical
		);

		$lines = [];

		if (MySQL::NAME === $type) {
			$lines[] = 'SET FOREIGN_KEY_CHECKS=0;';

			foreach ($tables as $t) {
				$lines[] = \sprintf('DROP TABLE IF EXISTS `%s`;', $t);
			}

			$lines[] = 'SET FOREIGN_KEY_CHECKS=1;';
		} elseif (PostgreSQL::NAME === $type) {
			foreach ($tables as $t) {
				$lines[] = \sprintf('DROP TABLE IF EXISTS "%s" CASCADE;', $t);
			}
		} else {
			// SQLite and others: no FK check toggle needed.
			foreach ($tables as $t) {
				$lines[] = \sprintf('DROP TABLE IF EXISTS "%s";', $t);
			}
		}

		return \implode(\PHP_EOL, $lines);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Returns the ORM output directory used exclusively for the batch-relation test schema.
	 */
	private static function getOrmOutDir(): string
	{
		return GOBL_TEST_ORM_OUTPUT . \DIRECTORY_SEPARATOR . 'BatchRel';
	}

	/**
	 * Inserts the shared fixture data into the live database.
	 *
	 * All tests in this class are read-only and share these entities.
	 *
	 * @param RDBMSInterface $db
	 */
	private static function insertFixture(RDBMSInterface $db): void
	{
		$authorsCtrl = ORM::ctrl($db->getTableOrFail('bat_authors'));
		$booksCtrl   = ORM::ctrl($db->getTableOrFail('bat_books'));
		$genresCtrl  = ORM::ctrl($db->getTableOrFail('bat_genres'));
		$bgCtrl      = ORM::ctrl($db->getTableOrFail('bat_book_genres'));
		$agCtrl      = ORM::ctrl($db->getTableOrFail('bat_author_genres'));
		$reviewsCtrl = ORM::ctrl($db->getTableOrFail('bat_reviews'));

		// Authors
		$alice = $authorsCtrl->addItem(['name' => 'Alice']);
		$bob   = $authorsCtrl->addItem(['name' => 'Bob']);

		// Books
		$phpManual   = $booksCtrl->addItem(['title' => 'PHP Manual', 'author_id' => $alice->id]);
		$sqlGuide    = $booksCtrl->addItem(['title' => 'SQL Guide', 'author_id' => $alice->id]);
		$linuxPrimer = $booksCtrl->addItem(['title' => 'Linux Primer', 'author_id' => $bob->id]);

		// Genres
		$programming = $genresCtrl->addItem(['name' => 'Programming']);
		$databases   = $genresCtrl->addItem(['name' => 'Databases']);

		// Book-Genre pivot rows
		$bgCtrl->addItem(['book_id' => $phpManual->id, 'genre_id' => $programming->id]);
		$bgCtrl->addItem(['book_id' => $sqlGuide->id, 'genre_id' => $databases->id]);
		$bgCtrl->addItem(['book_id' => $linuxPrimer->id, 'genre_id' => $programming->id]);

		// Author-Genre pivot rows (for the LinkJoin genres_direct relation)
		$agCtrl->addItem(['author_id' => $alice->id, 'genre_id' => $programming->id]);
		$agCtrl->addItem(['author_id' => $alice->id, 'genre_id' => $databases->id]);
		$agCtrl->addItem(['author_id' => $bob->id, 'genre_id' => $programming->id]);

		// Reviews: morph parent type = 'bat_books' (the table name)
		$reviewsCtrl->addItem(['body' => 'Excellent!', 'reviewable_id' => $sqlGuide->id, 'reviewable_type' => 'bat_books']);
		$reviewsCtrl->addItem(['body' => 'Very helpful!', 'reviewable_id' => $linuxPrimer->id, 'reviewable_type' => 'bat_books']);
		$reviewsCtrl->addItem(['body' => 'Comprehensive!', 'reviewable_id' => $linuxPrimer->id, 'reviewable_type' => 'bat_books']);

		static::$fixture = [
			'alice'        => $alice,
			'bob'          => $bob,
			'php_manual'   => $phpManual,
			'sql_guide'    => $sqlGuide,
			'linux_primer' => $linuxPrimer,
			'programming'  => $programming,
			'databases'    => $databases,
		];
	}
}
