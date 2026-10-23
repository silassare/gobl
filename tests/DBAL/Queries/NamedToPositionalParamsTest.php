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

use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Queries\NamedToPositionalParams;
use PHPUnit\Framework\TestCase;

/**
 * Class NamedToPositionalParamsTest.
 *
 * @covers \Gobl\DBAL\Queries\NamedToPositionalParams
 *
 * @internal
 */
final class NamedToPositionalParamsTest extends TestCase
{
	public function testEachParameterBecomesAPlaceholder(): void
	{
		$n = new NamedToPositionalParams(
			'SELECT * FROM t WHERE a = :a AND b = :b',
			['a' => 1, 'b' => 'two']
		);

		self::assertSame('SELECT * FROM t WHERE a = ? AND b = ?', $n->getNewQuery());
		self::assertSame([1, 'two'], $n->getNewParams());
	}

	public function testAnArrayValueIsExpandedIntoAnInList(): void
	{
		$n = new NamedToPositionalParams('SELECT * FROM t WHERE a IN (:a)', ['a' => [1, 2, 3]]);

		self::assertSame('SELECT * FROM t WHERE a IN (?, ?, ?)', $n->getNewQuery());
		self::assertSame([1, 2, 3], $n->getNewParams());
	}

	/**
	 * A cast is not a parameter: PostgreSQL writes `x::jsonb`, and the second colon of it used to be
	 * read as a parameter named after the type, which nothing had bound.
	 */
	public function testACastIsNotReadAsAParameter(): void
	{
		$n = new NamedToPositionalParams(
			'UPDATE t SET data = jsonb_set((data)::jsonb, ARRAY[:seg]::text[], (:val)::jsonb, true)'
				. ' WHERE id = :id',
			['seg' => 'lang', 'val' => '"fr"', 'id' => 7]
		);

		self::assertSame(
			'UPDATE t SET data = jsonb_set((data)::jsonb, ARRAY[?]::text[], (?)::jsonb, true) WHERE id = ?',
			$n->getNewQuery()
		);
		self::assertSame(['lang', '"fr"', 7], $n->getNewParams());
	}

	public function testAParameterThatWasNotBoundIsRefused(): void
	{
		$this->expectException(DBALException::class);

		new NamedToPositionalParams('SELECT * FROM t WHERE a = :a', []);
	}
}
