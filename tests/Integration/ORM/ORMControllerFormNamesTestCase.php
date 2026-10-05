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
use Gobl\CRUD\Exceptions\CRUDException;
use Gobl\ORM\Exceptions\ORMQueryException;
use Gobl\Tests\BaseTestCase;
use Throwable;

/**
 * A form names a column by its name (`code`) or its full name (`probe_code`): the controller writes
 * either, and guards a private column whichever name the form gives it.
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
	 * @return iterable<string, array{string}>
	 */
	public static function providePrivateColumnNames(): iterable
	{
		yield 'its name' => ['trusted'];

		yield 'its full name' => ['probe_trusted'];
	}

	/**
	 * @dataProvider providePrivateColumnNames
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
