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

namespace Gobl\Tests\DBAL\Diff;

use Gobl\DBAL\Db;
use Gobl\DBAL\Diff\Diff;
use Gobl\Tests\BaseTestCase;

/**
 * Class DiffTest.
 *
 * Integration test for the full migration PHP file produced by the Diff engine.
 *
 * For each RDBMS driver the schema in tests/assets/schemas.php is diffed against
 * tests/assets/schema.with.changes.php and the resulting migration PHP file is
 * snapshotted at tests/snapshots/{driver}/diff/schema.diff.php.
 *
 * To regenerate a snapshot, delete its file and re-run the suite once.
 *
 * @covers \Gobl\DBAL\Diff\Diff
 *
 * @internal
 */
final class DiffTest extends BaseTestCase
{
	/**
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testDiff(string $driver): void
	{
		$db_a = self::getNewDbInstanceWithSchema($driver);

		// identical DB must produce no diff
		self::assertSame([], (new Diff($db_a, $db_a))->getDiff());

		$db_b = Db::newInstanceOf($db_a->getType(), $db_a->getConfig());
		$db_b->ns('Test')->schema(self::getTablesDiffDefinitions());
		$db_b->lock();

		$diff   = new Diff($db_a, $db_b);
		$output = (string) $diff->generateMigrationFile(1);

		// Write the raw (unormalized) file so it can be require'd by other tooling
		$actual_file = GOBL_TEST_OUTPUT . '/migration_' . $driver . '_schema_diff.php';
		\file_put_contents($actual_file, $output);

		$normalized    = self::normalizeMigrationContent($output);
		$expected_dir  = GOBL_TEST_SNAPSHOTS . \DIRECTORY_SEPARATOR . $driver . \DIRECTORY_SEPARATOR . 'diff';
		$expected_file = $expected_dir . \DIRECTORY_SEPARATOR . 'schema.diff.php';

		$actual_snap_dir = GOBL_TEST_OUTPUT . '/snapshots/' . $driver . '/diff';

		if (!\is_dir($actual_snap_dir)) {
			\mkdir($actual_snap_dir, 0755, true);
		}

		\file_put_contents($actual_snap_dir . '/schema.diff.php', $normalized);

		if (!\file_exists($expected_file)) {
			if (!\is_dir($expected_dir)) {
				\mkdir($expected_dir, 0755, true);
			}

			\file_put_contents($expected_file, $normalized);
		}

		$snapshot_key     = $driver . '/diff/schema.diff.php';
		$expected_content = \rtrim((string) \file_get_contents($expected_file), \PHP_EOL);

		self::assertSame(
			$expected_content,
			\rtrim($normalized, \PHP_EOL),
			\sprintf('Snapshot mismatch for "%s". To regenerate, delete: %s', $snapshot_key, $expected_file)
		);
	}

	/**
	 * Normalizes dynamic content in a generated migration PHP file so that
	 * snapshots remain stable across runs.
	 *
	 * - Replaces the "Generated on:" date comment with a placeholder.
	 * - Replaces the unix timestamp returned by getTimestamp() with a placeholder.
	 * - Applies the standard content normalizer (ISO datetimes, version strings).
	 *
	 * @param string $content Raw migration file content
	 *
	 * @return string
	 */
	private static function normalizeMigrationContent(string $content): string
	{
		// "Generated on: 4th March 2026, 4:07:35 pm"
		$content = \preg_replace(
			'/(Generated on:) .+/',
			'$1 [GENERATED_AT]',
			$content
		);

		// "return 1772640455;" inside getTimestamp()
		$content = \preg_replace(
			'/\breturn \d{10}+;/',
			'return 0;',
			$content
		);

		return self::normalizeGeneratedContent($content);
	}
}
