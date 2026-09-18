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
use Gobl\Tests\Fixtures\RegistryCheckedType;
use Throwable;

/**
 * Saving an entity that was loaded from the database must not validate the values it already holds.
 *
 * `ORMEntity::save()` takes back the row the database returns. Those values are not user input, and a
 * validation that reads a registry rejects them: the value is registered, by this very row. Before that
 * was fixed, saving a user to change its password failed with "email already registered".
 *
 * The table lives in its own namespace, with a type whose validation reads a registry, so the shared
 * test schema (and the snapshots built from it) stays untouched.
 */
abstract class ORMEntitySaveRevalidationTestCase extends BaseTestCase
{
	protected const NAMESPACE = 'Gobl\Tests\DbRevalidation';

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

		$out_dir = GOBL_TEST_ORM_OUTPUT . \DIRECTORY_SEPARATOR . 'Revalidation';

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
				->table('probes', static function (TableBuilder $tb): void {
					$tb->plural('probes')
						->singular('probe')
						->columnPrefix('probe');

					$tb->id();
					$tb->column('code', new RegistryCheckedType());
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
				'Error setting up the revalidation DB for %s: %s',
				static::getDriverName(),
				$t->getMessage()
			));
		}
	}

	public static function tearDownAfterClass(): void
	{
		if (null !== static::$db) {
			try {
				static::$db->executeMulti('DROP TABLE IF EXISTS probes;');
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

		RegistryCheckedType::reset();

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

	public function testSavingALoadedEntityDoesNotValidateItsStoredValues(): void
	{
		$ctrl = ORM::ctrl(static::$db->getTableOrFail('probes'));

		RegistryCheckedType::reset();

		$created = $ctrl->addItem(['probe_code' => 'the-code', 'probe_label' => 'before']);

		// From now on the code is registered, as an email is once its user exists.
		RegistryCheckedType::$registry['the-code'] = true;

		$entity = $ctrl->getItem(ORMOptions::makeFromFilters(['probe_id' => $created->id]));

		self::assertNotNull($entity);

		$validations_before = RegistryCheckedType::$validations;

		$entity->label = 'after';

		self::assertTrue($entity->save(), 'saving a loaded entity must succeed');
		self::assertSame('after', (string) $entity->label);
		self::assertSame('the-code', (string) $entity->code, 'the stored value is kept');
		self::assertSame(
			$validations_before,
			RegistryCheckedType::$validations,
			'a value coming from the database is not validated again'
		);
		self::assertTrue($entity->isSaved());
	}

	public function testAValueTheUserSetsIsStillValidated(): void
	{
		$ctrl = ORM::ctrl(static::$db->getTableOrFail('probes'));

		RegistryCheckedType::reset();

		$created = $ctrl->addItem(['probe_code' => 'another-code', 'probe_label' => 'before']);
		$entity  = $ctrl->getItem(ORMOptions::makeFromFilters(['probe_id' => $created->id]));

		self::assertNotNull($entity);

		RegistryCheckedType::$registry['taken'] = true;

		$this->expectExceptionMessage('VALUE_ALREADY_REGISTERED');

		$entity->code = 'taken';
	}

	abstract protected static function getDriverName(): string;
}
