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

use Gobl\ORM\Generators\CSGenerator;
use Gobl\ORM\Generators\CSGeneratorDart;
use Gobl\ORM\Generators\CSGeneratorTS;
use Gobl\Tests\BaseTestCase;

/**
 * Class CSGeneratorClientSnapshotTest.
 *
 * Snapshot tests for CSGeneratorTS and CSGeneratorDart output.
 * Uses the full test schema (clients / accounts / currencies / transactions).
 *
 * All generated files are captured (output dir is wiped before each run so
 * even the overwrite=false user-editable files are snapshotted on re-run).
 *
 * Timestamps and version strings are normalized via normalizeGeneratedContent()
 * so snapshots remain stable across test runs.
 *
 * To regenerate a snapshot, delete the corresponding .txt fixture and re-run.
 *
 * @covers \Gobl\ORM\Generators\CSGeneratorDart
 * @covers \Gobl\ORM\Generators\CSGeneratorTS
 *
 * @internal
 */
final class CSGeneratorClientSnapshotTest extends BaseTestCase
{
	// ------------------------------------------------------------------
	// TypeScript
	// ------------------------------------------------------------------

	/**
	 * Snapshots every TypeScript file produced by CSGeneratorTS.
	 * Timestamps and version strings are normalized.
	 */
	public function testTSFilesSnapshot(): void
	{
		$outDir    = GOBL_TEST_OUTPUT . \DIRECTORY_SEPARATOR . 'generators' . \DIRECTORY_SEPARATOR . 'ts';
		$generator = new CSGeneratorTS(self::getNewDbInstanceWithSchema());

		$this->assertGeneratorSnapshot($generator, $outDir, 'generators/ts');
	}

	// ------------------------------------------------------------------
	// Dart
	// ------------------------------------------------------------------

	/**
	 * Snapshots every Dart file produced by CSGeneratorDart.
	 * Timestamps and version strings are normalized.
	 */
	public function testDartFilesSnapshot(): void
	{
		$outDir    = GOBL_TEST_OUTPUT . \DIRECTORY_SEPARATOR . 'generators' . \DIRECTORY_SEPARATOR . 'dart';
		$generator = new CSGeneratorDart(self::getNewDbInstanceWithSchema());

		$this->assertGeneratorSnapshot($generator, $outDir, 'generators/dart');
	}

	/**
	 * Generates all files into $outDir (after wiping it) using $generator,
	 * then snapshots each file under the snapshot prefix $prefix.
	 *
	 * @param CSGenerator $generator
	 * @param string      $outDir
	 * @param string      $prefix
	 */
	private function assertGeneratorSnapshot(
		CSGenerator $generator,
		string $outDir,
		string $prefix
	): void {
		$db = self::getNewDbInstanceWithSchema();

		self::rmDirRecursive($outDir);
		\mkdir($outDir, 0755, true);

		$generator->generate($db->getTables(), $outDir);

		$files = self::scanFiles($outDir);
		self::assertNotEmpty($files, "No files generated in {$outDir}");

		foreach ($files as $absPath) {
			$relative    = \ltrim(\str_replace($outDir, '', $absPath), \DIRECTORY_SEPARATOR);
			$snapshotKey = $prefix . '/' . \str_replace(\DIRECTORY_SEPARATOR, '/', $relative);
			$content     = (string) \file_get_contents($absPath);
			$this->assertMatchesContentSnapshot($snapshotKey, $content);
		}
	}
}
