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
use PHPUtils\Store\Map;
use Throwable;

/**
 * A row the database answers with reaches the entity without going through its properties.
 *
 * A generated column is a virtual property with hooks, and a hook sees the column's PHP write type.
 * A row carries DB-form values, a JSON column's being a string, so letting the driver write entity
 * properties would raise on that column and would quietly coerce the others instead of converting
 * them. The table below has **no column prefix**, which is where the two names meet: `data` is both
 * the property and the column, so this is the shape that breaks if hydration ever writes properties
 * again.
 *
 * Its own namespace, so the shared test schema and the snapshots built from it stay untouched.
 */
abstract class ORMHydrationTestCase extends BaseTestCase
{
	protected const string NAMESPACE = 'Gobl\Tests\DbHydration';

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

		$out_dir = GOBL_TEST_ORM_OUTPUT . \DIRECTORY_SEPARATOR . 'Hydration';

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
				->table('crates', static function (TableBuilder $tb): void {
					$tb->plural('crates')
						->singular('crate');

					// Deliberately no columnPrefix(): the DB column names are the property names.
					$tb->id();
					$tb->string('label')->max(60);
					$tb->string('note')->max(60)->nullable();
					$tb->bool('sealed')->default(false);
					$tb->int('weight')->default(0);
					$tb->map('data');
				});

			$db->ns(static::NAMESPACE)
				->table('boxes', static function (TableBuilder $tb): void {
					$tb->plural('boxes')
						->singular('box')
						->columnPrefix('box');

					$tb->id();
					$tb->string('label')->max(60);
					$tb->map('data');
				});

			$db->ns(static::NAMESPACE)->enableORM($out_dir);

			new CSGeneratorORM($db)->generate($db->getTables(static::NAMESPACE), $out_dir);

			$db->lock();
			$db->executeMulti($db->getGenerator()->buildDatabase());

			static::$db = $db;
		} catch (Throwable $t) {
			static::$setupFailed = true;
			gobl_log(\sprintf(
				'Error setting up the hydration DB for %s: %s',
				static::getDriverName(),
				$t->getMessage()
			));
		}
	}

	public static function tearDownAfterClass(): void
	{
		if (null !== static::$db) {
			try {
				static::$db->executeMulti('DROP TABLE IF EXISTS crates;' . \PHP_EOL . 'DROP TABLE IF EXISTS boxes;');
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
	 * One row, read back through the entity: every column arrives in its PHP form.
	 */
	public function testARowIsReadBackInItsPhpForm(): void
	{
		$this->addCrate(['label' => 'one', 'sealed' => true, 'weight' => 12, 'data' => ['k' => 'v']]);

		$entity = ORM::query(static::$db->getTableOrFail('crates'))
			->whereLabelIs('one')
			->find()
			->fetchClass();

		self::assertNotNull($entity);
		self::assertNotNull($entity->id, 'the primary key came back');
		self::assertSame('one', $entity->label, 'a string column');
		self::assertNull($entity->note, 'a nullable column with no value');
		self::assertTrue($entity->sealed, 'a bool column, not the string the driver answered with');
		self::assertSame(12, $entity->weight, 'an int column, not a numeric string');
		self::assertInstanceOf(Map::class, $entity->data, 'a map column, not the JSON string the driver answered with');
		self::assertSame('v', $entity->data->get('k'));
	}

	/**
	 * A freshly read entity has nothing to save: hydration snapshots the hashes, as the driver path
	 * used to.
	 */
	public function testAReadEntityIsClean(): void
	{
		$this->addCrate(['label' => 'clean', 'data' => []]);

		$entity = ORM::query(static::$db->getTableOrFail('crates'))
			->whereLabelIs('clean')
			->find()
			->fetchClass();

		self::assertNotNull($entity);
		self::assertTrue($entity->isSaved(), 'nothing is dirty after a read');
		self::assertFalse($entity->isNew());
	}

	/**
	 * And it can be written and saved again, so hydration left the dirty tracking in a usable state.
	 *
	 * On the prefixed table, because `save()` filters on the primary key and a column whose full
	 * name is two characters or shorter is never resolved as a filter operand: `id` on a table with
	 * no column prefix is exactly that. What this asserts is about hydration, not about prefixes.
	 */
	public function testAReadEntityCanBeChangedAndSaved(): void
	{
		$table = static::$db->getTableOrFail('boxes');

		ORM::ctrl($table)->addItem(['box_label' => 'before', 'box_data' => ['n' => 1]]);

		$entity = ORM::query($table)->whereLabelIs('before')
			->find()
			->fetchClass();

		self::assertNotNull($entity);
		self::assertTrue($entity->isSaved(), 'nothing is dirty after a read');

		$entity->label = 'after';

		self::assertTrue($entity->save(), 'saving a read entity must succeed');

		$reloaded = ORM::query($table)->whereLabelIs('after')
			->find()
			->fetchClass();

		self::assertNotNull($reloaded);
		self::assertSame('after', $reloaded->label);
		self::assertInstanceOf(Map::class, $reloaded->data);
		self::assertSame(1, $reloaded->data->get('n'), 'the untouched column kept its value');
	}

	/**
	 * Several rows at once, which is the other fetch path.
	 */
	public function testManyRowsAreReadBackInTheirPhpForm(): void
	{
		$this->addCrate(['label' => 'many-a', 'weight' => 1, 'data' => ['i' => 1]]);
		$this->addCrate(['label' => 'many-b', 'weight' => 2, 'data' => ['i' => 2]]);

		$items = ORM::query(static::$db->getTableOrFail('crates'))
			->whereLabelIsLike('many-%')
			->find()
			->fetchAllClass();

		self::assertCount(2, $items);

		foreach ($items as $item) {
			self::assertIsInt($item->weight, 'an int column, not a numeric string');
			self::assertInstanceOf(Map::class, $item->data, 'a map column, not a JSON string');
		}
	}

	/**
	 * A partial projection still marks the entity partial, which the fetch path decides.
	 */
	public function testAPartialProjectionIsDetected(): void
	{
		$this->addCrate(['label' => 'partial', 'data' => []]);

		$table = static::$db->getTableOrFail('crates');

		$full = ORM::query($table)->whereLabelIs('partial')
			->find()
			->fetchClass();

		self::assertNotNull($full);
		self::assertFalse($full->isPartial(), 'a full row is not partial');

		$options = new ORMOptions();
		$options->setExpectedColumns(['id', 'label']);

		$partial = ORM::query($table)->whereLabelIs('partial')
			->find($options)
			->fetchClass();

		self::assertNotNull($partial);
		self::assertTrue($partial->isPartial(), 'a projected row is partial');
		self::assertTrue($partial->isColumnLoaded('label'));
		self::assertFalse($partial->isColumnLoaded('data'));
	}

	abstract protected static function getDriverName(): string;

	/**
	 * Adds a row.
	 *
	 * Rows are told apart by their label, not their id: a column whose full name is two characters
	 * or shorter cannot be used as a filter operand, and `id` on a table with no column prefix is
	 * exactly that. Nothing here depends on filtering by the key.
	 */
	private function addCrate(array $values): void
	{
		ORM::ctrl(static::$db->getTableOrFail('crates'))
			->addItem($values);
	}
}
