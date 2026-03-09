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

namespace Gobl\Tests\DBAL\Types;

use Gobl\DBAL\Types\Exceptions\TypesException;
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\TypeString;
use Gobl\Tests\BaseTestCase;

/**
 * Class TypeStringTest.
 *
 * @covers \Gobl\DBAL\Types\TypeString
 *
 * @internal
 */
final class TypeStringTest extends BaseTestCase
{
	public function testStringValid(): void
	{
		$t = new TypeString();
		self::assertSame('hello world', $t->validate('hello world')->getCleanValue());
	}

	public function testStringAcceptsNumericInput(): void
	{
		$t = new TypeString();
		self::assertSame('42', $t->validate(42)->getCleanValue());
	}

	public function testStringNullWithNullable(): void
	{
		$t = (new TypeString())->nullable();
		self::assertNull($t->validate(null)->getCleanValue());
	}

	public function testStringNullWithDefault(): void
	{
		$t = (new TypeString())->default('N/A');
		self::assertSame('N/A', $t->validate(null)->getCleanValue());
	}

	public function testStringEmptyFallsBackToDefault(): void
	{
		$t = (new TypeString())->default('fallback');
		self::assertSame('fallback', $t->validate('')->getCleanValue());
	}

	public function testStringMinConstraint(): void
	{
		$t = (new TypeString())->min(5);
		$this->expectException(TypesInvalidValueException::class);
		$t->validate('hi')->getCleanValue();
	}

	public function testStringMaxConstraint(): void
	{
		$t = (new TypeString())->max(5);
		$this->expectException(TypesInvalidValueException::class);
		$t->validate('hello world')->getCleanValue();
	}

	public function testStringTruncateInsteadOfThrow(): void
	{
		$t = (new TypeString())->max(5)->truncate();
		self::assertSame('hello', $t->validate('hello world')->getCleanValue());
	}

	public function testStringPatternValid(): void
	{
		$t = (new TypeString())->pattern('/^[a-z]+$/');
		self::assertSame('hello', $t->validate('hello')->getCleanValue());
	}

	public function testStringPatternInvalid(): void
	{
		$t = (new TypeString())->pattern('/^[a-z]+$/');
		$this->expectException(TypesInvalidValueException::class);
		$t->validate('Hello123')->getCleanValue();
	}

	public function testStringOneOfValid(): void
	{
		$t = (new TypeString())->oneOf(['active', 'inactive', 'pending']);
		self::assertSame('active', $t->validate('active')->getCleanValue());
	}

	public function testStringOneOfInvalid(): void
	{
		$t = (new TypeString())->oneOf(['active', 'inactive']);
		$this->expectException(TypesInvalidValueException::class);
		$t->validate('deleted')->getCleanValue();
	}

	public function testStringMultilineCollapseWhitespace(): void
	{
		// by default multiline=true, so whitespace is not collapsed in multiline strings
		// disable multiline to collapse
		$t = (new TypeString())->multiline(false);
		self::assertSame('a b c', $t->validate("a   b\n  c")->getCleanValue());
	}

	public function testStringTrimOption(): void
	{
		$t = (new TypeString())->trim();
		self::assertSame('hello', $t->validate('  hello  ')->getCleanValue());
	}

	public function testStringMinGreaterThanMaxThrows(): void
	{
		$this->expectException(TypesException::class);
		(new TypeString())->min(10)->max(5);
	}
}
