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

namespace Gobl\Tests;

use Gobl\Gobl;
use Gobl\QueriesLogger;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Class QueriesLoggerTest.
 *
 * The logger a driver logs into is the one a caller enables and reads back. It answered with a new
 * instance per call, so every query was logged into an object discarded on the next line and
 * nothing could turn the logging on from outside.
 *
 * @internal
 */
#[CoversClass(QueriesLogger::class)]
final class QueriesLoggerTest extends BaseTestCase
{
	protected function tearDown(): void
	{
		QueriesLogger::get()->enable(false);

		parent::tearDown();
	}

	public function testTheLoggerIsOneInstance(): void
	{
		self::assertSame(QueriesLogger::get(), QueriesLogger::get());
	}

	/**
	 * What a driver logs into: `SQLDriverBase` reaches the logger through `Gobl::ql()`.
	 */
	public function testGoblAnswersWithThatSameLogger(): void
	{
		self::assertSame(QueriesLogger::get(), Gobl::ql());
	}

	public function testADisabledLoggerRecordsNothing(): void
	{
		$logger = QueriesLogger::get()->enable(false);

		$logger->start('SELECT 1')('end');

		self::assertSame([], $logger->getLogs());
	}

	public function testAnEnabledLoggerRecordsTheQueryAndItsParams(): void
	{
		$logger = QueriesLogger::get()->enable();

		$logger->start('SELECT * FROM t WHERE id = :id', ['id' => 7], ['id' => 1])('end');

		$logs = $logger->getLogs();

		self::assertCount(1, $logs);
		self::assertSame('SELECT * FROM t WHERE id = :id', $logs[0]['query']);
		self::assertSame(['id' => 7], $logs[0]['params']);
		self::assertSame(['id' => 1], $logs[0]['params_types']);
		self::assertArrayHasKey('end', $logs[0]);
		self::assertArrayHasKey('duration', $logs[0]);
	}

	/**
	 * A caller enabling the logger starts from an empty log, whatever ran before it.
	 */
	public function testEnablingStartsFromAnEmptyLog(): void
	{
		$logger = QueriesLogger::get()->enable();

		$logger->start('SELECT 1')('end');

		self::assertCount(1, $logger->getLogs());

		$logger->enable();

		self::assertSame([], $logger->getLogs());
	}
}
