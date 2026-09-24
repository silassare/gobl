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

namespace Gobl\Tests\ORM;

use Gobl\DBAL\Types\TypeEnum;
use Gobl\DBAL\Types\TypeString;
use Gobl\ORM\Generators\CSGeneratorTS;
use Gobl\Tests\BaseTestCase;
use Gobl\Tests\Fixtures\SampleKind;
use OLIUP\CG\PHPEnum;

/**
 * Class CSGeneratorTSEnumTest.
 *
 * An enum column must reach TypeScript as the enum that `enums.ts` generates, not as a bare
 * `string`: the enum class travels on the PHP type hint, which only the PHP generator used to read.
 *
 * @covers \Gobl\ORM\Generators\CSGeneratorTS
 *
 * @internal
 */
final class CSGeneratorTSEnumTest extends BaseTestCase
{
	public function testEnumColumnIsTypedWithItsEnum(): void
	{
		$generator = new CSGeneratorTS(self::getNewDbInstanceWithSchema());
		$type      = new TypeEnum(SampleKind::class);

		self::assertSame('SampleKind', $generator->getReadTypeHintString($type));
		self::assertSame('SampleKind', $generator->getWriteTypeHintString($type));
	}

	public function testNullableEnumColumnKeepsItsNull(): void
	{
		$generator = new CSGeneratorTS(self::getNewDbInstanceWithSchema());
		$type      = (new TypeEnum(SampleKind::class))->nullable();

		self::assertSame('SampleKind|null', $generator->getReadTypeHintString($type));
	}

	/** A type that is not an enum is unaffected. */
	public function testPlainStringIsStillAString(): void
	{
		$generator = new CSGeneratorTS(self::getNewDbInstanceWithSchema());

		self::assertSame('string', $generator->getReadTypeHintString(new TypeString()));
	}

	/** The name is the one `enums.ts` declares, so the import and the type always agree. */
	public function testTheNameIsTheGeneratedEnumName(): void
	{
		$generator = new CSGeneratorTS(self::getNewDbInstanceWithSchema());
		$name      = $generator->getReadTypeHintString(new TypeEnum(SampleKind::class));

		self::assertSame((new PHPEnum(SampleKind::class))->getName(), $name);
	}
}
