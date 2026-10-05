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

/*
 * One writer of SQLiteConcurrentWritersTest, run as its own process: each of its transactions reads,
 * then writes, as a sign-up checks an email then inserts the user.
 *
 * Usage: php sqlite_writer.php <database file> <transactions>
 */

use Gobl\DBAL\Db;
use Gobl\DBAL\DbConfig;
use Gobl\DBAL\Drivers\SQLite\SQLite;

require __DIR__ . '/../../vendor/autoload.php';

[, $file, $count] = $argv;

$db = Db::newInstanceOf(SQLite::NAME, new DbConfig(['db_host' => $file]));

try {
	for ($i = 0; $i < (int) $count; ++$i) {
		$db->runInTransaction(static function () use ($db, $i): void {
			$db->execute('SELECT COUNT(*) FROM writes')->fetchColumn();
			$db->execute('INSERT INTO writes (n) VALUES (?)', [$i]);
		});
	}
} catch (Throwable $t) {
	\fwrite(\STDERR, $t->getMessage());

	exit(1);
}
