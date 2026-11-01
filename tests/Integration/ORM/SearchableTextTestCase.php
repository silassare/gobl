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
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Search\Searchable;
use Gobl\DBAL\Search\SearchMatch;
use Gobl\DBAL\Search\SearchText;
use Gobl\DBAL\Types\Utils\JsonPatch;
use Gobl\ORM\Exceptions\ORMRuntimeException;
use Gobl\ORM\Generators\CSGeneratorORM;
use Gobl\ORM\ORM;
use Gobl\ORM\ORMEntity;
use Gobl\ORM\ORMOptions;
use Gobl\Tests\BaseTestCase;
use Gobl\Tests\TestLog;
use Gobl\Tests\TestPaths;
use Override;
use PDO;
use Throwable;

/**
 * A searchable column against a real server: the index it is given, and the text it holds.
 *
 * What this buys over searching the column where it stands is the same answer on every engine --
 * the folding happens once, in PHP, so a term typed without its accents finds an accented row on
 * PostgreSQL and SQLite too, which neither can do in the unindexed shape.
 *
 * The folded text is the one thing here that can drift, and drift would make a search go quiet
 * rather than wrong, so it is asserted against the normaliser itself and not against a term.
 */
abstract class SearchableTextTestCase extends BaseTestCase
{
	protected const string NAMESPACE = 'Gobl\Tests\DbSearchable';

	protected const string ACCENTED = 'Économie sociale et solidaire';

	protected static ?RDBMSInterface $db = null;

	protected static bool $setupFailed = false;

	/** @var null|callable the PSR-4 autoloader of the generated classes */
	private static mixed $autoloader = null;

	#[Override]
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		static::$setupFailed = false;

		try {
			ORM::undeclareNamespace(static::NAMESPACE);
		} catch (Throwable) {
			// not declared, the expected state
		}

		$out_dir = TestPaths::ORM_OUTPUT . \DIRECTORY_SEPARATOR . 'Searchable';

		try {
			$db = static::getNewDbInstance(static::getDriverName());
		} catch (Throwable $t) {
			static::$setupFailed = true;
			TestLog::write(\sprintf('Error building live DB for %s: %s', static::getDriverName(), $t->getMessage()));

			return;
		}

		try {
			if (!\is_dir($out_dir . \DIRECTORY_SEPARATOR . 'Base')) {
				\mkdir($out_dir . \DIRECTORY_SEPARATOR . 'Base', 0o755, true);
			}

			$prefix           = static::NAMESPACE . '\\';
			self::$autoloader = static function (string $class) use ($out_dir, $prefix): void {
				if (!\str_starts_with($class, $prefix)) {
					return;
				}

				$file = $out_dir . \DIRECTORY_SEPARATOR
					. \str_replace('\\', \DIRECTORY_SEPARATOR, \substr($class, \strlen($prefix))) . '.php';

				if (\is_file($file)) {
					require_once $file;
				}
			};

			\spl_autoload_register(self::$autoloader);

			$db->ns(static::NAMESPACE)
				->table('articles', static function (TableBuilder $tb): void {
					$tb->plural('articles')
						->singular('article')
						->columnPrefix('article');

					$tb->id();
					// The values of the document, which is what somebody searches for.
					$tb->map('title')->default([])->searchable(SearchMatch::SUBSTRING);
					// The document as it is written, keys included.
					$tb->map('meta')->default([])->searchable(SearchMatch::SUBSTRING, SearchText::DOCUMENT);
					// Plain text, and an anchored search of it.
					$tb->string('name')->max(255)->nullable()->searchable(SearchMatch::PREFIX);
					// The same text, declared nothing: the unindexed road, for comparison.
					$tb->string('plain')->max(255)->nullable();
				});

			$db->ns(static::NAMESPACE)->enableORM($out_dir);

			new CSGeneratorORM($db)->generate($db->getTables(static::NAMESPACE), $out_dir);

			$db->lock();

			try {
				$db->executeMulti('DROP TABLE IF EXISTS ' . self::tableRef($db) . ';');
			} catch (Throwable) {
				// nothing left behind by an interrupted run, the expected state
			}

			$db->executeMulti($db->getGenerator()->buildDatabase());

			static::$db = $db;
		} catch (Throwable $t) {
			static::$setupFailed = true;
			TestLog::write(\sprintf(
				'Error setting up the searchable DB for %s: %s',
				static::getDriverName(),
				$t->getMessage()
			));
		}
	}

	#[Override]
	public static function tearDownAfterClass(): void
	{
		if (null !== static::$db) {
			try {
				static::db()->executeMulti('DROP TABLE IF EXISTS ' . self::tableRef(static::$db) . ';');
			} catch (Throwable) {
				// best effort
			}

			try {
				ORM::undeclareNamespace(static::NAMESPACE);
			} catch (Throwable) {
				// already undeclared
			}

			static::$db = null;
		}

		if (null !== self::$autoloader) {
			\spl_autoload_unregister(self::$autoloader);
			self::$autoloader = null;
		}

		parent::tearDownAfterClass();
	}

	#[Override]
	protected function setUp(): void
	{
		parent::setUp();

		if (static::$setupFailed || null === static::$db) {
			self::markTestSkipped(\sprintf('Live DB not available for %s.', static::getDriverName()));
		}
	}

	#[Override]
	protected function tearDown(): void
	{
		if (null !== static::$db) {
			static::db()->executeMulti('DELETE FROM ' . self::tableRef(static::$db) . ';');
		}

		parent::tearDown();
	}

	/**
	 * The index is on the server, named for the column it indexes.
	 */
	public function testTheIndexIsOnTheServer(): void
	{
		$indexed = $this->indexedColumnsOnServer();

		self::assertContains('article_title__search', $indexed, 'the substring index is missing');
		self::assertContains('article_name__search', $indexed, 'the prefix index is missing');
	}

	/**
	 * The folded column holds what the normaliser answers for the row, and nothing else.
	 *
	 * The guard that matters: drift here makes a search answer an empty set, which reads as "there
	 * is no such row" rather than as a failure, so nothing else would catch it.
	 */
	public function testTheFoldedTextIsWhatTheNormaliserAnswers(): void
	{
		$id = $this->addArticle(['fr' => self::ACCENTED], 'Économie Sociale');

		$row = $this->storedRow($id);

		self::assertSame(
			Searchable::foldValue(['fr' => self::ACCENTED], SearchText::VALUES),
			$row['article_title__search'],
			'the folded title is not what the normaliser answers'
		);
		self::assertSame(
			Searchable::fold('Économie Sociale'),
			$row['article_name__search'],
			'the folded name is not what the normaliser answers'
		);
	}

	/**
	 * The point of all of it: a term typed without the accents of the text finds the row, on every
	 * engine, which the unindexed search cannot promise.
	 */
	public function testATermWithoutAccentsFindsTheRow(): void
	{
		$this->addArticle(['fr' => self::ACCENTED], self::ACCENTED);

		self::assertCount(1, $this->search('economie sociale'), 'the accented row was not found');
		self::assertCount(1, $this->search('ECONOMIE'), 'the case was not folded');
		self::assertCount(1, $this->search('solidaire'), 'a word further in the text was not found');
		self::assertCount(0, $this->search('agriculture'));
	}

	/**
	 * A write updates the folded text in the same statement, so a search of the old term stops
	 * finding the row and a search of the new one starts.
	 */
	public function testAWriteKeepsTheFoldedTextInStep(): void
	{
		$id = $this->addArticle(['fr' => self::ACCENTED], self::ACCENTED);

		$entity        = $this->getArticle($id);
		$entity->title = ['fr' => 'Agriculture vivrière'];
		self::assertTrue($entity->save());

		self::assertCount(0, $this->search('economie'), 'the old term still finds the row');
		self::assertCount(1, $this->search('vivriere'), 'the new term does not find the row');
	}

	/**
	 * A patch writes the paths it changed, and the folded text of the document they leave behind.
	 */
	public function testAPatchKeepsTheFoldedTextInStep(): void
	{
		$id = $this->addArticle(['fr' => self::ACCENTED], self::ACCENTED);

		$entity = $this->getArticle($id);
		$patch  = new JsonPatch($entity->title);
		$patch->set('en', 'Social Économy');
		$entity->title = $patch;
		self::assertTrue($entity->save());

		self::assertCount(1, $this->search('economie'), 'the untouched text was lost');
		self::assertCount(1, $this->search('social economy'), 'the patched text was not folded');
	}

	/**
	 * An update of many rows writes the folded text too: no entity, same statement.
	 */
	public function testABulkUpdateKeepsTheFoldedTextInStep(): void
	{
		$this->addArticle(['fr' => self::ACCENTED], self::ACCENTED);

		$updated = ORM::ctrl(static::db()->getTableOrFail('articles'))->updateAllItems(
			ORMOptions::makeFromFilters(['article_name' => self::ACCENTED])
				->setFormData(['article_title' => ['fr' => 'Agriculture vivrière']])
		);

		self::assertSame(1, $updated);
		self::assertCount(0, $this->search('economie'), 'the old term still finds the row');
		self::assertCount(1, $this->search('vivriere'), 'the new term does not find the row');
	}

	/**
	 * What a bulk update cannot fold without reading each row is refused, and says which column.
	 */
	public function testABulkWriteItCannotFoldIsRefused(): void
	{
		$id     = $this->addArticle(['fr' => self::ACCENTED], self::ACCENTED);
		$entity = $this->getArticle($id);

		$patch = new JsonPatch($entity->title);
		$patch->set('en', 'Social Economy');

		$this->expectException(ORMRuntimeException::class);
		$this->expectExceptionMessageMatches('~article_title.+cannot be folded~');

		ORM::query(static::db()->getTableOrFail('articles'))->update(['article_title' => $patch]);
	}

	/**
	 * An anchored search finds the start of the text and not the middle of it.
	 */
	public function testAnAnchoredSearchFindsTheStart(): void
	{
		$this->addArticle(['fr' => self::ACCENTED], self::ACCENTED);

		$query = ORM::query(static::db()->getTableOrFail('articles'));
		$query->whereNameStartsWith('Écon');
		self::assertCount(1, \iterator_to_array($query->find()->getItems()), 'the start was not found');

		$query = ORM::query(static::db()->getTableOrFail('articles'));
		$query->whereNameStartsWith('sociale');
		self::assertCount(0, \iterator_to_array($query->find()->getItems()), 'the middle was found');
	}

	/**
	 * A term holding a wildcard searches for that character, folded column or not.
	 */
	public function testAWildcardInATermIsNotAWildcard(): void
	{
		$this->addArticle(['fr' => 'Tarif 50%_net'], 'Tarif');

		self::assertCount(1, $this->search('50%_n'), 'the escaped wildcards did not match');
		self::assertCount(0, $this->search('50%net'), 'a term was read as a pattern');
	}

	/**
	 * A column declared to fold its whole document finds a key name; one folding its values cannot.
	 */
	public function testWhatTheDeclarationSaysIsWhatIsSearched(): void
	{
		$this->addArticle(['fr' => self::ACCENTED], self::ACCENTED, ['region' => 'Atlantique']);

		$query = ORM::query(static::db()->getTableOrFail('articles'));
		$query->whereMetaMatches('region');
		self::assertCount(1, \iterator_to_array($query->find()->getItems()), 'the document text has keys');

		self::assertCount(0, $this->search('fr'), 'the values of a document are not its keys');
	}

	/**
	 * The live connection this class runs against, which its own setup opened.
	 *
	 * Empty until that setup has run, and a test reaching it before then says so here rather than
	 * as a call on null several frames away.
	 */
	protected static function db(): RDBMSInterface
	{
		$db = static::$db;

		self::assertNotNull($db, 'the live database connection was not opened');

		return $db;
	}

	abstract protected static function getDriverName(): string;

	/**
	 * The columns this server holds an index on, as it names them.
	 *
	 * @return list<string>
	 */
	abstract protected function indexedColumnsOnServer(): array;

	/**
	 * The table as a statement of its own must name it, quoted: PostgreSQL folds an unquoted
	 * identifier to lower case and the name has capitals in it.
	 */
	private static function tableRef(RDBMSInterface $db): string
	{
		return $db->getGenerator()->quoteIdentifier($db->getTableOrFail('articles')->getFullName());
	}

	/**
	 * The articles a term finds in the title.
	 *
	 * @return list<ORMEntity>
	 */
	private function search(string $term): array
	{
		$query = ORM::query(static::db()->getTableOrFail('articles'));

		$query->whereTitleMatches($term);

		return \iterator_to_array($query->find()->getItems());
	}

	private function addArticle(array $title, string $name, array $meta = []): int
	{
		$created = ORM::ctrl(static::db()->getTableOrFail('articles'))->addItem([
			'article_title' => $title,
			'article_meta'  => $meta,
			'article_name'  => $name,
			'article_plain' => $name,
		]);

		return (int) $created->id;
	}

	private function getArticle(int $id): ORMEntity
	{
		$entity = ORM::ctrl(static::db()->getTableOrFail('articles'))
			->getItem(ORMOptions::makeFromFilters(['article_id' => $id]));

		self::assertNotNull($entity);

		return $entity;
	}

	/**
	 * The row as the database holds it, the private columns included.
	 */
	private function storedRow(int $id): array
	{
		$sql  = 'SELECT * FROM ' . self::tableRef(static::$db) . ' WHERE article_id = ' . $id;
		$stmt = static::db()->execute($sql, []);

		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		self::assertIsArray($row);

		return $row;
	}
}
