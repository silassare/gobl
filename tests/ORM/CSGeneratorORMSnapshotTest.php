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

use Gobl\ORM\Generators\CSGeneratorORM;
use Gobl\Tests\BaseTestCase;

/**
 * Class CSGeneratorORMSnapshotTest.
 *
 * Snapshots the PHP files produced by CSGeneratorORM for the full test schema
 * (clients / accounts / currencies / transactions).
 *
 * Only Base/ files are snapshotted because they are always overwritten by the
 * generator. User-facing files (non-base) are only written once and may be
 * edited by users, so snapshotting them is less useful.
 *
 * To regenerate a snapshot, delete the corresponding .txt fixture and re-run.
 *
 * @covers \Gobl\ORM\Generators\CSGeneratorORM
 *
 * @internal
 */
final class CSGeneratorORMSnapshotTest extends BaseTestCase
{
	/**
	 * Generates ORM PHP files into a dedicated temp dir, then snapshots
	 * every Base/ file individually.
	 *
	 * Running this twice in a row must produce identical output.
	 */
	public function testORMBaseFilesSnapshot(): void
	{
		$db     = self::getNewDbInstanceWithSchema();
		$outDir = GOBL_TEST_OUTPUT . \DIRECTORY_SEPARATOR . 'generators' . \DIRECTORY_SEPARATOR . 'orm';

		// Always wipe so we get a clean generation
		self::rmDirRecursive($outDir);
		\mkdir($outDir, 0755, true);

		(new CSGeneratorORM($db))->generate($db->getTables(), $outDir);

		$baseDir = $outDir . \DIRECTORY_SEPARATOR . 'Base';
		$files   = self::scanFiles($baseDir);

		self::assertNotEmpty($files, 'No Base files generated');

		foreach ($files as $absPath) {
			$relative = \str_replace($outDir . \DIRECTORY_SEPARATOR, '', $absPath);
			// Normalize directory separator to forward slash for snapshot key
			$snapshotKey = 'generators/orm/' . \str_replace(\DIRECTORY_SEPARATOR, '/', $relative);
			$content     = (string) \file_get_contents($absPath);
			// ORM files contain no timestamps, but normalize anyway for future safety
			$this->assertMatchesContentSnapshot($snapshotKey, $content);
		}
	}
}
