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

use Gobl\DBAL\Table;
use Gobl\Tests\BaseTestCase;

/**
 * Class TableTest.
 *
 * @covers \Gobl\DBAL\Table
 *
 * @internal
 */
final class TableTest extends BaseTestCase
{
	public function testConstructor(): void
	{
		$name   = 'users';
		$prefix = 'pr';

		$table = new Table($name, $prefix);

		static::assertSame($name, $table->getName());
		static::assertSame($prefix, $table->getPrefix());
		static::assertSame($prefix . '_' . $name, $table->getFullName());
		static::assertSame($name . '_entity', $table->getSingularName());
		static::assertSame($name . '_entities', $table->getPluralName());

		$table = new Table($name);

		static::assertSame($name, $table->getName());
		static::assertSame('', $table->getPrefix());
		static::assertSame($name, $table->getFullName());
		static::assertSame($name . '_entity', $table->getSingularName());
		static::assertSame($name . '_entities', $table->getPluralName());
	}
}
