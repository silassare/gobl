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

use ArrayObject;
use Gobl\DBAL\Builders\TableBuilder;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\DBAL\Relations\RelationJoinWrites;
use Gobl\DBAL\Relations\RelationOnAttach;
use Gobl\DBAL\Relations\RelationOnRemove;
use Gobl\ORM\Generators\CSGeneratorORM;
use Gobl\ORM\ORM;
use Gobl\ORM\Exceptions\ORMQueryException;
use Gobl\ORM\ORMEntity;
use Gobl\ORM\ORMOptions;
use Gobl\Tests\BaseTestCase;
use Gobl\Tests\Fixtures\ScopedClientCandidates;
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
				$t->belongsTo('open_client')
					->from('rw_clients')
					->usingColumns(['client_id' => 'id'], ['name', 'neq', 'blocked'])
					->candidates();
				$t->belongsTo('scoped_client')
					->from('rw_clients')
					->usingColumns(['client_id' => 'id'])
					->candidates(ScopedClientCandidates::class);
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

			// Tasks may belong to no account, so they can be attached, and moved.
			$ns->table('rw_tasks', static function (TableBuilder $t): void {
				$t->plural('rw_tasks')->singular('rw_task')->columnPrefix('task');
				$t->id();
				$t->foreign('account_id', 'rw_accounts', 'id', true);
				$t->string('state')->max(20);
			});

			$articles = $ns->table('rw_articles', static function (TableBuilder $t): void {
				$t->plural('rw_articles')->singular('rw_article')->columnPrefix('article');
				$t->id();
				$t->string('title')->max(60);
				$t->softDeletable();
			});

			$ns->table('rw_groups', static function (TableBuilder $t): void {
				$t->plural('rw_groups')->singular('rw_group')->columnPrefix('group');
				$t->id();
				$t->string('name')->max(60);
			});

			$ns->table('rw_tags', static function (TableBuilder $t): void {
				$t->plural('rw_tags')->singular('rw_tag')->columnPrefix('tag');
				$t->id();
				$t->string('label')->max(60);
				$t->foreign('group_id', 'rw_groups', 'id', true);
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
				$t->hasMany('owned_transactions')
					->from('rw_transactions')
					->usingColumns()
					->onRemove(RelationOnRemove::DELETE);
				$t->hasMany('tasks')->from('rw_tasks');
				$t->hasMany('claimed_tasks')
					->from('rw_tasks')
					->usingColumns()
					->onAttach(RelationOnAttach::MOVE);
				$t->hasMany('open_tasks')
					->from('rw_tasks')
					->usingColumns([], ['state', 'eq', 'open']);
				$t->hasMany('pending_transactions')
					->from('rw_transactions')
					->usingColumns([], ['state', 'eq', 'pending']);
				$t->hasMany('live_transactions')
					->from('rw_transactions')
					->usingColumns([], ['state', 'neq', 'void']);
			});

			$articles->factory(static function (TableBuilder $t): void {
				$t->hasMany('tags')->from('rw_tags')->through('rw_taggings');
				$t->hasMany('comments')->from('rw_comments')->usingMorph('commentable');
				$t->hasMany('tags_joined')
					->from('rw_tags')
					->usingJoin(['steps' => [['join' => 'rw_taggings', 'link' => ['type' => 'columns']]]]);
				$t->hasMany('groups_joined')
					->from('rw_groups')
					->usingJoin(['steps' => [
						['join' => 'rw_taggings', 'link' => ['type' => 'columns']],
						['join' => 'rw_tags', 'link' => ['type' => 'columns']],
					]]);
				$t->hasMany('tags_joined_writable')
					->from('rw_tags')
					->usingJoin(['steps' => [['join' => 'rw_taggings', 'link' => ['type' => 'columns']]]])
					->joinWrites(RelationJoinWrites::LAST_STEP);
			});

			$ns->enableORM($out_dir);

			new CSGeneratorORM($db)
				->candidatesContextType(ArrayObject::class)
				->generate($db->getTables(static::NAMESPACE), $out_dir);

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
						['rw_comments', 'rw_taggings', 'rw_tags', 'rw_groups', 'rw_articles', 'rw_tasks', 'rw_notes', 'rw_transactions', 'rw_accounts', 'rw_clients']
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
	 * A child created through a relation belongs to the row it was created from: a payload naming
	 * another host is refused, by the column it names, and nothing is created.
	 */
	public function testACreateNamingAnotherHostIsRefused(): void
	{
		$a = $this->add('rw_accounts', ['account_label' => 'a']);
		$b = $this->add('rw_accounts', ['account_label' => 'b']);

		$this->assertRefused('GOBL_ORM_RELATION_FIXES_THIS_FIELD', 'transaction_account_id', function () use ($a, $b): void {
			$this->relation('rw_accounts', 'transactions')
				->create($a, ['transaction_account_id' => $b->id, 'transaction_state' => 'x1']);
		});

		self::assertNull($this->find('rw_transactions', ['transaction_state' => 'x1']));
	}

	/**
	 * Naming the very host the relation fills is not a conflict, though the payload says it as a number and the key is stored as text.
	 */
	public function testACreateNamingItsOwnHostIsAccepted(): void
	{
		$a = $this->add('rw_accounts', ['account_label' => 'a']);

		$this->relation('rw_accounts', 'transactions')
			->create($a, ['transaction_account_id' => (int) $a->id, 'transaction_state' => 'x1b']);

		self::assertSame((string) $a->id, (string) $this->one('rw_transactions', ['transaction_state' => 'x1b'])->account_id);
	}

	/**
	 * Updating a child through a relation edits it and leaves it where it is: moving it to another
	 * host is refused, and the child is unchanged.
	 */
	public function testAnUpdateMovingAChildToAnotherHostIsRefused(): void
	{
		$a = $this->add('rw_accounts', ['account_label' => 'a']);
		$b = $this->add('rw_accounts', ['account_label' => 'b']);
		$t = $this->add('rw_transactions', ['transaction_account_id' => $a->id, 'transaction_state' => 'x2']);

		$this->assertRefused('GOBL_ORM_RELATION_FIXES_THIS_FIELD', 'transaction_account_id', function () use ($a, $b, $t): void {
			$this->relation('rw_accounts', 'transactions')
				->update($a, ['transaction_id' => $t->id, 'transaction_account_id' => $b->id]);
		});

		self::assertSame((string) $a->id, (string) $this->one('rw_transactions', ['transaction_id' => $t->id])->account_id);
	}

	/**
	 * What a relation's equality fixes is not the payload's to change through it either.
	 */
	public function testAnUpdateChangingWhatAFilterFixesIsRefused(): void
	{
		$a = $this->add('rw_accounts', ['account_label' => 'a']);
		$t = $this->add('rw_transactions', ['transaction_account_id' => $a->id, 'transaction_state' => 'pending']);

		$this->assertRefused('GOBL_ORM_RELATION_FIXES_THIS_FIELD', 'transaction_state', function () use ($a, $t): void {
			$this->relation('rw_accounts', 'pending_transactions')
				->update($a, ['transaction_id' => $t->id, 'transaction_state' => 'done']);
		});

		self::assertSame('pending', (string) $this->one('rw_transactions', ['transaction_id' => $t->id])->state);
	}

	/**
	 * A filter that is not an equality cannot be written, so what a write leaves is checked: a row
	 * the relation would not count is refused, and not left behind.
	 */
	public function testACreateTheRelationWouldNotCountIsRefusedAndNotKept(): void
	{
		$a = $this->add('rw_accounts', ['account_label' => 'a']);

		$this->assertRefused('GOBL_ORM_RELATION_WRITE_NOT_A_RELATIVE', null, function () use ($a): void {
			$this->relation('rw_accounts', 'live_transactions')->create($a, ['transaction_state' => 'void']);
		});

		self::assertNull($this->find('rw_transactions', ['transaction_state' => 'void']));
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
	 * A relation filtered on an equality writes that value on what it creates, or what it created
	 * would not be one of its relatives.
	 */
	public function testAFilteredRelationWritesItsEqualityOnWhatItCreates(): void
	{
		$a = $this->add('rw_accounts', ['account_label' => 'a']);

		$this->relation('rw_accounts', 'pending_transactions')->create($a, []);

		$listed = ORM::ctrl($this->table('rw_transactions'))
			->getAllRelatives($a, $this->relationOf('rw_accounts', 'pending_transactions'), new ORMOptions());

		self::assertNotNull($listed);
		self::assertCount(1, \iterator_to_array($listed->getItems(), false));
	}

	/**
	 * Creating the record a row points at (the key on the row's side) creates that record and points
	 * the row at it -- not a record carrying the row's old key as its own.
	 */
	public function testCreatingWhatARowPointsAtPointsTheRowAtIt(): void
	{
		$a = $this->add('rw_accounts', ['account_label' => 'a']);

		$this->relation('rw_accounts', 'client')->create($a, ['client_name' => 'new client']);

		$client = $this->one('rw_clients', ['client_name' => 'new client']);
		$after  = $this->one('rw_accounts', ['account_id' => $a->id]);

		self::assertSame((string) $client->id, (string) $after->client_id);
	}

	/**
	 * Removing what a row points at, through that row, unlinks it: another row still points at the
	 * same record, which must stay.
	 */
	public function testRemovingWhatARowPointsAtKeepsARecordOthersShare(): void
	{
		$c = $this->add('rw_clients', ['client_name' => 'shared']);
		$a = $this->add('rw_accounts', ['account_label' => 'a', 'account_client_id' => $c->id]);

		$this->add('rw_accounts', ['account_label' => 'b', 'account_client_id' => $c->id]);

		$this->relation('rw_accounts', 'client')->delete($a, ['client_id' => $c->id]);

		self::assertNotNull(
			ORM::ctrl($this->table('rw_clients'))->getItem(ORMOptions::makeFromFilters(['client_id' => $c->id])),
			'the client another account points at is still there'
		);
	}

	/**
	 * Creating through a morph relation from the child side creates the parent and points the child
	 * at it, type and key.
	 */
	public function testAMorphChildCreatesItsParentAndPointsAtIt(): void
	{
		$first   = $this->add('rw_articles', ['article_title' => 'first']);
		$comment = $this->add('rw_comments', [
			'comment_body'             => 'c',
			'comment_commentable_id'   => (string) $first->id,
			'comment_commentable_type' => 'rw_articles',
		]);

		$this->relation('rw_comments', 'article')->create($comment, ['article_title' => 'second']);

		$second = $this->one('rw_articles', ['article_title' => 'second']);
		$after  = $this->one('rw_comments', ['comment_id' => $comment->id]);

		self::assertSame((string) $second->id, (string) $after->commentable_id);
	}

	/**
	 * Creating through a relation that goes through a link table creates the record and the link
	 * row that makes it a relative.
	 */
	public function testCreatingThroughALinkTableWritesTheLinkRow(): void
	{
		$article = $this->add('rw_articles', ['article_title' => 'a']);

		$this->relation('rw_articles', 'tags')->create($article, ['tag_label' => 'new tag']);

		$tag = $this->one('rw_tags', ['tag_label' => 'new tag']);

		self::assertNotFalse($this->linked($article->id, $tag->id));
	}

	/**
	 * Removing a relative that goes through a link table removes the link row: the record itself is
	 * shared, and another row still links to it.
	 */
	public function testRemovingThroughALinkTableRemovesTheLinkAndKeepsTheRecord(): void
	{
		$tag   = $this->add('rw_tags', ['tag_label' => 'shared']);
		$one   = $this->add('rw_articles', ['article_title' => 'one']);
		$other = $this->add('rw_articles', ['article_title' => 'other']);

		$this->add('rw_taggings', ['tagging_article_id' => $one->id, 'tagging_tag_id' => $tag->id]);
		$this->add('rw_taggings', ['tagging_article_id' => $other->id, 'tagging_tag_id' => $tag->id]);

		$this->relation('rw_articles', 'tags')->delete($one, ['tag_id' => $tag->id]);

		self::assertNotNull(
			ORM::ctrl($this->table('rw_tags'))->getItem(ORMOptions::makeFromFilters(['tag_id' => $tag->id])),
			'the tag the other article links to is still there'
		);
		self::assertFalse($this->linked($one->id, $tag->id), 'the link of the first article is gone');
	}

	/**
	 * Removing a child whose key cannot be null is refused by default: the link cannot go without
	 * the child, and deleting it is the relation's to declare.
	 */
	public function testRemovingAChildThatCannotBeUnlinkedIsRefusedByDefault(): void
	{
		$a = $this->add('rw_accounts', ['account_label' => 'a']);
		$t = $this->add('rw_transactions', ['transaction_account_id' => $a->id, 'transaction_state' => 'r1']);

		$this->assertRefused('GOBL_ORM_RELATION_CANNOT_UNLINK', 'transaction_account_id', function () use ($a, $t): void {
			$this->relation('rw_accounts', 'transactions')->delete($a, ['transaction_id' => $t->id]);
		});

		self::assertNotNull($this->find('rw_transactions', ['transaction_id' => $t->id]));
	}

	/**
	 * A relation declared to delete what it removes deletes it.
	 */
	public function testARelationDeclaredToDeleteDeletesTheChild(): void
	{
		$a = $this->add('rw_accounts', ['account_label' => 'a']);
		$t = $this->add('rw_transactions', ['transaction_account_id' => $a->id, 'transaction_state' => 'r2']);

		$this->relation('rw_accounts', 'owned_transactions')->delete($a, ['transaction_id' => $t->id]);

		self::assertNull($this->find('rw_transactions', ['transaction_id' => $t->id]));
	}

	/**
	 * Removing a child whose key may be null unlinks it, and keeps it.
	 */
	public function testRemovingAChildThatMayBeUnlinkedKeepsIt(): void
	{
		$a    = $this->add('rw_accounts', ['account_label' => 'a']);
		$task = $this->add('rw_tasks', ['task_account_id' => $a->id, 'task_state' => 'open']);

		$this->relation('rw_accounts', 'tasks')->delete($a, ['task_id' => $task->id]);

		self::assertNull($this->one('rw_tasks', ['task_id' => $task->id])->account_id);
	}

	/**
	 * Attaching a child no row holds points it at the host, and only that.
	 */
	public function testAttachingAFreeChildPointsItAtTheHost(): void
	{
		$a    = $this->add('rw_accounts', ['account_label' => 'a']);
		$task = $this->add('rw_tasks', ['task_state' => 'open']);

		$this->relation('rw_accounts', 'tasks')->attach($a, ['task_id' => $task->id]);

		self::assertSame((string) $a->id, (string) $this->one('rw_tasks', ['task_id' => $task->id])->account_id);
	}

	/**
	 * A child another row holds is not taken by default, and is moved where the relation says so.
	 */
	public function testAttachingAChildHeldElsewhereIsRefusedUnlessDeclaredToMove(): void
	{
		$a    = $this->add('rw_accounts', ['account_label' => 'a']);
		$b    = $this->add('rw_accounts', ['account_label' => 'b']);
		$task = $this->add('rw_tasks', ['task_account_id' => $b->id, 'task_state' => 'open']);

		$this->assertRefused('GOBL_ORM_RELATION_ATTACH_HELD_ELSEWHERE', null, function () use ($a, $task): void {
			$this->relation('rw_accounts', 'tasks')->attach($a, ['task_id' => $task->id]);
		});

		self::assertSame((string) $b->id, (string) $this->one('rw_tasks', ['task_id' => $task->id])->account_id);

		$this->relation('rw_accounts', 'claimed_tasks')->attach($a, ['task_id' => $task->id]);

		self::assertSame((string) $a->id, (string) $this->one('rw_tasks', ['task_id' => $task->id])->account_id);
	}

	/**
	 * What a relation's filters ask is checked on attach, not written: a record that is not one of
	 * its relatives is refused, and left as it was.
	 */
	public function testAttachingARecordTheRelationWouldNotCountIsRefusedAndRolledBack(): void
	{
		$a    = $this->add('rw_accounts', ['account_label' => 'a']);
		$task = $this->add('rw_tasks', ['task_state' => 'closed']);

		$this->assertRefused('GOBL_ORM_RELATION_WRITE_NOT_A_RELATIVE', null, function () use ($a, $task): void {
			$this->relation('rw_accounts', 'open_tasks')->attach($a, ['task_id' => $task->id]);
		});

		$after = $this->one('rw_tasks', ['task_id' => $task->id]);

		self::assertNull($after->account_id);
		self::assertSame('closed', (string) $after->state);
	}

	/**
	 * Attaching through a link table writes the link row, once.
	 */
	public function testAttachingThroughALinkTableWritesOneLinkRow(): void
	{
		$article = $this->add('rw_articles', ['article_title' => 'a']);
		$tag     = $this->add('rw_tags', ['tag_label' => 'existing']);

		$this->relation('rw_articles', 'tags')->attach($article, ['tag_id' => $tag->id]);
		$this->relation('rw_articles', 'tags')->attach($article, ['tag_id' => $tag->id]);

		$links = ORM::ctrl($this->table('rw_taggings'))->getAllItems(ORMOptions::makeFromFilters([
			'tagging_article_id' => $article->id,
			'tagging_tag_id'     => $tag->id,
		]));

		self::assertCount(1, \iterator_to_array($links->getItems(), false));
	}

	/**
	 * Attaching what a row points at points the row at it.
	 */
	public function testAttachingWhatARowPointsAtPointsTheRow(): void
	{
		$client = $this->add('rw_clients', ['client_name' => 'existing']);
		$a      = $this->add('rw_accounts', ['account_label' => 'a']);

		$this->relation('rw_accounts', 'client')->attach($a, ['client_id' => $client->id]);

		self::assertSame((string) $client->id, (string) $this->one('rw_accounts', ['account_id' => $a->id])->client_id);
	}

	/**
	 * A join relation is read from a row: a tag linked to an article through the link table is
	 * found through the join, as through the relation that goes through that table.
	 */
	public function testAJoinRelationIsReadFromARow(): void
	{
		$article = $this->add('rw_articles', ['article_title' => 'probe']);

		$tag = $this->add('rw_tags', ['tag_label' => 'probe']);

		$this->add('rw_taggings', ['tagging_article_id' => $article->id, 'tagging_tag_id' => $tag->id]);

		$found = ORM::ctrl($this->table('rw_tags'))
			->getRelative($article, $this->relationOf('rw_articles', 'tags_joined'), ORMOptions::makeFromFilters(['tag_id' => $tag->id]));

		self::assertNotNull($found);
	}

	/**
	 * A join of three steps is read from a row too: an article's tag groups, through its links and
	 * its tags, and not the group of a tag it does not carry.
	 */
	public function testAJoinOfThreeStepsIsReadFromARow(): void
	{
		$mine   = $this->add('rw_groups', ['group_name' => 'mine']);
		$other  = $this->add('rw_groups', ['group_name' => 'other']);
		$tag    = $this->add('rw_tags', ['tag_label' => 'grouped', 'tag_group_id' => $mine->id]);
		$stray  = $this->add('rw_tags', ['tag_label' => 'stray', 'tag_group_id' => $other->id]);
		$article = $this->add('rw_articles', ['article_title' => 'three steps']);

		$this->add('rw_taggings', ['tagging_article_id' => $article->id, 'tagging_tag_id' => $tag->id]);

		$found = ORM::ctrl($this->table('rw_groups'))
			->getAllRelatives($article, $this->relationOf('rw_articles', 'groups_joined'), new ORMOptions());

		self::assertNotNull($found);

		$names = \array_map(
			static fn (ORMEntity $group): string => (string) $group->name,
			\iterator_to_array($found->getItems(), false)
		);

		self::assertSame(['mine'], $names);
		self::assertNotNull($stray);
	}

	/**
	 * A join writes nothing unless it declares so.
	 */
	public function testAJoinRefusesWritesByDefault(): void
	{
		$article = $this->add('rw_articles', ['article_title' => 'a']);

		$this->assertRefused('GOBL_ORM_RELATION_REFUSES_WRITES', null, function () use ($article): void {
			$this->relation('rw_articles', 'tags_joined')->create($article, ['tag_label' => 'refused']);
		});

		self::assertNull($this->find('rw_tags', ['tag_label' => 'refused']));
	}

	/**
	 * A join of two steps declared to write its last one is written as a relation through that link
	 * table is.
	 */
	public function testAJoinDeclaredToWriteItsLastStepWritesTheLinkRow(): void
	{
		$article = $this->add('rw_articles', ['article_title' => 'a']);

		$this->relation('rw_articles', 'tags_joined_writable')->create($article, ['tag_label' => 'joined']);

		$tag = $this->one('rw_tags', ['tag_label' => 'joined']);

		self::assertNotFalse($this->linked($article->id, $tag->id));
	}

	/**
	 * A relation whose filters are about its target finds its candidates by itself: every client
	 * but the one its filter excludes.
	 */
	public function testARelationFindsItsOwnCandidates(): void
	{
		$this->add('rw_clients', ['client_name' => 'open one']);
		$this->add('rw_clients', ['client_name' => 'blocked']);

		$account = $this->add('rw_accounts', ['account_label' => 'a']);
		$names   = $this->candidateNames($account, 'open_client', new ORMOptions());

		self::assertContains('open one', $names);
		self::assertNotContains('blocked', $names);

		// A search the picker sends narrows them further.
		$options = ORMOptions::makeFromFilters(['client_name' => 'open one']);

		self::assertSame(['open one'], \array_values(\array_unique($this->candidateNames($account, 'open_client', $options))));
	}

	/**
	 * A provider narrows them with the caller's context, and is handed the context class generated
	 * for its relation.
	 */
	public function testAProviderNarrowsTheCandidatesWithTheCallersContext(): void
	{
		$this->add('rw_clients', ['client_name' => 'wanted']);
		$this->add('rw_clients', ['client_name' => 'not wanted']);

		$account = $this->add('rw_accounts', ['account_label' => 'a']);

		ScopedClientCandidates::forget();

		$names = $this->candidateNames($account, 'scoped_client', new ORMOptions(), new ArrayObject(['name' => 'wanted']));

		self::assertSame(['wanted'], \array_values(\array_unique($names)));
		self::assertSame(
			[\ltrim(\Gobl\ORM\Utils\ORMCandidatesClass::fqn($this->relationOf('rw_accounts', 'scoped_client')), '\\')],
			ScopedClientCandidates::handed()
		);
	}

	/**
	 * The generated context types what the provider reads: the target's query, the host's entity,
	 * and the caller's context as the generation was told.
	 */
	public function testTheGeneratedContextIsTypedForItsRelation(): void
	{
		$class = \ltrim(\Gobl\ORM\Utils\ORMCandidatesClass::fqn($this->relationOf('rw_accounts', 'scoped_client')), '\\');

		self::assertTrue(\class_exists($class));

		$type = static fn (string $method): string => (string) new \ReflectionMethod($class, $method)->getReturnType();

		self::assertSame(\ltrim(\Gobl\ORM\Utils\ORMClassKind::QUERY->getClassFQN($this->table('rw_clients')), '\\'), $type('selector'));
		self::assertSame(\ltrim(\Gobl\ORM\Utils\ORMClassKind::ENTITY->getClassFQN($this->table('rw_accounts')), '\\'), $type('host'));
		self::assertSame(ArrayObject::class, $type('context'));
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
		$found = $this->find($table, $filters);

		self::assertNotNull($found, \sprintf('a row of %s matching the filters', $table));

		return $found;
	}

	/**
	 * @param array<string, mixed> $filters
	 */
	private function find(string $table, array $filters): ?ORMEntity
	{
		return ORM::ctrl($this->table($table))->getItem(ORMOptions::makeFromFilters($filters));
	}

	/**
	 * Runs a write expected to be refused with this message, naming this field when one is given.
	 *
	 * @param callable():void $write
	 */
	private function assertRefused(string $message, ?string $field, callable $write): void
	{
		try {
			$write();
		} catch (ORMQueryException $e) {
			self::assertSame($message, $e->getMessage());

			if (null !== $field) {
				self::assertSame($field, $e->getData()['field'] ?? null);
			}

			return;
		}

		self::fail(\sprintf('The write was not refused with %s.', $message));
	}

	/**
	 * The names of a relation's candidates for this account.
	 *
	 * @return list<string>
	 */
	private function candidateNames(ORMEntity $host, string $relation, ORMOptions $options, mixed $context = null): array
	{
		$found = ORM::ctrl($this->table('rw_clients'))
			->getCandidates($this->relationOf('rw_accounts', $relation), $host, $options, $context);

		return \array_map(
			static fn (ORMEntity $client): string => (string) $client->name,
			\iterator_to_array($found->getItems(), false)
		);
	}

	/** The link row between an article and a tag, or `false`. */
	private function linked(mixed $article_id, mixed $tag_id): mixed
	{
		return $this->row('rw_taggings', ['tagging_article_id' => $article_id, 'tagging_tag_id' => $tag_id]);
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
