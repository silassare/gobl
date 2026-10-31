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
use Gobl\ORM\Generators\CSGeneratorORM;
use Gobl\ORM\ORM;
use Gobl\ORM\ORMOptions;
use Gobl\Tests\BaseTestCase;
use Throwable;

/**
 * A text search against a real server, and what each engine folds.
 *
 * One operator with one name used to mean two comparisons: `like` folded case and accents on a
 * plain column and neither on a JSON one, because a JSON value carries a binary collation. The
 * search then answered an empty set, which reads as "there is no such page" rather than as a
 * failure, so nothing failed and nothing could assert against it.
 *
 * What the operator folds is still not the same on every engine. That is declared per driver by
 * the subclasses below and asserted here, rather than left to be discovered.
 */
abstract class TextMatchTestCase extends BaseTestCase
{
	protected const string NAMESPACE = 'Gobl\Tests\DbTextMatch';

	/**
	 * The same text in a JSON column and in a plain one, which is what the asymmetry was about.
	 */
	protected const array PAGES = [
		'accented'  => ['Economie sociale et solidaire', 'Un dossier de la region'],
		'acronym'   => ['Forum ouest-africain sur la resilience des jeunes (WAYRF)', 'Un dossier ESS de la region'],
		'wildcards' => ['Tarif 50%_net', 'Un dossier de la region'],
	];

	protected static ?RDBMSInterface $db = null;

	protected static bool $setupFailed = false;

	/** @var null|callable the PSR-4 autoloader of the generated classes */
	private static mixed $autoloader = null;

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		static::$setupFailed = false;

		try {
			ORM::undeclareNamespace(static::NAMESPACE);
		} catch (Throwable) {
			// not declared, the expected state
		}

		$out_dir = GOBL_TEST_ORM_OUTPUT . \DIRECTORY_SEPARATOR . 'TextMatch';

		try {
			$db = static::getNewDbInstance(static::getDriverName());
		} catch (Throwable $t) {
			static::$setupFailed = true;
			gobl_log(\sprintf('Error building live DB for %s: %s', static::getDriverName(), $t->getMessage()));

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
				->table('pages', static function (TableBuilder $tb): void {
					$tb->plural('pages')
						->singular('page')
						->columnPrefix('page');

					$tb->id();
					// The title per locale, and a body under it: a page is found by a word of its
					// body as well as by one of its title.
					$tb->map('title')->default([]);
					// The same text as the title's French entry, stored as text.
					$tb->string('name')->max(255);
				});

			$db->ns(static::NAMESPACE)->enableORM($out_dir);

			new CSGeneratorORM($db)->generate($db->getTables(static::NAMESPACE), $out_dir);

			$db->lock();

			try {
				$db->executeMulti('DROP TABLE IF EXISTS ' . self::pagesTableRef($db) . ';');
			} catch (Throwable) {
				// nothing left behind by an interrupted run, the expected state
			}

			$db->executeMulti($db->getGenerator()->buildDatabase());

			static::$db = $db;
		} catch (Throwable $t) {
			static::$setupFailed = true;
			gobl_log(\sprintf(
				'Error setting up the text match DB for %s: %s',
				static::getDriverName(),
				$t->getMessage()
			));
		}
	}

	public static function tearDownAfterClass(): void
	{
		if (null !== static::$db) {
			try {
				static::$db->executeMulti('DROP TABLE IF EXISTS ' . self::pagesTableRef(static::$db) . ';');
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

	protected function setUp(): void
	{
		parent::setUp();

		if (static::$setupFailed || null === static::$db) {
			self::markTestSkipped(\sprintf('Live DB not available for %s.', static::getDriverName()));
		}

		$this->addPages();
	}

	protected function tearDown(): void
	{
		if (null !== static::$db) {
			static::$db->executeMulti('DELETE FROM ' . self::pagesTableRef(static::$db) . ';');
		}

		parent::tearDown();
	}

	/**
	 * A term is found whatever case it is typed in, on every engine.
	 */
	public function testATermIsFoundWhateverCaseItIsTypedIn(): void
	{
		foreach (['WAYRF', 'wayrf', 'WaYrF'] as $term) {
			self::assertSame(
				['acronym'],
				$this->searchTitle($term),
				\sprintf('"%s" did not find the page by its acronym', $term)
			);
		}
	}

	/**
	 * A term inside a word is found: the operator searches, it does not match a whole value.
	 */
	public function testATermInsideTheTextIsFound(): void
	{
		self::assertSame(['accented'], $this->searchTitle('conomie sociale'));
	}

	/**
	 * The report's own case: a term typed without the accents of the text it searches.
	 *
	 * MySQL folds them through the collation. PostgreSQL and SQLite do not in this shape, and a
	 * project needing it there declares a searchable column, which folds in PHP.
	 */
	public function testATermWithoutTheAccentsOfTheText(): void
	{
		$this->addAccentedPage();

		$found = $this->searchTitle('economie sociale');

		// The engine's own answer, so a change of collation shows here rather than in a project.
		self::assertSame(
			static::foldsAccents(),
			\in_array('accented-spelled', $found, true),
			static::foldsAccents()
				? 'the accented page was not found by a term typed without its accents'
				: 'this engine is not expected to fold accents in this shape'
		);
	}

	/**
	 * One operator, one answer, whatever the column stores: this is the report's complaint.
	 */
	public function testTheAnswerIsTheSameOnAJsonAndOnAPlainColumn(): void
	{
		$this->addAccentedPage();

		foreach (['WAYRF', 'conomie sociale', 'economie sociale', '50%_n'] as $term) {
			self::assertSame(
				$this->searchName($term),
				$this->searchTitle($term),
				\sprintf('"%s" answered differently on a JSON column and on a plain one', $term)
			);
		}
	}

	/**
	 * Without a path the whole document is searched, which is how a word of a page's body finds it.
	 */
	public function testWithoutAPathTheWholeDocumentIsSearched(): void
	{
		self::assertSame(['acronym'], $this->searchDocument('ESS'), 'the body was not searched');
		self::assertSame([], $this->searchTitle('ESS'), 'a path searched more than the path');
	}

	/**
	 * The document text carries its keys, so a whole-document search can match a key name.
	 *
	 * The caveat is documented rather than compiled away: with a path it cannot happen.
	 */
	public function testTheDocumentTextCarriesItsKeys(): void
	{
		self::assertSame(
			['accented', 'acronym', 'wildcards'],
			$this->searchDocument('body'),
			'a key name was not part of the document text'
		);
		self::assertSame([], $this->searchTitle('body'), 'a path matched a key name');
	}

	/**
	 * A term holding a wildcard searches for that character.
	 */
	public function testAWildcardInATermIsNotAWildcard(): void
	{
		self::assertSame(['wildcards'], $this->searchTitle('50%_n'), 'the escaped wildcards did not match');
		self::assertSame(['wildcards'], $this->searchTitle('%'), 'a bare wildcard matched more than the page holding it');
		// Unescaped, "50%net" would be a pattern and would match "50%_net".
		self::assertSame([], $this->searchTitle('50%net'), 'a term was read as a pattern');
	}

	abstract protected static function getDriverName(): string;

	/**
	 * The table as a statement of its own must name it.
	 *
	 * Quoted, because the name has capitals in it and PostgreSQL folds an unquoted identifier to
	 * lower case: a DELETE naming it bare answers that no such relation exists.
	 */
	private static function pagesTableRef(RDBMSInterface $db): string
	{
		return $db->getGenerator()->quoteIdentifier($db->getTableOrFail('pages')->getFullName());
	}

	/**
	 * Whether this engine folds accents in this shape.
	 */
	abstract protected static function foldsAccents(): bool;

	/**
	 * The keys of the pages a term finds at the title's French entry, in a stable order.
	 */
	private function searchTitle(string $term): array
	{
		$query = ORM::query(static::$db->getTableOrFail('pages'));

		$query->whereTitleMatches($term, 'fr');

		return $this->keysOf($query->find()->getItems());
	}

	/**
	 * The keys of the pages a term finds anywhere in the title document.
	 */
	private function searchDocument(string $term): array
	{
		$query = ORM::query(static::$db->getTableOrFail('pages'));

		$query->whereTitleMatches($term);

		return $this->keysOf($query->find()->getItems());
	}

	/**
	 * The keys of the pages a term finds in the plain column.
	 */
	private function searchName(string $term): array
	{
		$query = ORM::query(static::$db->getTableOrFail('pages'));

		$query->whereNameMatches($term);

		return $this->keysOf($query->find()->getItems());
	}

	/**
	 * The pages' own keys, read back from the document.
	 */
	private function keysOf(iterable $items): array
	{
		$keys = [];

		foreach ($items as $item) {
			$keys[] = (string) $item->title->get('key');
		}

		\sort($keys);

		return $keys;
	}

	/**
	 * The three pages every test starts from.
	 */
	private function addPages(): void
	{
		foreach (self::PAGES as $key => [$text, $intro]) {
			$this->addPage($key, $text, $intro);
		}
	}

	/**
	 * The accented spelling of the same title, added only by the tests that search for it.
	 */
	private function addAccentedPage(): int
	{
		return $this->addPage('accented-spelled', 'Économie sociale et solidaire', 'Un dossier de la region');
	}

	private function addPage(string $key, string $text, string $intro): int
	{
		$ctrl = ORM::ctrl(static::$db->getTableOrFail('pages'));

		$created = $ctrl->addItem([
			'page_title' => [
				'key'  => $key,
				'fr'   => $text,
				'body' => ['intro' => $intro],
			],
			'page_name'  => $text,
		]);

		return (int) $created->id;
	}
}
