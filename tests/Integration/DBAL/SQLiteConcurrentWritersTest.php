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

namespace Gobl\Tests\Integration\DBAL;

use Gobl\DBAL\Db;
use Gobl\DBAL\DbConfig;
use Gobl\DBAL\Drivers\SQLite\SQLite;
use Gobl\Tests\BaseTestCase;

/**
 * Processes writing one SQLite file at once, each transaction reading then writing: each waits its
 * turn. Begun with a plain `BEGIN`, two of them met as both wanted to write after reading, and SQLite
 * failed one at once with "database is locked".
 *
 * @internal
 *
 * @coversNothing
 *
 * @group sqlite
 */
final class SQLiteConcurrentWritersTest extends BaseTestCase
{
	private const WRITERS      = 4;
	private const TRANSACTIONS = 15;

	private string $file = '';

	protected function setUp(): void
	{
		parent::setUp();

		$this->file = \tempnam(\sys_get_temp_dir(), 'gobl_writers_');

		Db::newInstanceOf(SQLite::NAME, new DbConfig(['db_host' => $this->file]))
			->executeMulti('CREATE TABLE writes (n INTEGER NOT NULL);');
	}

	protected function tearDown(): void
	{
		@\unlink($this->file);

		parent::tearDown();
	}

	public function testWritersThatReadFirstWaitTheirTurn(): void
	{
		$script    = \dirname(__DIR__, 2) . '/Fixtures/sqlite_writer.php';
		$processes = [];

		for ($i = 0; $i < self::WRITERS; ++$i) {
			$process = \proc_open(
				[\PHP_BINARY, $script, $this->file, (string) self::TRANSACTIONS],
				[1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
				$pipes
			);

			self::assertIsResource($process);

			$processes[] = [$process, $pipes];
		}

		$failures = [];

		foreach ($processes as [$process, $pipes]) {
			\stream_get_contents($pipes[1]);
			$error = (string) \stream_get_contents($pipes[2]);

			\fclose($pipes[1]);
			\fclose($pipes[2]);

			if (0 !== \proc_close($process)) {
				$failures[] = $error;
			}
		}

		self::assertSame([], $failures);

		$count = Db::newInstanceOf(SQLite::NAME, new DbConfig(['db_host' => $this->file]))
			->execute('SELECT COUNT(*) FROM writes')
			->fetchColumn();

		self::assertSame(self::WRITERS * self::TRANSACTIONS, (int) $count);
	}
}
