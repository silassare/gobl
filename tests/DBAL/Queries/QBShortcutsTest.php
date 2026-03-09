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

namespace Gobl\Tests\DBAL\Queries;

use Gobl\DBAL\Builders\TableBuilder;
use Gobl\DBAL\Queries\QBExpression;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\DBAL\Queries\Traits\QBCommonTrait;
use Gobl\Tests\BaseTestCase;

/**
 * Class QBShortcutsTest.
 *
 * Tests for the shorthand expression helpers defined in {@see QBCommonTrait}:
 * expr(), fn(), col(), sqlNull(), sqlTrue(), sqlFalse(), sub(), and quote().
 *
 * @covers \Gobl\DBAL\Queries\Traits\QBShortcutsTrait
 *
 * @internal
 */
final class QBShortcutsTest extends BaseTestCase
{
	// -------------------------------------------------------------------------
	// expr()
	// -------------------------------------------------------------------------

	public function testExprReturnsQBExpression(): void
	{
		$qb   = self::makeQB();
		$expr = $qb->expr('NOW()');

		self::assertInstanceOf(QBExpression::class, $expr);
		self::assertSame('NOW()', (string) $expr);
	}

	public function testExprPreservesRawFragment(): void
	{
		$qb = self::makeQB();

		self::assertSame('u.usr_name + 1', (string) $qb->expr('u.usr_name + 1'));
	}

	// -------------------------------------------------------------------------
	// sqlNull() / sqlTrue() / sqlFalse()
	// -------------------------------------------------------------------------

	public function testSqlNullReturnsNullLiteral(): void
	{
		$qb = self::makeQB();

		$expr = $qb->sqlNull();

		self::assertInstanceOf(QBExpression::class, $expr);
		self::assertSame('NULL', (string) $expr);
	}

	public function testSqlTrueReturnsTrueLiteral(): void
	{
		$qb = self::makeQB();

		$expr = $qb->sqlTrue();

		self::assertInstanceOf(QBExpression::class, $expr);
		self::assertSame('TRUE', (string) $expr);
	}

	public function testSqlFalseReturnsFalseLiteral(): void
	{
		$qb = self::makeQB();

		$expr = $qb->sqlFalse();

		self::assertInstanceOf(QBExpression::class, $expr);
		self::assertSame('FALSE', (string) $expr);
	}

	// -------------------------------------------------------------------------
	// col()
	// -------------------------------------------------------------------------

	public function testColResolvesColumnPrefixViaAlias(): void
	{
		$qb  = self::makeQB();
		$col = $qb->col('u', 'name');

		self::assertInstanceOf(QBExpression::class, $col);
		// table 'users' has prefix 'usr', so 'name' -> full name 'usr_name'
		self::assertSame('u.usr_name', (string) $col);
	}

	public function testColResolvesColumnPrefixViaTableName(): void
	{
		$qb  = self::makeQB();
		$col = $qb->col('users', 'name');

		self::assertInstanceOf(QBExpression::class, $col);
		// resolved to the main alias 'u' with prefixed column
		self::assertSame('u.usr_name', (string) $col);
	}

	public function testColWithUnknownAliasPassesThrough(): void
	{
		$qb  = self::makeQB();
		$col = $qb->col('foo', 'bar');

		// unknown alias/table -> verbatim dot-notation
		self::assertSame('foo.bar', (string) $col);
	}

	// -------------------------------------------------------------------------
	// fn()
	// -------------------------------------------------------------------------

	public function testFnWithNoArgsProducesEmptyCall(): void
	{
		$qb = self::makeQB();

		self::assertSame('NOW()', (string) $qb->fn('NOW'));
	}

	public function testFnWithQBExpressionArgIsVerbatim(): void
	{
		$qb = self::makeQB();

		self::assertSame('COUNT(*)', (string) $qb->fn('COUNT', $qb->expr('*')));
	}

	public function testFnWithStringArgIsQuoted(): void
	{
		$qb = self::makeQB();

		// plain string arguments must be quoted as SQL literals
		self::assertSame("DATE_FORMAT(u.usr_name, '%Y-%m')", (string) $qb->fn('DATE_FORMAT', $qb->col('u', 'name'), '%Y-%m'));
	}

	public function testFnEscapesSingleQuotesInStringArgs(): void
	{
		$qb = self::makeQB();

		// single quotes inside a string arg must be doubled
		self::assertSame("FIND_IN_SET('O''Brien')", (string) $qb->fn('FIND_IN_SET', "O'Brien"));
	}

	public function testFnMixedArgs(): void
	{
		$qb = self::makeQB();

		self::assertSame("COALESCE(u.usr_name, 'anonymous')", (string) $qb->fn('COALESCE', $qb->col('u', 'name'), 'anonymous'));
	}

	public function testFnMultipleQBExpressionArgs(): void
	{
		$qb = self::makeQB();

		self::assertSame('IF(u.usr_name, u.usr_id, u.usr_name)', (string) $qb->fn('IF', $qb->col('u', 'name'), $qb->col('u', 'id'), $qb->col('u', 'name')));
	}

	// -------------------------------------------------------------------------
	// quote()
	// -------------------------------------------------------------------------

	public function testQuoteWrapsInSingleQuotes(): void
	{
		$qb = self::makeQB();

		self::assertSame("'hello'", $qb->quote('hello'));
	}

	public function testQuoteEscapesSingleQuotes(): void
	{
		$qb = self::makeQB();

		self::assertSame("'O''Brien'", $qb->quote("O'Brien"));
	}

	public function testQuoteHandlesEmptyString(): void
	{
		$qb = self::makeQB();

		self::assertSame("''", $qb->quote(''));
	}

	public function testQuoteDoesNotRequireConnection(): void
	{
		// quote() must work without a live DB connection (no PDO needed)
		$qb = self::makeQB();

		// This would throw if quote() tried to use PDO::quote() without a connection
		self::assertSame("'safe'", $qb->quote('safe'));
	}

	// -------------------------------------------------------------------------
	// sub()
	// -------------------------------------------------------------------------

	public function testSubReturnsQBSelectInstance(): void
	{
		$qb  = self::makeQB();
		$sub = $qb->sub();

		self::assertInstanceOf(QBSelect::class, $sub);
	}

	public function testSubSharesDatabaseInstance(): void
	{
		$qb  = self::makeQB();
		$sub = $qb->sub();

		self::assertSame($qb->getRDBMS(), $sub->getRDBMS());
	}

	public function testSubWithFactoryConfiguresInstance(): void
	{
		$qb     = self::makeQB();
		$called = false;
		$sub    = $qb->sub(static function (QBSelect $s) use (&$called): void {
			$called = true;
		});

		self::assertTrue($called, 'Factory callable must be invoked.');
		self::assertInstanceOf(QBSelect::class, $sub);
	}

	public function testSubWithFactoryReturnsSameInstance(): void
	{
		$qb  = self::makeQB();
		$sub = $qb->sub(static function (QBSelect $s): QBSelect {
			return $s;
		});

		self::assertInstanceOf(QBSelect::class, $sub);
	}

	/**
	 * Creates a QBSelect instance with a 'users' table (prefix 'usr', columns: id, name)
	 * and registers the alias 'u' for it.
	 */
	private static function makeQB(): QBSelect
	{
		$db = self::getNewDbInstance();
		$ns = $db->ns('test');
		$ns->table('users', static function (TableBuilder $t) {
			$t->columnPrefix('usr');
			$t->id();
			$t->string('name');
		});
		$db->lock();

		$qb = new QBSelect($db);
		$qb->from('users', 'u');

		return $qb;
	}
}
