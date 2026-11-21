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
use Gobl\DBAL\Queries\QBSelect;
use Gobl\ORM\Generators\CSGeneratorORM;
use Gobl\ORM\ORM;
use Gobl\ORM\ORMEntity;
use Gobl\ORM\ORMOptions;
use Gobl\Tests\BaseTestCase;
use Gobl\Tests\TestLog;
use Gobl\Tests\TestPaths;
use Override;
use Throwable;

/**
 * Writing a row's relatives through its relations: what each kind of link must write.
 *
 * Every write a client makes through a relation -- a REST service's nested create, update or delete
 * -- goes to the relation's controller, and the controller is the same for every kind of link. So
 * each test here is one kind of link and one operation, asserting what the write must leave in the
 * database: the right row, linked to the right row, and nothing else changed.
 *
 * The tables live in a namespace of their own so the shared schema and its snapshots stay as they
 * are.
 */
abstract class ORMRelationWritesTestCase extends BaseTestCase
{
	protected const string NAMESPACE = 'Gobl\Tests\DbRelationWrites';

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

		$out_dir = TestPaths::ORM_OUTPUT . \DIRECTORY_SEPARATOR . 'RelationWrites';

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

			$ns = $db->ns(static::NAMESPACE);

			$ns->table('rw_clients', static function (TableBuilder $t): void {
				$t->plural('rw_clients')->singular('rw_client')->columnPrefix('client');
				$t->id();
				$t->string('name')->max(60);
				$t->softDeletable();
			});

			$accounts = $ns->table('rw_accounts', static function (TableBuilder $t): void {
				$t->plural('rw_accounts')->singular('rw_account')->columnPrefix('account');
				$t->id();
				$t->string('label')->max(60);
				$t->foreign('client_id', 'rw_clients', 'id', true);
				$t->belongsTo('client')->from('rw_clients');
			});

			$ns->table('rw_transactions', static function (TableBuilder $t): void {
				$t->plural('rw_transactions')->singular('rw_transaction')->columnPrefix('transaction');
				$t->id();
				$t->foreign('account_id', 'rw_accounts', 'id');
				$t->string('state')->max(20)->nullable();
			});

			// A table with a private column, apart, so what it does to a write is seen on its own.
			$ns->table('rw_notes', static function (TableBuilder $t): void {
				$t->plural('rw_notes')->singular('rw_note')->columnPrefix('note');
				$t->id();
				$t->foreign('account_id', 'rw_accounts', 'id');
				$t->string('body')->max(60);
				$t->string('internal')->max(60)->nullable();
				$t->useColumn('internal')->setPrivate();
			});

			$articles = $ns->table('rw_articles', static function (TableBuilder $t): void {
				$t->plural('rw_articles')->singular('rw_article')->columnPrefix('article');
				$t->id();
				$t->string('title')->max(60);
				$t->softDeletable();
			});

			$ns->table('rw_tags', static function (TableBuilder $t): void {
				$t->plural('rw_tags')->singular('rw_tag')->columnPrefix('tag');
				$t->id();
				$t->string('label')->max(60);
				$t->softDeletable();
			});

			$ns->table('rw_taggings', static function (TableBuilder $t): void {
				$t->plural('rw_taggings')->singular('rw_tagging')->columnPrefix('tagging');
				$t->id();
				$t->foreign('article_id', 'rw_articles', 'id');
				$t->foreign('tag_id', 'rw_tags', 'id');
			});

			$ns->table('rw_comments', static function (TableBuilder $t): void {
				$t->plural('rw_comments')->singular('rw_comment')->columnPrefix('comment');
				$t->id();
				$t->string('body')->max(60);
				$t->morph('commentable');
				$t->belongsTo('article')->from('rw_articles')->usingMorph('commentable');
			});

			$accounts->factory(static function (TableBuilder $t): void {
				$t->hasMany('transactions')->from('rw_transactions');
				$t->hasMany('notes')->from('rw_notes');
				$t->hasMany('pending_transactions')
					->from('rw_transactions')
					->usingColumns([], ['state', 'eq', 'pending']);
			});

			$articles->factory(static function (TableBuilder $t): void {
				$t->hasMany('tags')->from('rw_tags')->through('rw_taggings');
				$t->hasMany('comments')->from('rw_comments')->usingMorph('commentable');
			});

			$ns->enableORM($out_dir);

			new CSGeneratorORM($db)->generate($db->getTables(static::NAMESPACE), $out_dir);

			$db->lock();
			$db->executeMulti($db->getGenerator()->buildDatabase());

			static::$db = $db;
		} catch (Throwable $t) {
			static::$setupFailed = true;
			TestLog::write(\sprintf(
				'Error setting up the relation writes DB for %s: %s',
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
				static::db()->executeMulti(
					\implode(' ', \array_map(
						static fn (string $name): string => \sprintf(
							'DROP TABLE IF EXISTS %s;',
							static::db()->getTableOrFail($name)->getFullName()
						),
						['rw_comments', 'rw_taggings', 'rw_tags', 'rw_articles', 'rw_notes', 'rw_transactions', 'rw_accounts', 'rw_clients']
					))
				);
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

	/**
	 * A request's payload never writes a private column of a relative: the same refusal a direct
	 * write of that column meets.
	 */
	public function testAnUpdateThroughARelationDoesNotWriteAPrivateColumn(): void
	{
		$a = $this->add('rw_accounts', ['account_label' => 'a']);
		$n = $this->add('rw_notes', ['note_account_id' => $a->id, 'note_body' => 'n']);

		try {
			$this->relation('rw_accounts', 'notes')
				->update($a, ['note_id' => $n->id, 'note_internal' => 'written']);
		} catch (Throwable) {
			// refused: what is asserted below is that nothing was written
		}

		$row = $this->row('rw_notes', ['note_id' => $n->id]);

		self::assertIsArray($row);
		self::assertNull($row['note_internal']);
	}

	/**
	 * What a payload may write, an update through a relation writes: the refusal above is the
	 * private column's, not every nested update's.
	 */
	public function testAnUpdateThroughARelationWritesWhatThePayloadMay(): void
	{
		$a = $this->add('rw_accounts', ['account_label' => 'a']);
		$n = $this->add('rw_notes', ['note_account_id' => $a->id, 'note_body' => 'before']);

		$this->relation('rw_accounts', 'notes')->update($a, ['note_id' => $n->id, 'note_body' => 'after']);

		self::assertSame('after', (string) $this->one('rw_notes', ['note_id' => $n->id])->body);
	}

	/**
	 * A table with a private column can be written through a relation when the payload does not
	 * name that column: a private column the request never touched is not a reason to refuse.
	 */
	public function testCreatingThroughARelationIgnoresAPrivateColumnThePayloadDoesNotName(): void
	{
		$a = $this->add('rw_accounts', ['account_label' => 'a']);

		$this->relation('rw_accounts', 'notes')->create($a, ['note_body' => 'plain']);

		self::assertSame((string) $a->id, (string) $this->one('rw_notes', ['note_body' => 'plain'])->account_id);
	}

	/**
	 * The live connection this class runs against, which its own setup opened.
	 */
	protected static function db(): RDBMSInterface
	{
		$db = static::$db;

		self::assertNotNull($db, 'the live database connection was not opened');

		return $db;
	}

	abstract protected static function getDriverName(): string;

	private function table(string $name): \Gobl\DBAL\Table
	{
		return static::db()->getTableOrFail($name);
	}

	/**
	 * @param array<string, mixed> $values
	 */
	private function add(string $table, array $values): ORMEntity
	{
		return ORM::ctrl($this->table($table))->addItem($values);
	}

	/**
	 * @param array<string, mixed> $filters
	 */
	private function one(string $table, array $filters): ORMEntity
	{
		$found = ORM::ctrl($this->table($table))->getItem(ORMOptions::makeFromFilters($filters));

		self::assertNotNull($found, \sprintf('a row of %s matching the filters', $table));

		return $found;
	}

	/**
	 * A row as stored, columns private ones included, read past the ORM -- which would not answer a
	 * private column -- and through the query builder, which quotes a name as each driver needs.
	 *
	 * @param array<string, mixed> $equal
	 */
	private function row(string $name, array $equal): mixed
	{
		$table   = $this->table($name);
		$qb      = new QBSelect(static::db());
		$filters = $qb->filters();

		$qb->from($table)->select($table);

		foreach ($equal as $column => $value) {
			$filters->eq($qb->fullyQualifiedName($table, $column), $value);
		}

		$qb->andWhere($filters);

		return static::db()
			->select($qb->getSqlQuery(), $qb->getBoundValues(), $qb->getBoundValuesTypes())
			->fetch();
	}

	private function relation(string $table, string $name): \Gobl\DBAL\Relations\Interfaces\RelationControllerInterface
	{
		return $this->relationOf($table, $name)->getController();
	}

	private function relationOf(string $table, string $name): \Gobl\DBAL\Relations\Relation
	{
		$relation = $this->table($table)->getRelation($name);

		self::assertNotNull($relation, \sprintf('the relation %s of %s', $name, $table));

		return $relation;
	}
}
