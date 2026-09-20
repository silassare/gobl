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
use PHPUnit\Framework\TestCase;
use PHPUtils\PortablePattern;

/**
 * Class TypeStringPatternTest.
 *
 * A string pattern is portable ({@see PortablePattern}, php-utils) and runs as JavaScript runs it.
 *
 * @covers \Gobl\DBAL\Types\TypeString
 *
 * @internal
 */
final class TypeStringPatternTest extends TestCase
{
	public function testAPortablePatternIsAccepted(): void
	{
		$type = (new TypeString())->pattern('~^[a-z]+$~i');

		self::assertSame('Abc', $type->validate('Abc')->getCleanValue());
	}

	public function testARefusedPatternIsRefusedByTheType(): void
	{
		$this->expectException(TypesException::class);
		$this->expectExceptionMessage('possessive');

		(new TypeString())->pattern('~a++~');
	}

	public function testASchemaOptionIsCheckedToo(): void
	{
		$this->expectException(TypesException::class);

		(new TypeString())->configure(['pattern' => '~\Aa~']);
	}

	public function testDollarIsTheEndOfTheValueAsInJavaScript(): void
	{
		$type = (new TypeString())->pattern('~^abc$~');

		self::assertSame('abc', $type->validate('abc')->getCleanValue());

		// PCRE's default `$` also matches before a final newline; JavaScript's never does.
		$this->expectException(TypesInvalidValueException::class);

		$type->validate("abc\n");
	}

	public function testAPatternRunsOnCharactersNotBytes(): void
	{
		$type = (new TypeString())->pattern('~^.{2}$~');

		// Two characters, four bytes: without Unicode mode PCRE would count bytes.
		self::assertSame('éé', $type->validate('éé')->getCleanValue());
	}

	public function testInvalidUtf8DoesNotMatch(): void
	{
		$type = (new TypeString())->pattern('~^.+$~');

		$this->expectException(TypesInvalidValueException::class);

		$type->validate("\xC3\x28");
	}
}
