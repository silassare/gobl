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

namespace Gobl\Tests\ORM;

use Gobl\DBAL\Builders\TableBuilder;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\ORM\Generators\CSGeneratorDart;
use Gobl\ORM\Generators\CSGeneratorTS;
use Gobl\Tests\BaseTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Class CSGeneratorSingleColumnPrefixTest.
 *
 * The client generators read one column of the table to get the prefix every column of it shares,
 * and the templates write it as the entity's `PREFIX`. A table with a single column is the case
 * where reading the wrong one answers nothing at all.
 *
 * @internal
 */
#[CoversClass(CSGeneratorDart::class)]
#[CoversClass(CSGeneratorTS::class)]
final class CSGeneratorSingleColumnPrefixTest extends BaseTestCase
{
	public function testTSKeepsThePrefixOfASingleColumnTable(): void
	{
		$db  = self::getSingleColumnDb();
		$out = self::outDir('ts_single_column');

		new CSGeneratorTS($db)->generate($db->getTables(), $out);

		$content = (string) \file_get_contents(
			$out . \DIRECTORY_SEPARATOR . 'gobl' . \DIRECTORY_SEPARATOR . 'db'
			. \DIRECTORY_SEPARATOR . 'base' . \DIRECTORY_SEPARATOR . 'TokensEntityBase.ts'
		);

		self::assertStringContainsString("PREFIX = 'token'", $content);
		self::assertStringNotContainsString("PREFIX = ''", $content);
	}

	public function testDartKeepsThePrefixOfASingleColumnTable(): void
	{
		$db  = self::getSingleColumnDb();
		$out = self::outDir('dart_single_column');

		new CSGeneratorDart($db)->generate($db->getTables(), $out);

		$files = self::scanFiles($out);
		$found = false;

		foreach ($files as $file) {
			if (\str_ends_with($file, 'tokens_entity.dart')) {
				$found   = true;
				$content = (string) \file_get_contents($file);

				self::assertStringContainsString("PREFIX = 'token'", $content);
			}
		}

		self::assertTrue($found, 'The Dart entity file must be generated.');
	}

	/**
	 * A namespace holding one table whose only column is its primary key.
	 */
	private static function getSingleColumnDb(): RDBMSInterface
	{
		$db = self::getNewDbInstance();

		$db->ns('Gobl\Tests\Db\SingleColumn')
			->table('tokens', static function (TableBuilder $t): void {
				$t->columnPrefix('token');
				$t->id();
			});

		return $db->lock();
	}

	private static function outDir(string $name): string
	{
		$dir = GOBL_TEST_OUTPUT . \DIRECTORY_SEPARATOR . 'generators' . \DIRECTORY_SEPARATOR . $name;

		self::rmDirRecursive($dir);
		\mkdir($dir, 0o755, true);

		return $dir;
	}
}
