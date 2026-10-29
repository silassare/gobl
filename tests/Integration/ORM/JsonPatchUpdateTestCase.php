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
use Gobl\DBAL\Events\JsonPatchNotExpressible;
use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Types\Utils\JsonPatch;
use Gobl\ORM\Generators\CSGeneratorORM;
use Gobl\ORM\ORM;
use Gobl\ORM\ORMEntity;
use Gobl\Gobl;
use Gobl\ORM\ORMOptions;
use Gobl\Tests\BaseTestCase;
use Throwable;

/**
 * A patch of a JSON column is sent to the database as the paths it changed.
 *
 * What that buys: the database applies them to the row it holds, so two transactions patching
 * different paths of one column keep each other's writes. Handed a document instead, each would
 * write what it read before the other wrote, and the one that answers last would win.
 */
abstract class JsonPatchUpdateTestCase extends BaseTestCase
{
	protected const string NAMESPACE = 'Gobl\Tests\DbJsonPatch';

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

		$out_dir = GOBL_TEST_ORM_OUTPUT . \DIRECTORY_SEPARATOR . 'JsonPatch';

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
				->table('boxes', static function (TableBuilder $tb): void {
					$tb->plural('boxes')
						->singular('box')
						->columnPrefix('box');

					$tb->id();
					$tb->map('data')->default([]);
					// The same column stored as text instead of the engine's JSON type: the patch is
					// written back as text, where a native column takes the JSON value as it is.
					$tb->map('plain')->nativeJson(false)->default([]);
					// A column that may not be written whole: several requests own part of it.
					$tb->map('guarded')->patchOnly()->default([]);
					$tb->string('label')->max(60)->nullable();
				});

			$db->ns(static::NAMESPACE)->enableORM($out_dir);

			new CSGeneratorORM($db)->generate($db->getTables(static::NAMESPACE), $out_dir);

			$db->lock();
			$db->executeMulti($db->getGenerator()->buildDatabase());

			static::$db = $db;
		} catch (Throwable $t) {
			static::$setupFailed = true;
			gobl_log(\sprintf(
				'Error setting up the JSON patch DB for %s: %s',
				static::getDriverName(),
				$t->getMessage()
			));
		}
	}

	public static function tearDownAfterClass(): void
	{
		if (null !== static::$db) {
			try {
				static::$db->executeMulti('DROP TABLE IF EXISTS boxes;');
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
	}

	/**
	 * Two entities of one row, each patching a path of its own: both writes are kept.
	 */
	public function testConcurrentPatchesOfDifferentPathsKeepEachOther(): void
	{
		$id = $this->addBox(['uid' => 'u1', 'oz' => ['polyglot' => ['favorite' => 'en']]]);

		// Two requests, each holding the row as it was when it read it.
		$first  = $this->getBox($id);
		$second = $this->getBox($id);

		$this->patch($first, static fn (JsonPatch $patch) => $patch->remove('uid'));
		$this->patch($second, static fn (JsonPatch $patch) => $patch->set('oz.polyglot.favorite', 'fr'));

		$data = $this->storedData($id);

		self::assertArrayNotHasKey('uid', $data, 'the removal was undone by the other write');
		self::assertSame('fr', $data['oz']['polyglot']['favorite'] ?? null, 'the other write was lost');
	}

	/**
	 * The entity reads back what the row holds, not the document it patched.
	 */
	public function testAPatchedEntityHoldsTheMergedDocument(): void
	{
		$id = $this->addBox(['a' => 1, 'b' => 2]);

		$entity = $this->getBox($id);

		$this->patch($entity, static fn (JsonPatch $patch) => $patch->set('b', 3));

		self::assertSameDocument(['a' => 1, 'b' => 3], $entity->data->getData());
		self::assertSameDocument(['a' => 1, 'b' => 3], $this->storedData($id));
	}

	/**
	 * A path the document does not have yet: every object above it is made.
	 */
	public function testAPatchMakesTheObjectsAbovePathsItWrites(): void
	{
		$id = $this->addBox([]);

		$entity = $this->getBox($id);

		$this->patch($entity, static fn (JsonPatch $patch) => $patch->set('oz.link_to_cfg.key', ['next' => '/home']));

		self::assertSameDocument(
			['oz' => ['link_to_cfg' => ['key' => ['next' => '/home']]]],
			$this->storedData($id)
		);
	}

	/**
	 * An intermediate that is not an object is replaced, as `set()` does in PHP.
	 */
	public function testAPatchReplacesAnIntermediateThatIsNotAnObject(): void
	{
		$id = $this->addBox(['oz' => 'a string']);

		$entity = $this->getBox($id);

		$this->patch($entity, static fn (JsonPatch $patch) => $patch->set('oz.lang', 'fr'));

		self::assertSameDocument(['oz' => ['lang' => 'fr']], $this->storedData($id));
	}

	/**
	 * Several paths in one patch, and several patches before one save.
	 */
	public function testAllThePathsOfASaveAreWritten(): void
	{
		$id = $this->addBox(['keep' => true, 'drop' => true]);

		$entity = $this->getBox($id);

		$patch = new JsonPatch($entity->data);
		$patch->set('one', 1)
			->remove('drop');
		$entity->data = $patch;

		// A second patch of the same column before the save: its paths are written too.
		$other = new JsonPatch($entity->data);
		$other->set('two.deep', 2);
		$entity->data = $other;

		self::assertTrue($entity->save());

		self::assertSameDocument(
			['keep' => true, 'one' => 1, 'two' => ['deep' => 2]],
			$this->storedData($id)
		);
	}

	/**
	 * Paths that are inside one another cannot be sent as paths: the document is written instead,
	 * and the result is the one the patch computed.
	 */
	public function testPathsInsideOneAnotherStillGiveTheRightDocument(): void
	{
		$id = $this->addBox(['a' => ['b' => 1, 'c' => 2]]);

		$entity = $this->getBox($id);

		$patch = new JsonPatch($entity->data);
		$patch->set('a', ['b' => 9])
			->set('a.c', 3);
		$entity->data = $patch;

		self::assertTrue($entity->save());

		self::assertSameDocument(['a' => ['b' => 9, 'c' => 3]], $this->storedData($id));
	}

	/**
	 * A whole value still replaces the document: nothing about the column changed for a caller that
	 * does not patch.
	 */
	public function testAWholeValueReplacesTheDocument(): void
	{
		$id = $this->addBox(['a' => 1, 'b' => 2]);

		$entity       = $this->getBox($id);
		$entity->data = ['c' => 3];

		self::assertTrue($entity->save());
		self::assertSameDocument(['c' => 3], $this->storedData($id));
	}

	/**
	 * A value given after a patch wins: it answers for the whole column.
	 */
	public function testAWholeValueAfterAPatchWins(): void
	{
		$id = $this->addBox(['a' => 1]);

		$entity = $this->getBox($id);

		$patch = new JsonPatch($entity->data);
		$patch->set('b', 2);
		$entity->data = $patch;
		$entity->data = ['c' => 3];

		self::assertTrue($entity->save());
		self::assertSameDocument(['c' => 3], $this->storedData($id));
	}

	/**
	 * A patch that changes nothing is not a write.
	 */
	public function testAPatchThatChangesNothingIsNotAWrite(): void
	{
		$id = $this->addBox(['a' => 1]);

		$entity = $this->getBox($id);

		$patch = new JsonPatch($entity->data);
		$patch->set('a', 1);
		$entity->data = $patch;

		self::assertTrue($entity->isSaved(), 'the column was dirtied by a patch that changed nothing');
	}

	/**
	 * A boolean is a value a patch takes, through the generated one-liner that builds it.
	 *
	 * Under `strict_types` a `bool` has to be in the signature's union or it is a TypeError, and a
	 * flag is among the first things a session or a preferences document holds.
	 */
	public function testABooleanIsPatchedAsABoolean(): void
	{
		$id = $this->addBox([]);

		$entity = $this->getBox($id);

		// The generated helper: setDataKey() seeds a patch from the column and sets a path in it.
		$entity->setDataKey('flags.seen', true);

		self::assertTrue($entity->save());
		self::assertSameDocument(['flags' => ['seen' => true]], $this->storedData($id));
	}

	/**
	 * A column storing JSON as text is patched like a native one.
	 */
	public function testAColumnStoringJsonAsTextIsPatched(): void
	{
		$id = $this->addBox(['uid' => 'u1']);

		$first  = $this->getBox($id);
		$second = $this->getBox($id);

		$patch = new JsonPatch($first->plain);
		$patch->set('oz.lang', 'fr');
		$first->plain = $patch;
		self::assertTrue($first->save());

		$patch = new JsonPatch($second->plain);
		$patch->remove('uid');
		$second->plain = $patch;
		self::assertTrue($second->save());

		$data = $this->getBox($id)->plain->getData();

		self::assertArrayNotHasKey('uid', $data, 'the removal was undone by the other write');
		self::assertSame('fr', $data['oz']['lang'] ?? null, 'the other write was lost');
	}


	/**
	 * A member whose name is a number, patched in place while another request writes elsewhere.
	 *
	 * This is the shape that cost a partner their uploads: a page editor keeping blocks under their
	 * numeric ids made every path of its patch unexpressible, so the request wrote the whole
	 * document and undid the upload reference another request had just written. Both survive now.
	 */
	public function testAMemberNamedLikeANumberDoesNotTakeTheDocumentWithIt(): void
	{
		$id = $this->addBox([
			'blocks' => ['12' => ['title' => 'before']],
			'refs'   => ['first' => true],
		]);

		$editor   = $this->getBox($id);
		$uploader = $this->getBox($id);

		$this->patch($editor, static fn (JsonPatch $patch) => $patch->set('blocks.12.title', 'after'));
		$this->patch($uploader, static fn (JsonPatch $patch) => $patch->set('refs.second', true));

		$data = $this->storedData($id);

		self::assertSame('after', $data['blocks']['12']['title'] ?? null, 'the editor write was lost');
		self::assertTrue($data['refs']['first'] ?? false, 'the reference already there was lost');
		self::assertTrue($data['refs']['second'] ?? false, 'the concurrent reference was lost');
	}

	/**
	 * An entry of an array, addressed as a position, with a concurrent write kept.
	 */
	public function testAnArrayEntryIsWrittenInPlace(): void
	{
		$id = $this->addBox(['tags' => ['zero', 'one'], 'keep' => true]);

		$first  = $this->getBox($id);
		$second = $this->getBox($id);

		$this->patch($first, static fn (JsonPatch $patch) => $patch->set('tags.1', 'ONE'));
		$this->patch($second, static fn (JsonPatch $patch) => $patch->set('other', 'written'));

		$data = $this->storedData($id);

		self::assertSame(['zero', 'ONE'], $data['tags'] ?? null, 'the entry was not written in place');
		self::assertTrue($data['keep'] ?? false);
		self::assertSame('written', $data['other'] ?? null, 'the concurrent write was lost');
	}

	/**
	 * A patch that makes an array where the row has nothing, and one that makes an object: the
	 * container is made of the kind the path addresses, which is the only way the engines write it.
	 */
	public function testAPatchMakesTheContainerOfTheKindItAddresses(): void
	{
		$id = $this->addBox([]);

		$entity = $this->getBox($id);

		$patch = new JsonPatch($entity->data);
		$patch->setAt(['list', 0], 'entry')
			->setAt(['map', '0'], 'member');
		$entity->data = $patch;

		self::assertTrue($entity->save());

		$data = $this->storedData($id);

		self::assertSame(['entry'], $data['list'] ?? null, 'the array was not made');
		self::assertSame(['0' => 'member'], $data['map'] ?? null, 'the object was not made');
	}

	/**
	 * A whole value, then a patch of the same column before the save: the row holds none of the
	 * value yet, so the paths alone would drop it and the document is written instead.
	 */
	public function testAWholeValueBeforeAPatchIsKept(): void
	{
		$id = $this->addBox(['old' => true]);

		$entity       = $this->getBox($id);
		$entity->data = ['a' => 1];
		$entity->setDataKey('b', 2);

		self::assertTrue($entity->save());
		self::assertSameDocument(['a' => 1, 'b' => 2], $this->storedData($id));
	}

	/**
	 * A patch the paths cannot carry says so, rather than writing the document in silence.
	 */
	public function testAPatchThatCannotBeSentAsPathsIsReported(): void
	{
		$id = $this->addBox(['a' => ['b' => 1]]);

		$entity = $this->getBox($id);

		$reported = [];
		$detach   = JsonPatchNotExpressible::listen(
			static function (JsonPatchNotExpressible $ev) use (&$reported): void {
				$reported[] = $ev;
			}
		);

		try {
			$patch = new JsonPatch($entity->data);
			$patch->set('a', ['b' => 9])
				->set('a.c', 3);
			$entity->data = $patch;

			self::assertTrue($entity->save());
		} finally {
			$detach();
		}

		self::assertCount(1, $reported, 'the fallback to the document was not reported');
		self::assertSame('gObL_boxes', $reported[0]->table_name);
		self::assertSame('box_data', $reported[0]->column_name);
		self::assertStringContainsString('inside one another', $reported[0]->reason);
		self::assertSame(['set a', 'set a.c'], $reported[0]->paths);
	}

	/**
	 * A column declared patch-only refuses the write instead of losing another request's.
	 */
	public function testAPatchOnlyColumnRefusesToBeWrittenWhole(): void
	{
		$id = $this->addBox([]);

		$entity = $this->getBox($id);

		$patch = new JsonPatch($entity->guarded);
		$patch->set('a', ['b' => 9])
			->set('a.c', 3);

		$this->expectException(DBALRuntimeException::class);
		$this->expectExceptionMessageMatches('~box_guarded.+inside one another~');

		$entity->guarded = $patch;
		$entity->save();
	}

	/**
	 * A patch-only column takes a patch that can be sent as paths, which is the point of declaring
	 * it: only the write that would lose another is refused.
	 */
	public function testAPatchOnlyColumnTakesAPatchItCanSendAsPaths(): void
	{
		$id = $this->addBox([]);

		$first  = $this->getBox($id);
		$second = $this->getBox($id);

		// A store keeping entries under a name says so, rather than leaving a digit to be read
		// off a document that has nothing there yet: a string segment is a member, always.
		$patch = new JsonPatch($first->guarded);
		$patch->setAt(['refs', '12'], 'a');
		$first->guarded = $patch;
		self::assertTrue($first->save());

		$patch = new JsonPatch($second->guarded);
		$patch->setAt(['refs', '13'], 'b');
		$second->guarded = $patch;
		self::assertTrue($second->save());

		$data = $this->getBox($id)->guarded->getData();

		self::assertSame(['12' => 'a', '13' => 'b'], $data['refs'] ?? null);
	}


	/**
	 * The paths reach the database.
	 *
	 * The guard for the defect under all of the above: every value on its way to the query builder
	 * went through `phpToDb()`, which for a patch answers the patched **document** -- so the paths
	 * were gone before they could be expressed, and every save wrote the whole document from the
	 * copy the request had read. Nothing in the result showed it, because the row ends up holding
	 * the same thing; what was lost was only the other request's write.
	 *
	 * Asserting on the result cannot catch that, so this asserts on the statement.
	 */
	public function testTheUpdateCarriesThePathsAndNotTheDocument(): void
	{
		$id = $this->addBox(['a' => 1]);

		$entity = $this->getBox($id);

		Gobl::ql()->enable();

		try {
			$this->patch($entity, static fn (JsonPatch $patch) => $patch->set('b.c', 2));

			$updates = \array_filter(
				Gobl::ql()->getLogs(),
				static fn (array $log): bool => \str_contains(\strtoupper((string) ($log['query'] ?? '')), 'UPDATE')
			);
		} finally {
			Gobl::ql()->enable(false);
		}

		self::assertNotEmpty($updates, 'the save ran no UPDATE');

		$sql = (string) \reset($updates)['query'];

		self::assertStringContainsStringIgnoringCase(
			static::getJsonSetFunctionName(),
			$sql,
			'the UPDATE wrote a document instead of the paths: ' . $sql
		);
	}

	/**
	 * An UPDATE writes the columns the entity changed and no others.
	 *
	 * It used to write the whole row, so a save carried every column as the entity had read it and
	 * undid a concurrent write to any other one -- nothing to do with JSON, and the same lost update
	 * on a plain column.
	 */
	public function testASaveLeavesTheColumnsItDidNotChangeAlone(): void
	{
		$id = $this->addBox(['a' => 1]);

		$first  = $this->getBox($id);
		$second = $this->getBox($id);

		// One request renames the box; the other, which read the old name, patches the document.
		$first->label = 'renamed';
		self::assertTrue($first->save());

		$this->patch($second, static fn (JsonPatch $patch) => $patch->set('b', 2));

		$stored = $this->getBox($id);

		self::assertSame('renamed', $stored->label, 'the rename was undone by a save of another column');
		self::assertSameDocument(['a' => 1, 'b' => 2], $stored->data->getData());
	}

	abstract protected static function getDriverName(): string;

	/**
	 * The name of the function this engine writes a path with, as the UPDATE spells it.
	 */
	abstract protected static function getJsonSetFunctionName(): string;

	/**
	 * Two documents hold the same thing, whatever order their keys are in.
	 *
	 * MySQL and PostgreSQL both store an object's members in an order of their own, so a patched
	 * document comes back ordered as the engine keeps it. What a member is named and holds is the
	 * document; the order members are written in is not.
	 */
	private static function assertSameDocument(array $expected, array $actual, string $message = ''): void
	{
		self::assertSame(self::sorted($expected), self::sorted($actual), $message);
	}

	/**
	 * A document with every object's members in one order.
	 */
	private static function sorted(array $data): array
	{
		foreach ($data as $key => $value) {
			if (\is_array($value)) {
				$data[$key] = self::sorted($value);
			}
		}

		if (!\array_is_list($data)) {
			\ksort($data);
		}

		return $data;
	}

	/**
	 * Adds a row and returns its id.
	 */
	private function addBox(array $data): int
	{
		$ctrl = ORM::ctrl(static::$db->getTableOrFail('boxes'));

		$created = $ctrl->addItem([
			'box_data'    => $data,
			'box_plain'   => $data,
			'box_guarded' => $data,
			'box_label'   => 'probe',
		]);

		return (int) $created->id;
	}

	/**
	 * The row as another request would read it.
	 */
	private function getBox(int $id): ORMEntity
	{
		$ctrl   = ORM::ctrl(static::$db->getTableOrFail('boxes'));
		$entity = $ctrl->getItem(ORMOptions::makeFromFilters(['box_id' => $id]));

		self::assertNotNull($entity);

		return $entity;
	}

	/**
	 * Patches an entity's document and saves it.
	 */
	private function patch(ORMEntity $entity, callable $operations): void
	{
		$patch = new JsonPatch($entity->data);

		$operations($patch);

		$entity->data = $patch;

		self::assertTrue($entity->save(), 'the patch was not saved');
	}

	/**
	 * What the row holds, read fresh.
	 */
	private function storedData(int $id): array
	{
		return $this->getBox($id)->data->getData();
	}
}
