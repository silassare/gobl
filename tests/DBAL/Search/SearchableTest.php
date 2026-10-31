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

namespace Gobl\Tests\DBAL\Search;

use Gobl\DBAL\Builders\TableBuilder;
use Gobl\DBAL\Db;
use Gobl\DBAL\DbConfig;
use Gobl\DBAL\Drivers\MySQL\MySQL;
use Gobl\DBAL\Drivers\MySQL\MySQLQueryGenerator;
use Gobl\DBAL\Drivers\PostgreSQL\PostgreSQL;
use Gobl\DBAL\Drivers\PostgreSQL\PostgreSQLQueryGenerator;
use Gobl\DBAL\Drivers\SQLite\SQLite;
use Gobl\DBAL\Drivers\SQLQueryGeneratorBase;
use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Filters\Filters;
use Gobl\DBAL\Indexes\IndexType;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Operator;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\DBAL\Search\Searchable;
use Gobl\DBAL\Search\SearchMatch;
use Gobl\DBAL\Search\SearchText;
use Gobl\DBAL\Types\TypeDate;
use Gobl\DBAL\Types\TypeMap;
use Gobl\Tests\BaseTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUtils\Store\Map;

/**
 * A searchable column, and the folded text an index can serve.
 *
 * The folding is in PHP because that is the only way it is the same on every driver, which is the
 * whole complaint the unindexed search answers per engine. It is also the only indexable form: no
 * engine indexes an accent-folding expression portably.
 *
 * @internal
 */
#[CoversClass(Searchable::class)]
#[CoversClass(SearchMatch::class)]
#[CoversClass(SearchText::class)]
#[CoversClass(Filters::class)]
#[CoversClass(SQLQueryGeneratorBase::class)]
final class SearchableTest extends BaseTestCase
{
	// =========================================================================
	// Folding
	// =========================================================================

	/** One fold, so a term and the text it searches are folded the same way. */
	public function testFoldingLowersCaseAndStripsAccents(): void
	{
		self::assertSame('economie sociale', Searchable::fold('Économie Sociale'));
		self::assertSame('deja vu', Searchable::fold('Déjà Vu'));
		self::assertSame('wayrf', Searchable::fold('WAYRF'));
	}

	/** Whitespace is one space, so a line break in the text is not a term nobody can type. */
	public function testFoldingCollapsesWhitespace(): void
	{
		self::assertSame('economie sociale', Searchable::fold("  Économie \n\t sociale  "));
	}

	/** A script the accent table has no entry for keeps its letters and loses its case. */
	public function testFoldingLeavesWhatItDoesNotKnow(): void
	{
		self::assertSame('ЭКОНОМИКА', 'ЭКОНОМИКА');
		self::assertSame(\mb_strtolower('ЭКОНОМИКА'), Searchable::fold('ЭКОНОМИКА'));
	}

	/** A document's values, in the order the document holds them, and never its keys. */
	public function testADocumentFoldsToItsValues(): void
	{
		$folded = Searchable::foldValue([
			'fr'    => 'Économie Sociale',
			'count' => 42,
			'live'  => true,
			'body'  => ['intro' => 'Déjà Vu'],
		]);

		self::assertSame('economie sociale 42 deja vu', $folded);
	}

	/** The whole document when that is what was declared, keys included. */
	public function testADocumentCanFoldToItsWholeText(): void
	{
		$folded = Searchable::foldValue(['fr' => 'Économie'], SearchText::DOCUMENT);

		self::assertSame('{"fr":"economie"}', $folded);
	}

	/** A Map holds a document too: what the ORM hands back for a map column. */
	public function testAMapFoldsLikeTheDocumentItHolds(): void
	{
		$data = ['fr' => 'Économie'];

		self::assertSame('economie', Searchable::foldValue(new Map($data)));
	}

	/** Nothing to fold answers nothing, so the column says the row holds no text. */
	public function testWhatHoldsNoTextFoldsToNull(): void
	{
		self::assertNull(Searchable::foldValue(null));
		self::assertNull(Searchable::foldValue(true));
	}

	// =========================================================================
	// The declaration
	// =========================================================================

	/** A column holding no text is refused, rather than given a column of folded nothing. */
	public function testAColumnWithNoTextCannotBeSearchable(): void
	{
		$this->expectException(DBALRuntimeException::class);
		$this->expectExceptionMessage('holds no text to search');

		new TypeDate()->searchable();
	}

	/** The short form of the array schema: a term anywhere in the text. */
	public function testTheArraySchemaDeclaresIt(): void
	{
		$db = self::getNewDbInstance(SQLite::NAME);
		$db->ns('GoblSearchArray')->schema([
			'pages' => [
				'column_prefix' => 'page',
				'constraints'   => [['type' => 'primary_key', 'columns' => ['id']]],
				'columns'       => [
					'id'    => ['type' => 'bigint', 'auto_increment' => true, 'unsigned' => true],
					'title' => ['type' => 'map', 'searchable' => true],
					'name'  => ['type' => 'string', 'searchable' => 'prefix', 'searchable_text' => 'document'],
				],
			],
		]);
		$db->lock();

		$table = $db->getTableOrFail('pages');

		self::assertSame(SearchMatch::SUBSTRING, $table->getColumnOrFail('title')->getType()->getSearchMatch());
		self::assertSame(SearchMatch::PREFIX, $table->getColumnOrFail('name')->getType()->getSearchMatch());
		self::assertSame(SearchText::DOCUMENT, $table->getColumnOrFail('name')->getType()->getSearchText());
	}

	/** What a schema cannot mean is refused when it is read, not when a row is written. */
	public function testAnUnknownDeclarationIsRefused(): void
	{
		$this->expectException(DBALRuntimeException::class);
		$this->expectExceptionMessage('"searchable" should be true or one of');

		// What a schema file carries reaches a type through configure(), so this is that road.
		new TypeMap()->configure(['searchable' => 'everywhere']);
	}

	/** Nor can a schema ask for a kind of folded text that does not exist. */
	public function testAnUnknownTextDeclarationIsRefused(): void
	{
		$this->expectException(DBALRuntimeException::class);
		$this->expectExceptionMessage('"searchable_text" should be one of');

		new TypeMap()->configure(['searchable' => true, 'searchable_text' => 'everything']);
	}

	/** Locking the table gives the column its companion, private, and an index of the intent. */
	public function testTheTableHoldsACompanionColumnAndItsIndex(): void
	{
		$table = self::makeDb(SQLite::NAME)->getTableOrFail('pages');

		$search = $table->getColumnOrFail('title__search');

		self::assertSame('page_title__search', $search->getFullName());
		self::assertTrue($search->isPrivate(), 'the folded text is the ORM\'s own, not a request\'s');
		self::assertSame('string', $search->getType()->getName());

		$types = [];

		foreach ($table->getIndexes() as $index) {
			$types[\implode(',', $index->getColumns())] = $index->getType();
		}

		self::assertSame(IndexType::TEXT_SEARCH_SUBSTRING, $types['page_title__search'] ?? null);
		self::assertSame(IndexType::TEXT_SEARCH_PREFIX, $types['page_name__search'] ?? null);
	}

	/** Locking twice adds nothing twice. */
	public function testLockingTwiceAddsOneCompanion(): void
	{
		$db = self::makeDb(SQLite::NAME);
		$db->getTableOrFail('pages')->lock();

		self::assertCount(5, $db->getTableOrFail('pages')->getColumns());
	}

	// =========================================================================
	// What the index is built as
	// =========================================================================

	/** MySQL: the ngram parser, which is what makes a substring findable at all. */
	public function testMySQLBuildsAFullTextIndexWithTheNgramParser(): void
	{
		$sql = self::databaseSql(MySQL::NAME);

		self::assertStringContainsString('`page_title__search` text NULL', $sql);
		self::assertMatchesRegularExpression(
			'~CREATE FULLTEXT INDEX `[^`]+` ON `gObL_pages` \(`page_title__search`\) WITH PARSER ngram;~',
			$sql
		);
		// A TEXT column has no key length of its own, and MySQL indexes none without one.
		self::assertMatchesRegularExpression(
			'~CREATE INDEX `[^`]+` ON `gObL_pages` \(`page_name__search`\(' . MySQLQueryGenerator::TEXT_SEARCH_PREFIX_KEY_LENGTH . '\)\);~',
			$sql
		);
	}

	/** PostgreSQL: trigrams, and the extension they need, which the migration carries. */
	public function testPostgreSQLBuildsATrigramIndexAndItsExtension(): void
	{
		$sql = self::databaseSql(PostgreSQL::NAME);

		self::assertStringContainsString(
			'CREATE EXTENSION IF NOT EXISTS ' . PostgreSQLQueryGenerator::TEXT_SEARCH_EXTENSION . ';',
			$sql
		);
		self::assertMatchesRegularExpression(
			'~CREATE INDEX "[^"]+" ON "gObL_pages" USING gin \("page_title__search" gin_trgm_ops\);~',
			$sql
		);
		self::assertMatchesRegularExpression(
			'~CREATE INDEX "[^"]+" ON "gObL_pages" USING btree \("page_name__search" text_pattern_ops\);~',
			$sql
		);
	}

	/** SQLite has neither, and takes a plain index: the search reads the column. */
	public function testSQLiteBuildsAPlainIndex(): void
	{
		$sql = self::databaseSql(SQLite::NAME);

		self::assertMatchesRegularExpression(
			'~CREATE INDEX "[^"]+" ON "gObL_pages" \("page_title__search"\);~',
			$sql
		);
	}

	// =========================================================================
	// What a search of a declared column compiles to
	// =========================================================================

	/** MySQL reads the FULLTEXT index through a phrase search: n-grams in a row are a substring. */
	public function testMySQLSearchesTheFullTextIndex(): void
	{
		[$filters, $qb] = self::makeFilters(MySQL::NAME);
		$filters->matches('t.page_title', 'Économie Sociale');

		self::assertSame(
			'(MATCH(t.page_title__search) AGAINST (:_val_a IN BOOLEAN MODE))',
			(string) $filters
		);
		self::assertSame(['"economie sociale"'], \array_values($qb->getBoundValues()));
	}

	/** A term the parser holds no token for is read with LIKE: slower, never an empty answer. */
	public function testMySQLFallsBackToTheFoldedColumn(): void
	{
		foreach (['e' => '%e%', '50%_net' => '%50!%!_net%', 'a b' => '%a b%'] as $term => $pattern) {
			[$filters, $qb] = self::makeFilters(MySQL::NAME);
			$filters->matches('t.page_title', (string) $term);

			self::assertMatchesRegularExpression(
				'~^\(t\.page_title__search LIKE :\w+ ESCAPE \'!\'\)$~',
				(string) $filters,
				\sprintf('"%s" should be searched with LIKE', $term)
			);
			self::assertSame([$pattern], \array_values($qb->getBoundValues()));
		}
	}

	/** PostgreSQL and SQLite have one shape, and neither side folds anything again. */
	public function testTheOtherDriversCompareTheFoldedColumn(): void
	{
		foreach ([PostgreSQL::NAME, SQLite::NAME] as $driver) {
			[$filters, $qb] = self::makeFilters($driver);
			$filters->matches('t.page_title', 'Économie');

			self::assertMatchesRegularExpression(
				'~^\(t\.page_title__search LIKE :\w+ ESCAPE \'!\'\)$~',
				(string) $filters,
				$driver
			);
			self::assertSame(['%economie%'], \array_values($qb->getBoundValues()), $driver);
		}
	}

	/** An anchored search is the one a B-tree serves, so it is a LIKE on every driver. */
	public function testAnAnchoredSearchReadsThePrefixIndex(): void
	{
		foreach ([MySQL::NAME, PostgreSQL::NAME, SQLite::NAME] as $driver) {
			[$filters, $qb] = self::makeFilters($driver);
			$filters->startsWith('t.page_name', 'Écon');

			self::assertMatchesRegularExpression(
				'~^\(t\.page_name__search LIKE :\w+ ESCAPE \'!\'\)$~',
				(string) $filters,
				$driver
			);
			self::assertSame(['econ%'], \array_values($qb->getBoundValues()), $driver);
		}
	}

	/** A path is not the whole column, and the companion holds the whole column's text. */
	public function testAPathIsSearchedWhereItStands(): void
	{
		[$filters] = self::makeFilters(MySQL::NAME);
		$filters->matches('t.page_title#fr', 'economie');

		$sql = (string) $filters;

		self::assertStringNotContainsString('page_title__search', $sql);
		self::assertStringContainsString('JSON_UNQUOTE(JSON_EXTRACT(', $sql);
	}

	/** A term holding nothing to fold is refused rather than searched for nothing. */
	public function testATermHoldingNoTextIsRefused(): void
	{
		$this->expectException(DBALRuntimeException::class);
		$this->expectExceptionMessage('holding nothing to search for');

		[$filters] = self::makeFilters(SQLite::NAME);
		$filters->matches('t.page_title', '   ');
	}

	/** The generated method is whereNameStartsWith(). */
	public function testTheFilterSuffixNamesTheAnchoredOperator(): void
	{
		$column = self::makeDb(SQLite::NAME)->getTableOrFail('pages')->getColumnOrFail('name');

		self::assertSame('name_starts_with', Operator::STARTS_WITH->getFilterSuffix($column));
	}

	private static function databaseSql(string $driver): string
	{
		$db = self::makeDb($driver);

		return $db->getGenerator()->buildDatabase($db->getTableOrFail('pages')->getNamespace());
	}

	/**
	 * @return array{0: Filters, 1: QBSelect}
	 */
	private static function makeFilters(string $driver): array
	{
		$db = self::makeDb($driver);
		$qb = new QBSelect($db);
		$qb->from('pages', 't');

		return [new Filters($qb), $qb];
	}

	private static function makeDb(string $driver, ?callable $factory = null): RDBMSInterface
	{
		static $counter = 0;
		++$counter;

		$db = Db::newInstanceOf($driver, new DbConfig(self::getDbConfig($driver)->toArray()));

		$db->ns('GoblSearchable' . $counter)->table('pages', $factory ?? static function (TableBuilder $t): void {
			$t->columnPrefix('page');
			$t->id();
			$t->map('title')->searchable(SearchMatch::SUBSTRING);
			$t->string('name')->max(255)->searchable(SearchMatch::PREFIX);
		});

		return $db->lock();
	}
}
