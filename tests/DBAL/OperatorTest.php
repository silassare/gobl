<?php

/**
 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Gobl\Tests\DBAL;

use Gobl\DBAL\Operator;
use Gobl\Tests\BaseTestCase;

/**
 * Class OperatorTest.
 *
 * @covers \Gobl\DBAL\Operator
 *
 * @internal
 */
final class OperatorTest extends BaseTestCase
{
	public function testIsUnaryAndOperandsCountMatches(): void
	{
		$expected = [];
		$found    = [];

		foreach (Operator::cases() as $op) {
			$expected[$op->name] = [
				'operands_count' => $op->isUnary() ? 1 : 2,
			];
			$found[$op->name]    = [
				'operands_count' => $op->getOperandsCount(),
			];
		}

		self::assertSame($expected, $found);
	}
}
