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
use Gobl\DBAL\Types\Utils\JsonPatch;
use Gobl\ORM\Generators\CSGeneratorORM;
use Gobl\ORM\ORM;
use Gobl\ORM\ORMEntity;
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
	protected const NAMESPACE = 'Gobl\Tests\DbJsonPatch';

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
					$tb->string('label')->max(60)->nullable();
				});

			$db->ns(static::NAMESPACE)->enableORM($out_dir);

			(new CSGeneratorORM($db))->generate($db->getTables(static::NAMESPACE), $out_dir);

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

	abstract protected static function getDriverName(): string;

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

		$created = $ctrl->addItem(['box_data' => $data, 'box_plain' => $data, 'box_label' => 'probe']);

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
