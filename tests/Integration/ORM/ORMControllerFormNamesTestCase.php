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

use Gobl\CRUD\Exceptions\CRUDException;
use Gobl\DBAL\Builders\TableBuilder;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Operator;
use Gobl\ORM\Exceptions\ORMQueryException;
use Gobl\ORM\Generators\CSGeneratorORM;
use Gobl\ORM\ORM;
use Gobl\ORM\ORMOptions;
use Gobl\Tests\BaseTestCase;
use Throwable;

/**
 * A form names a column by its name (`code`) or its full name (`probe_code`): the controller writes
 * either, and guards a private column whichever name the form gives it. A private column is the
 * server's: its own queries filter on it, a request's filters, order or cursor do not.
 *
 * The table lives in its own namespace, so the shared test schema (and the snapshots built from it)
 * stays untouched.
 */
abstract class ORMControllerFormNamesTestCase extends BaseTestCase
{
	protected const NAMESPACE = 'Gobl\Tests\DbFormNames';

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

		$out_dir = GOBL_TEST_ORM_OUTPUT . \DIRECTORY_SEPARATOR . 'FormNames';

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
				->table('named_probes', static function (TableBuilder $tb): void {
					$tb->plural('named_probes')
						->singular('named_probe')
						->columnPrefix('probe');

					$tb->id();
					$tb->string('code')->max(60);
					$tb->string('label')->max(60)->nullable();
					$tb->bool('trusted')->default(false);
					$tb->useColumn('trusted')->setPrivate();
					$tb->softDeletable();
				});

			$db->ns(static::NAMESPACE)->enableORM($out_dir);

			(new CSGeneratorORM($db))->generate($db->getTables(static::NAMESPACE), $out_dir);

			$db->lock();
			$db->executeMulti($db->getGenerator()->buildDatabase());

			static::$db = $db;
		} catch (Throwable $t) {
			static::$setupFailed = true;
			gobl_log(\sprintf(
				'Error setting up the form names DB for %s: %s',
				static::getDriverName(),
				$t->getMessage()
			));
		}
	}

	public static function tearDownAfterClass(): void
	{
		if (null !== static::$db) {
			try {
				static::$db->executeMulti('DROP TABLE IF EXISTS named_probes;');
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

	public function testAFormByColumnNamesCreatesTheRow(): void
	{
		$ctrl    = ORM::ctrl(static::$db->getTableOrFail('named_probes'));
		$created = $ctrl->addItem(['code' => 'by-name', 'label' => 'short']);
		$entity  = $ctrl->getItem(ORMOptions::makeFromFilters(['probe_id' => $created->id]));

		self::assertNotNull($entity);
		self::assertSame('by-name', (string) $entity->code);
		self::assertSame('short', (string) $entity->label);
	}

	public function testAColumnNamedTwiceIsRefused(): void
	{
		$ctrl = ORM::ctrl(static::$db->getTableOrFail('named_probes'));

		$this->expectException(ORMQueryException::class);
		$this->expectExceptionMessage('GOBL_ORM_REQUEST_FIELD_GIVEN_TWICE');

		$ctrl->addItem(['code' => 'one', 'probe_code' => 'two']);
	}

	/**
	 * @dataProvider provideAPrivateColumnIsGuardedByEitherNameCases
	 */
	public function testAPrivateColumnIsGuardedByEitherName(string $name): void
	{
		$ctrl = ORM::ctrl(static::$db->getTableOrFail('named_probes'));

		try {
			$ctrl->addItem(['code' => 'guarded', $name => true]);
			self::fail('writing a private column without a listener allowing it must be refused');
		} catch (CRUDException $e) {
			self::assertSame('column_is_private', $e->getData(true)['_why'] ?? null);
		}
	}

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function provideAPrivateColumnIsGuardedByEitherNameCases(): iterable
	{
		yield 'its name' => ['trusted'];

		yield 'its full name' => ['probe_trusted'];
	}

	public function testAFormCannotCreateADeletedRow(): void
	{
		$ctrl = ORM::ctrl(static::$db->getTableOrFail('named_probes'));

		try {
			$ctrl->addItem(['code' => 'born-deleted', 'deleted' => true]);
			self::fail('a form must not write the soft delete columns');
		} catch (CRUDException $e) {
			self::assertSame('column_is_private', $e->getData(true)['_why'] ?? null);
		}
	}

	public function testTheSoftDeleteStillWritesItsColumns(): void
	{
		$ctrl    = ORM::ctrl(static::$db->getTableOrFail('named_probes'));
		$created = $ctrl->addItem(['code' => 'to-delete']);
		$filters = ['probe_id' => $created->id];
		$deleted = $ctrl->deleteOneItem(ORMOptions::makeFromFilters($filters), true);

		self::assertNotNull($deleted);
		self::assertNull($ctrl->getItem(ORMOptions::makeFromFilters($filters)), 'a deleted row is not read');
		self::assertArrayNotHasKey('probe_deleted', $deleted->toArray());
	}

	public function testAnEntityWritesItsPrivateColumns(): void
	{
		$table  = static::$db->getTableOrFail('named_probes');
		$entity = ORM::entity($table);

		// The server's own write: a new row of a soft-deletable table holds `deleted`, and the
		// entity sets a private column.
		$entity->code    = 'own-write';
		$entity->trusted = true;

		self::assertTrue($entity->save());

		$loaded = ORM::ctrl($table)->getItem(ORMOptions::makeFromFilters(['probe_id' => $entity->id]));

		self::assertNotNull($loaded);
		self::assertTrue((bool) $loaded->trusted);

		$loaded->trusted = false;

		self::assertTrue($loaded->save());
		self::assertFalse((bool) $loaded->trusted);
	}

	public function testTheServerFiltersOnAPrivateColumn(): void
	{
		$table = static::$db->getTableOrFail('named_probes');

		ORM::ctrl($table)->addItem(['code' => 'server-side']);

		$found = ORM::query($table)
			->filterBy('probe_trusted', Operator::EQ, false)
			->filterBy('probe_deleted', Operator::EQ, false)
			->filterBy('probe_code', Operator::EQ, 'server-side')
			->find()
			->fetchClass();

		self::assertNotNull($found);
	}

	public function testARequestUsesAPublicColumn(): void
	{
		$ctrl = ORM::ctrl(static::$db->getTableOrFail('named_probes'));

		$ctrl->addItem(['code' => 'shown']);

		self::assertSame(1, $ctrl->getAllItems(ORMOptions::makeFromFilters(['probe_code', 'eq', 'shown']))->count());
		self::assertGreaterThan(0, $ctrl->getAllItems(ORMOptions::makePaginated(10, 1, ['probe_code' => 'ASC']))->count());
		self::assertGreaterThan(0, $ctrl->getAllItems(ORMOptions::makeCursorBased('probe_code', 10))->count());
	}

	/**
	 * Filtering, sorting or paging on a column tells its values: a request may not on a private one.
	 *
	 * @dataProvider provideARequestCannotUseAPrivateColumnCases
	 */
	public function testARequestCannotUseAPrivateColumn(ORMOptions $options, string $message): void
	{
		$ctrl = ORM::ctrl(static::$db->getTableOrFail('named_probes'));

		$ctrl->addItem(['code' => 'hidden']);

		$this->expectException(ORMQueryException::class);
		$this->expectExceptionMessage($message);

		$ctrl->getAllItems($options);
	}

	/**
	 * @return iterable<string, array{ORMOptions, string}>
	 */
	public static function provideARequestCannotUseAPrivateColumnCases(): iterable
	{
		yield 'a filter' => [ORMOptions::makeFromFilters(['probe_trusted', 'eq', true]), 'Failed to apply filters'];

		yield 'an order' => [ORMOptions::makePaginated(10, 1, ['probe_trusted' => 'ASC']), 'GOBL_ORM_REQUEST_INVALID_ORDER_BY'];

		yield 'a cursor' => [ORMOptions::makeCursorBased('probe_deleted_at', 10), 'GOBL_ORM_REQUEST_INVALID_CURSOR_COLUMN'];
	}

	public function testAnUpdateByColumnNamesWritesTheRow(): void
	{
		$ctrl    = ORM::ctrl(static::$db->getTableOrFail('named_probes'));
		$created = $ctrl->addItem(['probe_code' => 'to-update', 'probe_label' => 'before']);
		$options = ORMOptions::makeFromFilters(['probe_id' => $created->id])->setFormData(['label' => 'after']);

		// The form's column names used to be dropped, leaving nothing to update.
		$updated = $ctrl->updateOneItem($options);

		self::assertNotNull($updated);
		self::assertSame('after', (string) $updated->label);
		self::assertSame(1, $ctrl->updateAllItems(
			ORMOptions::makeFromFilters(['probe_id' => $created->id])->setFormData(['label' => 'again'])
		));
	}

	abstract protected static function getDriverName(): string;
}
