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

namespace Gobl\Tests\DBAL;

use Gobl\DBAL\Builders\TableBuilder;
use Gobl\DBAL\Db;
use Gobl\DBAL\Diff\Diff;
use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Interfaces\MigrationInterface;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\MigrationSchema;
use Gobl\DBAL\Types\TypeEnum;
use Gobl\Tests\BaseTestCase;
use Gobl\Tests\Fixtures\SampleKind;
use stdClass;

/**
 * Class MigrationSchemaTest.
 *
 * A migration is history: loading it must not depend on an enum class that has moved on since.
 *
 * @covers \Gobl\DBAL\Diff\Diff
 * @covers \Gobl\DBAL\MigrationSchema
 * @covers \Gobl\DBAL\Types\TypeEnum
 *
 * @internal
 */
final class MigrationSchemaTest extends BaseTestCase
{
	private const string MISSING_ENUM = 'Gobl\Tests\Missing\RemovedKind';

	public function testAssertPureDataNamesWhereAnObjectIs(): void
	{
		MigrationSchema::assertPureData(['a' => ['b' => [1, 'x', null, true, 1.5]]]);

		$this->expectException(DBALRuntimeException::class);
		$this->expectExceptionMessage('found "stdClass" at "schema.a.b.1"');

		MigrationSchema::assertPureData(['a' => ['b' => [1, new stdClass()]]]);
	}

	/**
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testAMigrationFileIsPureDataAndLoadsWithoutItsEnumClass(string $driver): void
	{
		$db_to = self::dbWithEnum($driver);
		$code  = (string) (new Diff(self::getNewDbInstance($driver)->lock(), $db_to))->generateMigrationFile(1);

		self::assertStringNotContainsString('SampleKind::', $code);
		self::assertStringContainsString("'default' => 'video'", $code);
		self::assertStringContainsString("'enum_backing' => 'string'", $code);

		// The class is gone: the file names one that does not exist.
		$code      = \str_replace(\addslashes(SampleKind::class), \addslashes(self::MISSING_ENUM), $code);
		$file      = self::writeMigration($driver . '_detached', $code);
		$migration = require $file;

		self::assertInstanceOf(MigrationInterface::class, $migration);

		$db_old = Db::newInstanceOf($driver, self::getDbConfig($driver))->loadSchema($migration->getSchema());
		$type   = $db_old->getTableOrFail('media')->getColumnOrFail('kind')->getType();

		self::assertInstanceOf(TypeEnum::class, $type);
		self::assertTrue($type->isDetached());
		self::assertSame(['IMAGE' => 'image', 'VIDEO' => 'video'], $type->getEnumCases());

		// The SQL does not depend on the class: a full build is the same, and so a diff finds nothing.
		self::assertSame($db_to->getGenerator()->buildDatabase(), $db_old->getGenerator()->buildDatabase());
		self::assertSame([], (new Diff($db_old, $db_to))->getDiff());
	}

	private static function dbWithEnum(string $driver): RDBMSInterface
	{
		$db = self::getNewDbInstance($driver);

		$db->ns('Test')->table('media', static function (TableBuilder $t): void {
			$t->id();
			$t->enum('kind', SampleKind::class)->default(SampleKind::VIDEO);
		});

		return $db->lock();
	}

	private static function writeMigration(string $name, string $code): string
	{
		$dir = GOBL_TEST_OUTPUT . \DIRECTORY_SEPARATOR . 'migration_schema';

		if (!\is_dir($dir)) {
			\mkdir($dir, 0755, true);
		}

		$file = $dir . \DIRECTORY_SEPARATOR . $name . '_' . \uniqid() . '.php';

		\file_put_contents($file, $code);

		return $file;
	}
}
